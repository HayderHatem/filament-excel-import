<?php

namespace HayderHatem\FilamentExcelImport\Actions\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ImportAction as ImportTableAction;
use HayderHatem\FilamentExcelImport\Actions\Imports\Jobs\ImportExcel;
use HayderHatem\FilamentExcelImport\Models\Import;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait CanImportExcelRecords
{
    /**
     * @var class-string<Importer>
     */
    protected string $importer;
    protected ?string $job = null;
    protected int | Closure $chunkSize = 100;
    protected int | Closure | null $maxRows = null;
    protected int | Closure | null $headerOffset = null;
    protected int | Closure | null $activeSheet = null;
    /**
     * @var array<string, mixed> | Closure
     */
    protected array | Closure $options = [];
    /**
     * @var array<string | array<mixed> | Closure>
     */
    protected array $fileValidationRules = [];

    /**
     * Additional form components to include in the import form
     * @var array<\Filament\Forms\Components\Component>
     */
    protected array $additionalFormComponents = [];

    /**
     * Whether to use streaming import for large files (default: auto-detect)
     */
    protected bool | Closure | null $useStreaming = null;

    /**
     * File size threshold for auto-enabling streaming (in bytes)
     */
    protected int | Closure $streamingThreshold = 1048576; // 1MB (was 10MB)

    protected function setUp(): void
    {
        parent::setUp();
        $this->label(fn(ImportAction | ImportTableAction $action): string => __('filament-actions::import.label', ['label' => $action->getPluralModelLabel()]));
        $this->modalHeading(fn(ImportAction | ImportTableAction $action): string => __('filament-actions::import.modal.heading', ['label' => $action->getPluralModelLabel()]));
        $this->modalDescription(fn(ImportAction | ImportTableAction $action): Htmlable => $action->getModalAction('downloadExample'));
        $this->modalSubmitActionLabel(__('filament-actions::import.modal.actions.import.label'));
        $this->groupedIcon(FilamentIcon::resolve('actions::import-action.grouped') ?? 'heroicon-m-arrow-up-tray');

        $this->form(fn(ImportAction | ImportTableAction $action): array => array_merge([
            FileUpload::make('file')
                ->label(__('filament-actions::import.modal.form.file.label'))
                ->placeholder(__('filament-actions::import.modal.form.file.placeholder'))
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'application/octet-stream',
                    'text/csv',
                    'application/csv',
                    'application/excel',
                    'application/vnd.msexcel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                    'application/vnd.ms-excel.sheet.macroEnabled.12',
                    'application/vnd.ms-excel.template.macroEnabled.12',
                    'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
                ])
                ->rules($action->getFileValidationRules())
                ->afterStateUpdated(function (FileUpload $component, Component $livewire, Forms\Set $set, ?TemporaryUploadedFile $state) use ($action) {
                    if (! $state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    try {
                        $livewire->validateOnly($component->getStatePath());
                    } catch (ValidationException $exception) {
                        $component->state([]);

                        throw $exception;
                    }

                    try {
                        // Only read headers for column mapping - much more memory efficient
                        $headers = $this->getExcelHeaders($state, $action->getHeaderOffset() ?? 0);

                        if (empty($headers)) {
                            // No headers found, use manual mapping
                            $this->setBasicColumnMapping($set, $action);

                            Notification::make()
                                ->title(__('No headers detected'))
                                ->body(__('Could not detect column headers. Please map columns manually using the text inputs below.'))
                                ->warning()
                                ->send();

                            return;
                        }

                        // Set up column mapping with detected headers
                        $lowercaseExcelColumnValues = array_map(Str::lower(...), $headers);
                        $lowercaseExcelColumnKeys = array_combine(
                            $lowercaseExcelColumnValues,
                            $headers,
                        );

                        $set('columnMap', array_reduce($action->getImporter()::getColumns(), function (array $carry, ImportColumn $column) use ($lowercaseExcelColumnKeys, $lowercaseExcelColumnValues) {
                            $carry[$column->getName()] = $lowercaseExcelColumnKeys[Arr::first(
                                array_intersect(
                                    $lowercaseExcelColumnValues,
                                    $column->getGuesses(),
                                ),
                            )] ?? null;

                            return $carry;
                        }, []));

                        // Try to get sheet names for multi-sheet files (but don't fail if it doesn't work)
                        try {
                            $sheetNames = $this->getExcelSheetNames($state);
                            if (!empty($sheetNames)) {
                                $set('availableSheets', $sheetNames);
                                $set('activeSheet', $action->getActiveSheet() ?? 0);
                            } else {
                                $set('availableSheets', []);
                                $set('activeSheet', null);
                            }
                        } catch (\Throwable $e) {
                            // If sheet detection fails, just continue without it
                            $set('availableSheets', []);
                            $set('activeSheet', null);
                        }
                    } catch (\Throwable $e) {
                        // Handle any errors during header reading
                        Notification::make()
                            ->title(__('File preview unavailable'))
                            ->body(__('Unable to preview file contents. You can still import, but please map columns manually.'))
                            ->warning()
                            ->send();

                        // Set basic column mapping as fallback
                        $this->setBasicColumnMapping($set, $action);
                    }
                })
                ->storeFiles(false)
                ->visibility('private')
                ->required()
                ->hiddenLabel(),
            Select::make('activeSheet')
                ->label(__('Sheet'))
                ->options(fn(Forms\Get $get): array => $get('availableSheets') ?? [])
                ->visible(fn(Forms\Get $get): bool => is_array($get('availableSheets')) && count($get('availableSheets')) > 1)
                ->reactive()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) use ($action) {
                    $file = Arr::first((array) ($get('file') ?? []));
                    if (! $file instanceof TemporaryUploadedFile) {
                        return;
                    }

                    try {
                        // Use lightweight header reading for the selected sheet
                        $headers = $this->getExcelHeaders($file, $action->getHeaderOffset() ?? 0);

                        if (empty($headers)) {
                            // No headers found, reset to manual mapping
                            $this->setBasicColumnMapping($set, $action);
                            return;
                        }

                        // Reset column map to ensure clean state
                        $set('columnMap', []);

                        $lowercaseExcelColumnValues = array_map(Str::lower(...), $headers);
                        $lowercaseExcelColumnKeys = array_combine(
                            $lowercaseExcelColumnValues,
                            $headers,
                        );

                        // Set new column mapping
                        $set('columnMap', array_reduce($action->getImporter()::getColumns(), function (array $carry, ImportColumn $column) use ($lowercaseExcelColumnKeys, $lowercaseExcelColumnValues) {
                            $carry[$column->getName()] = $lowercaseExcelColumnKeys[Arr::first(
                                array_intersect(
                                    $lowercaseExcelColumnValues,
                                    $column->getGuesses(),
                                ),
                            )] ?? null;

                            return $carry;
                        }, []));
                    } catch (\Throwable $e) {
                        // Handle any errors
                        $this->setBasicColumnMapping($set, $action);

                        Notification::make()
                            ->title(__('Sheet reading error'))
                            ->body(__('Unable to read the selected sheet. Column mapping has been reset.'))
                            ->warning()
                            ->send();
                    }
                }),
            // Add additional form components section
            ...$this->getAdditionalFormComponents(),
            Fieldset::make(__('filament-actions::import.modal.form.columns.label'))
                ->columns(1)
                ->inlineLabel()
                ->schema(function (Forms\Get $get) use ($action): array {
                    $file = Arr::first((array) ($get('file') ?? []));
                    if (! $file instanceof TemporaryUploadedFile) {
                        return [];
                    }

                    try {
                        // Use lightweight header reading
                        $headers = $this->getExcelHeaders($file, $action->getHeaderOffset() ?? 0);

                        if (empty($headers)) {
                            // No headers found, fallback to manual mapping
                            return $this->getManualColumnMappingSchema($action);
                        }

                        $excelColumnOptions = array_combine($headers, $headers);

                        return array_map(
                            fn(ImportColumn $column): Select => $column->getSelect()->options($excelColumnOptions),
                            $action->getImporter()::getColumns(),
                        );
                    } catch (\Throwable $e) {
                        // Any error during column reading, fallback to manual mapping
                        return $this->getManualColumnMappingSchema($action);
                    }
                })
                ->statePath('columnMap')
                ->visible(fn(Forms\Get $get): bool => Arr::first((array) ($get('file') ?? [])) instanceof TemporaryUploadedFile),
        ], $action->getImporter()::getOptionsFormComponents()));

        $this->action(function (ImportAction | ImportTableAction $action, array $data) {
            /** @var TemporaryUploadedFile $excelFile */
            $excelFile = $data['file'];
            $activeSheetIndex = $data['activeSheet'] ?? $action->getActiveSheet() ?? 0;

            // Extract additional form data
            $additionalFormData = $this->extractAdditionalFormData($data);

            try {
                // Use streaming approach to get total row count without loading everything into memory
                $totalRows = $this->getExcelRowCount($excelFile, $activeSheetIndex, $action->getHeaderOffset() ?? 0);

                $maxRows = $action->getMaxRows() ?? $totalRows;
                if ($maxRows < $totalRows) {
                    Notification::make()
                        ->title(__('filament-actions::import.notifications.max_rows.title'))
                        ->body(trans_choice('filament-actions::import.notifications.max_rows.body', $maxRows, [
                            'count' => Number::format($maxRows),
                        ]))
                        ->danger()
                        ->send();

                    return;
                }

                $user = Auth::check() ? Auth::user() : null;

                // Store the uploaded file permanently for streaming processing
                $permanentFilePath = $this->storePermanentFile($excelFile);

                $import = app(Import::class);
                if ($user) {
                    $import->user()->associate($user);
                }
                $import->file_name = $excelFile->getClientOriginalName();
                $import->file_path = $permanentFilePath;
                $import->importer = $action->getImporter();
                $import->total_rows = $totalRows;
                $import->save();

                // Store the import ID for later use
                $importId = $import->id;

                // Convert options to serializable format and include additional form data
                $options = array_merge(
                    $action->getOptions(),
                    Arr::except($data, ['file', 'columnMap']),
                    [
                        'additional_form_data' => $additionalFormData,
                        'activeSheet' => $activeSheetIndex,
                        'headerOffset' => $action->getHeaderOffset() ?? 0
                    ]
                );

                // Unset non-serializable relations to prevent issues
                $import->unsetRelation('user');

                $columnMap = $data['columnMap'];

                // Determine if we should use streaming import
                $useStreaming = $this->shouldUseStreaming($excelFile);

                if ($useStreaming) {
                    // Create streaming import chunks based on row ranges instead of loading data
                    $chunkSize = $action->getChunkSize();
                    $headerOffset = $action->getHeaderOffset() ?? 0;
                    $startDataRow = $headerOffset + 2; // Header offset + 1 for header row + 1 for first data row
                    $endDataRow = $headerOffset + 1 + $totalRows;

                    $importChunks = collect();
                    for ($currentRow = $startDataRow; $currentRow <= $endDataRow; $currentRow += $chunkSize) {
                        $endChunkRow = min($currentRow + $chunkSize - 1, $endDataRow);

                        $importChunks->push(app($action->getJob(), [
                            'importId' => $importId,
                            'rows' => null,
                            'startRow' => $currentRow,
                            'endRow' => $endChunkRow,
                            'columnMap' => $columnMap,
                            'options' => $options,
                        ]));
                    }
                } else {
                    // Fall back to original approach for smaller files
                    try {
                        $spreadsheet = $this->getUploadedFileSpreadsheet($excelFile);
                        if (! $spreadsheet) {
                            return;
                        }

                        $worksheet = $spreadsheet->getSheet((int) $activeSheetIndex);
                        $headerOffset = $action->getHeaderOffset() ?? 0;
                        // Get all data from the worksheet
                        $rows = [];
                        $highestRow = $worksheet->getHighestDataRow();
                        $highestColumn = $worksheet->getHighestDataColumn();
                        // Get header row
                        $headers = [];
                        $headerRowNumber = $headerOffset + 1;
                        foreach ($worksheet->getRowIterator($headerRowNumber, $headerRowNumber) as $row) {
                            $cellIterator = $row->getCellIterator('A', $highestColumn);
                            $cellIterator->setIterateOnlyExistingCells(false);
                            foreach ($cellIterator as $cell) {
                                $headers[] = $cell->getValue();
                            }
                        }
                        // Get data rows
                        for ($rowIndex = $headerRowNumber + 1; $rowIndex <= $highestRow; $rowIndex++) {
                            $rowData = [];
                            $hasData = false;
                            foreach ($worksheet->getRowIterator($rowIndex, $rowIndex) as $row) {
                                $cellIterator = $row->getCellIterator('A', $highestColumn);
                                $cellIterator->setIterateOnlyExistingCells(false);
                                $columnIndex = 0;
                                foreach ($cellIterator as $cell) {
                                    $value = $cell->getValue();
                                    if ($value !== null) {
                                        $hasData = true;
                                    }
                                    $rowData[$headers[$columnIndex] ?? $columnIndex] = $value;
                                    $columnIndex++;
                                }
                            }
                            if ($hasData) {
                                $rows[] = $rowData;
                            }
                        }

                        // Create import chunks with import ID instead of full model
                        $importChunks = collect($rows)->chunk($action->getChunkSize())
                            ->map(fn($chunk) => app($action->getJob(), [
                                'importId' => $importId,
                                'rows' => base64_encode(serialize($chunk->all())),
                                'startRow' => null,
                                'endRow' => null,
                                'columnMap' => $columnMap,
                                'options' => $options,
                            ]));
                    } catch (\Exception $e) {
                        // If regular loading fails, fall back to streaming
                        Notification::make()
                            ->title(__('Switching to streaming mode'))
                            ->body(__('File too large for standard processing, using streaming import...'))
                            ->info()
                            ->send();

                        $chunkSize = $action->getChunkSize();
                        $headerOffset = $action->getHeaderOffset() ?? 0;
                        $startDataRow = $headerOffset + 2;
                        $endDataRow = $headerOffset + 1 + $totalRows;

                        $importChunks = collect();
                        for ($currentRow = $startDataRow; $currentRow <= $endDataRow; $currentRow += $chunkSize) {
                            $endChunkRow = min($currentRow + $chunkSize - 1, $endDataRow);

                            $importChunks->push(app($action->getJob(), [
                                'importId' => $importId,
                                'rows' => null,
                                'startRow' => $currentRow,
                                'endRow' => $endChunkRow,
                                'columnMap' => $columnMap,
                                'options' => $options,
                            ]));
                        }
                    }
                }

                // Get importer with proper parameters
                $importer = $import->getImporter(
                    columnMap: $columnMap,
                    options: $options
                );

                event(new ImportStarted($import, $columnMap, $options));

                Bus::batch($importChunks->all())
                    ->allowFailures()
                    ->when(
                        filled($jobQueue = $importer->getJobQueue()),
                        fn(PendingBatch $batch) => $batch->onQueue($jobQueue),
                    )
                    ->when(
                        filled($jobConnection = $importer->getJobConnection()),
                        fn(PendingBatch $batch) => $batch->onConnection($jobConnection),
                    )
                    ->when(
                        filled($jobBatchName = $importer->getJobBatchName()),
                        fn(PendingBatch $batch) => $batch->name($jobBatchName),
                    )
                    ->finally(function () use ($importId, $columnMap, $options, $jobConnection, $permanentFilePath) {
                        // Retrieve fresh import from database in the callback to avoid serialization issues
                        $import = Import::query()->find($importId);

                        if (! $import) {
                            return;
                        }

                        $import->touch('completed_at');

                        // Clean up the temporary file after import is complete
                        if (file_exists($permanentFilePath)) {
                            @unlink($permanentFilePath);
                        }

                        event(new ImportCompleted($import, $columnMap, $options));

                        // Check if user relation can be safely accessed
                        $user = $import->user;
                        if (! $user instanceof Authenticatable) {
                            return;
                        }

                        $failedRowsCount = $import->getFailedRowsCount();

                        Notification::make()
                            ->title($import->importer::getCompletedNotificationTitle($import))
                            ->body($import->importer::getCompletedNotificationBody($import))
                            ->when(
                                ! $failedRowsCount,
                                fn(Notification $notification) => $notification->success(),
                            )
                            ->when(
                                $failedRowsCount && ($failedRowsCount < $import->total_rows),
                                fn(Notification $notification) => $notification->warning(),
                            )
                            ->when(
                                $failedRowsCount === $import->total_rows,
                                fn(Notification $notification) => $notification->danger(),
                            )
                            ->when(
                                $failedRowsCount,
                                fn(Notification $notification) => $notification->actions([
                                    NotificationAction::make('downloadFailedRowsCsv')
                                        ->label(trans_choice('filament-actions::import.notifications.completed.actions.download_failed_rows_csv.label', $failedRowsCount, [
                                            'count' => Number::format($failedRowsCount),
                                        ]))
                                        ->color('danger')
                                        ->url(route('filament.imports.failed-rows.download', ['import' => $import], absolute: false), shouldOpenInNewTab: true)
                                        ->markAsRead(),
                                ]),
                            )
                            ->when(
                                ($jobConnection === 'sync') ||
                                    (blank($jobConnection) && (config('queue.default') === 'sync')),
                                fn(Notification $notification) => $notification
                                    ->persistent()
                                    ->send(),
                                fn(Notification $notification) => $notification->sendToDatabase($import->user, isEventDispatched: true),
                            );
                    })
                    ->dispatch();

                if (
                    (filled($jobConnection) && ($jobConnection !== 'sync')) ||
                    (blank($jobConnection) && (config('queue.default') !== 'sync'))
                ) {
                    Notification::make()
                        ->title($action->getSuccessNotificationTitle())
                        ->body(trans_choice('filament-actions::import.notifications.started.body', $import->total_rows, [
                            'count' => Number::format($import->total_rows),
                        ]))
                        ->success()
                        ->send();
                }
            } catch (ReaderException $e) {
                Notification::make()
                    ->title(__('Error processing Excel file'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });

        $this->registerModalActions([
            (match (true) {
                $this instanceof TableAction => TableAction::class,
                default => Action::class,
            })::make('downloadExample')
                ->label(__('Download Template'))
                ->link()
                ->action(function (): StreamedResponse {
                    $columns = $this->getImporter()::getColumns();
                    // Create a new Spreadsheet
                    $spreadsheet = new Spreadsheet();
                    $worksheet = $spreadsheet->getActiveSheet();
                    // Add headers
                    $columnIndex = 1;
                    foreach ($columns as $column) {
                        $worksheet->setCellValue(
                            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '1',
                            $column->getExampleHeader()
                        );
                        $columnIndex++;
                    }
                    // Add example data
                    $columnExamples = array_map(
                        fn(ImportColumn $column): array => $column->getExamples(),
                        $columns,
                    );
                    $exampleRowsCount = array_reduce(
                        $columnExamples,
                        fn(int $count, array $exampleData): int => max($count, count($exampleData)),
                        initial: 0,
                    );
                    for ($rowIndex = 0; $rowIndex < $exampleRowsCount; $rowIndex++) {
                        $columnIndex = 1;
                        foreach ($columnExamples as $exampleData) {
                            $worksheet->setCellValue(
                                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . ($rowIndex + 2),
                                $exampleData[$rowIndex] ?? ''
                            );
                            $columnIndex++;
                        }
                    }
                    // Create Excel writer
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

                    return response()->streamDownload(function () use ($writer) {
                        $writer->save('php://output');
                    }, __('filament-actions::import.example_csv.file_name', ['importer' => (string) str($this->getImporter())->classBasename()->kebab()]) . '.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
        ]);

        $this->color('gray');
        $this->modalWidth('xl');
        $this->successNotificationTitle(__('filament-actions::import.notifications.started.title'));
        $this->model(fn(ImportAction | ImportTableAction $action): string => $action->getImporter()::getModel());
    }

    /**
     * Get the uploaded file spreadsheet (legacy method - kept for compatibility).
     * NOTE: This method is now primarily used for backward compatibility.
     * For header reading, use getExcelHeaders() instead for better memory efficiency.
     */
    protected function getUploadedFileSpreadsheet(TemporaryUploadedFile $file): ?Spreadsheet
    {
        $path = $file->getRealPath();
        if (! file_exists($path)) {
            return null;
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Very restrictive filter - only read first few rows and columns
            $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    // Only read first 3 rows and first 50 columns to minimize memory
                    return $row <= 3 && preg_match('/^[A-Z]{1,2}$/', preg_replace('/\d+/', '', $columnAddress));
                }
            });

            return $reader->load($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the active worksheet from a spreadsheet.
     */
    protected function getActiveWorksheet(Spreadsheet $spreadsheet): Worksheet
    {
        $activeSheet = $this->getActiveSheet();
        if ($activeSheet !== null) {
            return $spreadsheet->getSheet($activeSheet);
        }

        return $spreadsheet->getActiveSheet();
    }

    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    /**
     * @param  class-string<Importer>  $importer
     */
    public function importer(string $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * @return class-string<Importer>
     */
    public function getImporter(): string
    {
        return $this->importer;
    }

    /**
     * Get the job to use for importing.
     */
    public function getJob(): string
    {
        return $this->job ?? ImportExcel::class;
    }

    /**
     * Set the job to use for importing.
     *
     * @param  ?string  $job
     */
    public function job(?string $job): static
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get the chunk size for importing.
     */
    public function getChunkSize(): int
    {
        return $this->evaluate($this->chunkSize);
    }

    /**
     * Set the chunk size for importing.
     *
     * @param  int | Closure  $size
     */
    public function chunkSize(int | Closure $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Get the maximum number of rows that can be imported.
     */
    public function getMaxRows(): ?int
    {
        return $this->evaluate($this->maxRows);
    }

    /**
     * Set the maximum number of rows that can be imported.
     *
     * @param  int | Closure | null  $count
     */
    public function maxRows(int | Closure | null $count): static
    {
        $this->maxRows = $count;

        return $this;
    }

    /**
     * Get the header row number (1-based).
     */
    public function getHeaderOffset(): ?int
    {
        return $this->evaluate($this->headerOffset);
    }

    /**
     * Set the header row number (1-based).
     *
     * @param  int | Closure | null  $row
     */
    public function headerOffset(int | Closure | null $row): static
    {
        $this->headerOffset = $row;

        return $this;
    }

    /**
     * Get the active sheet index (0-based).
     */
    public function getActiveSheet(): ?int
    {
        return $this->evaluate($this->activeSheet);
    }

    /**
     * Set the active sheet index (0-based).
     *
     * @param  int | Closure | null  $sheet
     */
    public function activeSheet(int | Closure | null $sheet): static
    {
        $this->activeSheet = $sheet;

        return $this;
    }

    /**
     * Get the options for importing.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    /**
     * Set the options for importing.
     *
     * @param  array<string, mixed> | Closure  $options
     */
    public function options(array | Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the validation rules for the imported file.
     *
     * @return array<string | array<mixed> | Closure>
     */
    public function getFileValidationRules(): array
    {
        return [
            ...$this->fileValidationRules,
            function () {
                return File::types([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'application/octet-stream',
                    'text/csv',
                    'application/csv',
                    'application/excel',
                    'application/vnd.msexcel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                    'application/vnd.ms-excel.sheet.macroEnabled.12',
                    'application/vnd.ms-excel.template.macroEnabled.12',
                    'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
                ]);
            },
        ];
    }

    /**
     * Set the validation rules for the imported file.
     *
     * @param  array<string | array<mixed> | Closure>  $rules
     */
    public function fileValidationRules(array $rules): static
    {
        $this->fileValidationRules = $rules;

        return $this;
    }

    /**
     * Get additional form components to include in the import form.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getAdditionalFormComponents(): array
    {
        if (empty($this->additionalFormComponents)) {
            return [];
        }

        return [
            Fieldset::make(__('filament-excel-import::import.modal.form.import_options.label'))
                ->schema($this->additionalFormComponents)
                ->columns(2)
        ];
    }

    /**
     * Add additional form components to the import form.
     *
     * @param array<\Filament\Forms\Components\Component> $components
     */
    public function additionalFormComponents(array $components): static
    {
        $this->additionalFormComponents = $components;
        return $this;
    }

    /**
     * Extract additional form data from submitted data.
     */
    protected function extractAdditionalFormData(array $data): array
    {
        if (empty($this->additionalFormComponents)) {
            return [];
        }

        $additionalKeys = collect($this->additionalFormComponents)
            ->map(fn($component) => $component->getName())
            ->filter()
            ->toArray();

        return Arr::only($data, $additionalKeys);
    }

    /**
     * Store the uploaded file permanently for streaming processing.
     */
    protected function storePermanentFile(TemporaryUploadedFile $file): string
    {
        $path = $file->getRealPath();
        $permanentFilePath = tempnam(sys_get_temp_dir(), 'import_');
        if (! $permanentFilePath) {
            throw new \Exception('Failed to create temporary file');
        }

        try {
            copy($path, $permanentFilePath);
            return $permanentFilePath;
        } catch (\Exception $e) {
            throw new \Exception('Failed to store file permanently: ' . $e->getMessage());
        }
    }

    /**
     * Get the Excel row count without loading everything into memory.
     */
    protected function getExcelRowCount(TemporaryUploadedFile $file, int $activeSheetIndex, int $headerOffset): int
    {
        $path = $file->getRealPath();
        if (! file_exists($path)) {
            throw new \Exception('File not found');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Method 1: Try to get row count without loading data by reading structure only
            try {
                $spreadsheet = $reader->load($path);
                $worksheet = $spreadsheet->getSheet($activeSheetIndex);
                $highestRow = $worksheet->getHighestDataRow();

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet);

                if ($highestRow > $headerOffset + 1) {
                    return $highestRow - ($headerOffset + 1);
                }
            } catch (\Exception $e) {
                // Continue to next method
            }

            // Method 2: If Method 1 fails or returns low count, try reading with minimal filter
            try {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell($columnAddress, $row, $worksheetName = ''): bool
                    {
                        return $row === 1 || $row % 10 === 0;
                    }
                });

                $spreadsheet = $reader->load($path);
                $worksheet = $spreadsheet->getSheet($activeSheetIndex);
                $highestRow = $worksheet->getHighestDataRow();

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet);

                if ($highestRow > $headerOffset + 1) {
                    return $highestRow - ($headerOffset + 1);
                }
            } catch (\Exception $e) {
                // Continue to next method
            }

            // Method 3: Fallback - use file-based estimation for large files
            $fileSize = filesize($path);
            if ($fileSize > 1024 * 1024) {
                return intval($fileSize / 80);
            }

            // Method 4: Final fallback
            return 1000;
        } catch (\Exception $e) {
            // Last resort: estimate based on file size
            $fileSize = filesize($path);
            return max(100, intval($fileSize / 100)); // Very conservative fallback
        }
    }

    /**
     * Set whether to use streaming import.
     */
    public function useStreaming(bool | Closure | null $useStreaming = true): static
    {
        $this->useStreaming = $useStreaming;
        return $this;
    }

    /**
     * Get whether to use streaming import.
     */
    public function getUseStreaming(): ?bool
    {
        return $this->evaluate($this->useStreaming);
    }

    /**
     * Set the file size threshold for auto-enabling streaming.
     */
    public function streamingThreshold(int | Closure $threshold): static
    {
        $this->streamingThreshold = $threshold;
        return $this;
    }

    /**
     * Get the file size threshold for auto-enabling streaming.
     */
    public function getStreamingThreshold(): int
    {
        return $this->evaluate($this->streamingThreshold);
    }

    /**
     * Determine if streaming should be used based on file size and configuration.
     */
    protected function shouldUseStreaming(TemporaryUploadedFile $file): bool
    {
        $useStreaming = $this->getUseStreaming();

        // If explicitly set, use that
        if ($useStreaming !== null) {
            return $useStreaming;
        }

        // Use streaming by default for better memory efficiency and reliability
        // Only use non-streaming for very small test files
        try {
            $totalRows = $this->getExcelRowCount($file, 0, 0);

            // Use streaming for files with more than 10 rows (covers almost all real use cases)
            if ($totalRows > 10) {
                return true;
            }
        } catch (\Exception $e) {
            // If we can't determine row count, better to use streaming for safety
            return true;
        }

        // Fallback: use file size threshold for very small files
        $fileSize = filesize($file->getRealPath());
        return $fileSize > $this->getStreamingThreshold();
    }

    /**
     * Set basic column mapping as fallback.
     */
    protected function setBasicColumnMapping(Forms\Set $set, ImportAction | ImportTableAction $action): void
    {
        $set('columnMap', []);
        $set('availableSheets', []);
        $set('activeSheet', null);
    }

    /**
     * Get the manual column mapping schema.
     */
    protected function getManualColumnMappingSchema(ImportAction | ImportTableAction $action): array
    {
        return array_map(
            fn(ImportColumn $column): Forms\Components\TextInput => Forms\Components\TextInput::make($column->getName())
                ->label($column->getLabel())
                ->placeholder('Enter column name from Excel file (e.g., "Name", "Email")')
                ->helperText('Type the exact column header from your Excel file'),
            $action->getImporter()::getColumns(),
        );
    }

    /**
     * Get Excel headers only (first row) - memory efficient.
     */
    protected function getExcelHeaders(TemporaryUploadedFile $file, int $headerOffset = 0): array
    {
        $path = $file->getRealPath();
        if (!file_exists($path)) {
            return [];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $headerRowNumber = $headerOffset + 1;

            // Only read the header row - extremely restrictive filter
            $reader->setReadFilter(new class($headerRowNumber) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function __construct(private int $headerRowNumber) {}

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    // Only read the specific header row
                    return $row === $this->headerRowNumber;
                }
            });

            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();

            // Extract headers quickly
            $headers = [];
            $row = $worksheet->getRowIterator($headerRowNumber, $headerRowNumber)->current();
            if ($row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($value !== null) {
                        $headers[] = (string) $value;
                    } else {
                        // Stop at first empty cell to avoid reading too far
                        break;
                    }
                }
            }

            // Immediate cleanup
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);

            return $headers;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get Excel sheet names - memory efficient.
     */
    protected function getExcelSheetNames(TemporaryUploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (!file_exists($path)) {
            return [];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Only read first cell of first row to minimize memory usage
            $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    // Only read A1 cell from each sheet
                    return $columnAddress === 'A1' && $row === 1;
                }
            });

            $spreadsheet = $reader->load($path);

            // Extract sheet names quickly
            $sheetNames = [];
            $index = 0;
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheetNames[$index] = $sheet->getTitle();
                $index++;
            }

            // Immediate cleanup
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            return $sheetNames;
        } catch (\Throwable $e) {
            return [];
        }
    }
}

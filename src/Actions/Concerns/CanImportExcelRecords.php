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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
use Illuminate\Filesystem\AwsS3V3Adapter;

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

    /**
     * The disk to store uploaded files on
     */
    protected string | Closure | null $disk = null;

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
                ->when(
                    filled($action->getDisk()),
                    fn(FileUpload $component) => $component->disk($action->getDisk()),
                )
                ->afterStateUpdated(function (FileUpload $component, Component $livewire, Forms\Set $set, ?TemporaryUploadedFile $state) use ($action) {
                    if (! $state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    // Increase execution time and memory for file processing
                    $this->increaseResourceLimits();

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
            try {
                \Illuminate\Support\Facades\Log::info('Import action started', [
                    'file' => $data['file']?->getClientOriginalName(),
                    'data_keys' => array_keys($data),
                ]);

                /** @var TemporaryUploadedFile $excelFile */
                $excelFile = $data['file'];

                if (!$excelFile instanceof TemporaryUploadedFile) {
                    \Illuminate\Support\Facades\Log::error('Invalid file type received', [
                        'file_type' => $excelFile !== null ? get_class($excelFile) : 'null',
                        'file_value' => $excelFile,
                    ]);

                    Notification::make()
                        ->title(__('Invalid file'))
                        ->body(__('Please upload a valid Excel file.'))
                        ->danger()
                        ->send();
                    return;
                }

                $activeSheetIndex = $data['activeSheet'] ?? $action->getActiveSheet() ?? 0;

                // Extract additional form data
                $additionalFormData = $this->extractAdditionalFormData($data);

                \Illuminate\Support\Facades\Log::info('Getting Excel row count...');
                // Use streaming approach to get total row count without loading everything into memory
                $totalRows = $this->getExcelRowCount($excelFile, $activeSheetIndex, $action->getHeaderOffset() ?? 0);
                \Illuminate\Support\Facades\Log::info('Excel row count retrieved', ['totalRows' => $totalRows]);

                $maxRows = $action->getMaxRows() ?? $totalRows;
                if ($maxRows < $totalRows) {
                    \Illuminate\Support\Facades\Log::info('Max rows exceeded', ['maxRows' => $maxRows, 'totalRows' => $totalRows]);

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

                \Illuminate\Support\Facades\Log::info('Storing permanent file...');
                // Store the uploaded file permanently for streaming processing
                $permanentFilePath = $this->storePermanentFile($excelFile);
                \Illuminate\Support\Facades\Log::info('Permanent file stored', ['path' => $permanentFilePath]);

                \Illuminate\Support\Facades\Log::info('Creating import record...');
                $import = app(Import::class);
                if ($user) {
                    $import->user()->associate($user);
                }
                $import->file_name = $excelFile->getClientOriginalName();
                $import->file_path = $permanentFilePath;
                $import->importer = $action->getImporter();
                $import->total_rows = $totalRows;
                $import->save();
                \Illuminate\Support\Facades\Log::info('Import record created', ['import_id' => $import->id]);

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
                \Illuminate\Support\Facades\Log::info('Column mapping', ['columnMap' => $columnMap]);

                // Determine if we should use streaming import
                $useStreaming = $this->shouldUseStreaming($excelFile);
                \Illuminate\Support\Facades\Log::info('Import method determined', ['useStreaming' => $useStreaming]);

                if ($useStreaming) {
                    \Illuminate\Support\Facades\Log::info('Creating streaming import chunks...');
                    // Create streaming import chunks based on row ranges instead of loading data
                    $chunkSize = $action->getChunkSize();
                    $headerOffset = $action->getHeaderOffset() ?? 0;
                    $startDataRow = $headerOffset + 2; // Header offset + 1 for header row + 1 for first data row
                    $endDataRow = $headerOffset + 1 + $totalRows;

                    $importChunks = collect();
                    for ($currentRow = $startDataRow; $currentRow <= $endDataRow; $currentRow += $chunkSize) {
                        $endChunkRow = min($currentRow + $chunkSize - 1, $endDataRow);

                        $job = app($action->getJob(), [
                            'importId' => $importId,
                            'rows' => null,
                            'startRow' => $currentRow,
                            'endRow' => $endChunkRow,
                            'columnMap' => $columnMap,
                            'options' => $options,
                        ]);

                        $importChunks->push($job);
                    }
                    \Illuminate\Support\Facades\Log::info('Streaming chunks created', ['count' => $importChunks->count()]);
                } else {
                    \Illuminate\Support\Facades\Log::info('Using traditional import method...');
                    // Fall back to original approach for smaller files
                    try {
                        $spreadsheet = $this->getUploadedFileSpreadsheet($excelFile);
                        if (! $spreadsheet) {
                            \Illuminate\Support\Facades\Log::error('Failed to get uploaded file spreadsheet');

                            Notification::make()
                                ->title(__('Error reading file'))
                                ->body(__('Unable to read the uploaded Excel file.'))
                                ->danger()
                                ->send();
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

                        \Illuminate\Support\Facades\Log::info('Traditional import data loaded', ['row_count' => count($rows)]);

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

                        \Illuminate\Support\Facades\Log::info('Traditional chunks created', ['count' => $importChunks->count()]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Traditional import failed, falling back to streaming', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

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
                        \Illuminate\Support\Facades\Log::info('Fallback streaming chunks created', ['count' => $importChunks->count()]);
                    }
                }

                \Illuminate\Support\Facades\Log::info('Getting importer instance...');
                // Get importer with proper parameters
                $importer = $import->getImporter(
                    columnMap: $columnMap,
                    options: $options
                );
                \Illuminate\Support\Facades\Log::info('Importer created', ['importer_class' => get_class($importer)]);

                \Illuminate\Support\Facades\Log::info('Dispatching ImportStarted event...');
                event(new ImportStarted($import, $columnMap, $options));

                \Illuminate\Support\Facades\Log::info('Creating job batch...', [
                    'chunk_count' => $importChunks->count(),
                    'job_queue' => $importer->getJobQueue(),
                    'job_connection' => $importer->getJobConnection(),
                ]);

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
                        \Illuminate\Support\Facades\Log::info('Job batch finally callback triggered', ['import_id' => $importId]);

                        // Retrieve fresh import from database in the callback to avoid serialization issues
                        $import = Import::query()->find($importId);

                        if (! $import) {
                            \Illuminate\Support\Facades\Log::error('Import record not found in finally callback', ['import_id' => $importId]);
                            return;
                        }

                        $import->touch('completed_at');

                        // Clean up the temporary file after import is complete
                        try {
                            if (str_starts_with($permanentFilePath, 's3://')) {
                                // For S3 files, we don't need to clean up since we return the S3 path directly
                                // The file will remain in S3 for future reference
                                \Illuminate\Support\Facades\Log::info('S3 file cleanup skipped - file remains in S3', ['path' => $permanentFilePath]);
                            } else {
                                // For local temporary files, clean them up
                                if (file_exists($permanentFilePath)) {
                                    @unlink($permanentFilePath);
                                    \Illuminate\Support\Facades\Log::info('Local temporary file cleaned up', ['path' => $permanentFilePath]);
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('Failed to cleanup temporary file', [
                                'path' => $permanentFilePath,
                                'error' => $e->getMessage()
                            ]);
                        }

                        event(new ImportCompleted($import, $columnMap, $options));

                        // Check if user relation can be safely accessed
                        $user = $import->user;
                        if (! $user instanceof Authenticatable) {
                            \Illuminate\Support\Facades\Log::info('No user associated with import, skipping notification');
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

                \Illuminate\Support\Facades\Log::info('Job batch dispatched successfully');

                if (
                    (filled($jobConnection) && ($jobConnection !== 'sync')) ||
                    (blank($jobConnection) && (config('queue.default') !== 'sync'))
                ) {
                    \Illuminate\Support\Facades\Log::info('Sending async import started notification');

                    Notification::make()
                        ->title($action->getSuccessNotificationTitle())
                        ->body(trans_choice('filament-actions::import.notifications.started.body', $import->total_rows, [
                            'count' => Number::format($import->total_rows),
                        ]))
                        ->success()
                        ->send();
                } else {
                    \Illuminate\Support\Facades\Log::info('Using sync queue - notification will be sent after completion');
                }

                \Illuminate\Support\Facades\Log::info('Import action completed successfully');
            } catch (ReaderException $e) {
                \Illuminate\Support\Facades\Log::error('Excel reader exception during import', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                Notification::make()
                    ->title(__('Error processing Excel file'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Unexpected exception during import', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);

                Notification::make()
                    ->title(__('Import failed'))
                    ->body(__('An unexpected error occurred: ') . $e->getMessage())
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
     * @return resource | false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        /** @phpstan-ignore-next-line */
        $fileDisk = invade($file)->disk;

        $filePath = $file->getRealPath();

        if (config("filesystems.disks.{$fileDisk}.driver") !== 's3') {
            $resource = $file->readStream();
        } else {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($fileDisk);

            // For S3 files, we'll use a different approach without stream wrappers
            // since they can be problematic across different adapter versions
            $fileS3Path = (string) str('s3://' . config("filesystems.disks.{$fileDisk}.bucket") . '/' . $filePath)->replace('\\', '/');

            $resource = fopen($fileS3Path, mode: 'r', context: stream_context_create([
                's3' => [
                    'seekable' => true,
                ],
            ]));
        }

        return $resource;
    }

    /**
     * Get the file path for the uploaded file (S3-aware).
     * This method handles both local files and S3 files.
     */
    protected function getUploadedFilePath(TemporaryUploadedFile $file): string
    {
        /** @phpstan-ignore-next-line */
        $fileDisk = invade($file)->disk;
        $filePath = $file->getRealPath();

        // For S3 files, we need to handle them differently
        if (config("filesystems.disks.{$fileDisk}.driver") === 's3') {
            // For S3 files, return the S3 path directly
            // We handle S3 Excel files by downloading them temporarily in getExcelHeadersFromS3
            return (string) str('s3://' . config("filesystems.disks.{$fileDisk}.bucket") . '/' . $filePath)->replace('\\', '/');
        }

        return $filePath;
    }

    /**
     * Get the uploaded file spreadsheet (legacy method - kept for compatibility).
     * NOTE: This method is now primarily used for backward compatibility.
     * For header reading, use getExcelHeaders() instead for better memory efficiency.
     */
    protected function getUploadedFileSpreadsheet(TemporaryUploadedFile $file): ?Spreadsheet
    {
        try {
            // For S3 files, download temporarily since PhpSpreadsheet can't read S3 paths directly
            if ($this->isS3File($file)) {
                $tempFile = null;
                try {
                    // Create a temporary file
                    $tempFile = tempnam(sys_get_temp_dir(), 'excel_spreadsheet_');
                    if (!$tempFile) {
                        \Illuminate\Support\Facades\Log::error('Failed to create temporary file for S3 spreadsheet');
                        return null;
                    }

                    // Download the S3 file to temporary location
                    /** @phpstan-ignore-next-line */
                    $fileDisk = invade($file)->disk;
                    $filePath = $file->getRealPath();

                    $disk = Storage::disk($fileDisk);
                    $content = $disk->get($filePath);

                    if ($content === null) {
                        \Illuminate\Support\Facades\Log::error('Failed to download S3 file content');
                        return null;
                    }

                    file_put_contents($tempFile, $content);

                    // Now read from temporary file
                    $reader = $this->createReaderForFile($file, $tempFile);
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

                    $spreadsheet = $reader->load($tempFile);

                    // Clean up temporary file
                    @unlink($tempFile);

                    return $spreadsheet;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Error reading S3 spreadsheet', [
                        'error' => $e->getMessage(),
                        'file' => $file->getClientOriginalName()
                    ]);

                    // Clean up temporary file if it exists
                    if ($tempFile && file_exists($tempFile)) {
                        @unlink($tempFile);
                    }

                    return null;
                }
            }

            // For local files, use direct reading
            $filePath = $this->getUploadedFilePath($file);

            $reader = IOFactory::createReaderForFile($filePath);
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

            return $reader->load($filePath);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error reading spreadsheet', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'is_s3' => $this->isS3File($file)
            ]);

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
     * Set file validation rules (alias for compatibility with base trait).
     *
     * @param  string | array<mixed> | Closure  $rules
     */
    public function fileRules(string | array | Closure $rules): static
    {
        $this->fileValidationRules = [
            ...$this->fileValidationRules,
            $rules,
        ];

        return $this;
    }

    /**
     * Set the disk for file uploads (for compatibility with FileUpload component).
     */
    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    /**
     * Get the disk for file uploads.
     */
    public function getDisk(): ?string
    {
        return $this->evaluate($this->disk);
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
        if ($this->isS3File($file)) {
            // For S3 files, return the S3 path directly since we can read from it
            return $this->getUploadedFilePath($file);
        }

        // For local files, copy to a permanent location
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
        try {
            // For S3 files, use the same temporary download approach as header reading
            if ($this->isS3File($file)) {
                return $this->getExcelRowCountFromS3($file, $activeSheetIndex, $headerOffset);
            }

            $filePath = $this->getUploadedFilePath($file);

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Method 1: Try to get row count without loading data by reading structure only
            try {
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getSheet($activeSheetIndex);
                $highestRow = $worksheet->getHighestDataRow();

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet);

                if ($highestRow > $headerOffset + 1) {
                    return $highestRow - ($headerOffset + 1);
                }

                // If we get here, the file has very few rows or is empty
                return max(0, $highestRow - ($headerOffset + 1));
            } catch (\Exception $e) {
                // Continue to next method
            }

            // Method 2: If Method 1 fails, try reading with minimal filter
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell($columnAddress, $row, $worksheetName = ''): bool
                    {
                        return $row === 1 || $row % 10 === 0;
                    }
                });

                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getSheet($activeSheetIndex);
                $highestRow = $worksheet->getHighestDataRow();

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet);

                if ($highestRow > $headerOffset + 1) {
                    return $highestRow - ($headerOffset + 1);
                }

                // If we get here, the file has very few rows or is empty
                return max(0, $highestRow - ($headerOffset + 1));
            } catch (\Exception $e) {
                // Continue to next method
            }

            // Method 3: Conservative fallback based on file size - but more accurate
            $fileSize = null;

            // Get file size appropriately for local vs S3 files
            try {
                if ($this->isS3File($file)) {
                    /** @phpstan-ignore-next-line */
                    $fileDisk = invade($file)->disk;
                    $fileSize = Storage::disk($fileDisk)->size($file->getRealPath());
                } else {
                    $fileSize = filesize($filePath);
                }
            } catch (\Exception $e) {
                // If we can't get file size, use minimal fallback
                return 10;
            }

            // For very small files, assume minimal rows
            if ($fileSize < 1024) { // Less than 1KB
                return 1;
            } else if ($fileSize < 10240) { // Less than 10KB  
                return 10;
            } else if ($fileSize < 102400) { // Less than 100KB
                return 100;
            } else {
                // For larger files, use rough estimation
                return intval($fileSize / 1000); // More conservative than before
            }
        } catch (\Exception $e) {
            // Final fallback - return minimal count for safety
            return 10;
        }
    }

    /**
     * Get Excel row count from S3 file by downloading temporarily.
     */
    protected function getExcelRowCountFromS3(TemporaryUploadedFile $file, int $activeSheetIndex, int $headerOffset): int
    {
        $tempFile = null;

        try {
            // Create a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_count_');
            if (!$tempFile) {
                return 10; // Conservative fallback
            }

            // Download the S3 file to temporary location
            /** @phpstan-ignore-next-line */
            $fileDisk = invade($file)->disk;
            $filePath = $file->getRealPath();

            $disk = Storage::disk($fileDisk);
            $content = $disk->get($filePath);

            if ($content === null) {
                return 10; // Conservative fallback
            }

            file_put_contents($tempFile, $content);

            // Now count rows from temporary file
            $reader = $this->createReaderForFile($file, $tempFile);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($tempFile);
            $worksheet = $spreadsheet->getSheet($activeSheetIndex);
            $highestRow = $worksheet->getHighestDataRow();

            // Cleanup
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);

            // Return actual data row count
            return max(0, $highestRow - ($headerOffset + 1));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to count Excel rows from S3', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return 10; // Conservative fallback
        } finally {
            // Always clean up temporary file
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
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
        try {
            $fileSize = null;

            // Get file size appropriately for local vs S3 files
            if ($this->isS3File($file)) {
                /** @phpstan-ignore-next-line */
                $fileDisk = invade($file)->disk;
                $fileSize = Storage::disk($fileDisk)->size($file->getRealPath());
            } else {
                $fileSize = filesize($file->getRealPath());
            }

            return $fileSize > $this->getStreamingThreshold();
        } catch (\Exception $e) {
            // If we can't get file size, use streaming for safety
            return true;
        }
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
        try {
            // For S3 files, download to temporary file for reliable reading
            // PhpSpreadsheet has issues reading xlsx (zip) files through S3 streams
            if ($this->isS3File($file)) {
                return $this->getExcelHeadersFromS3($file, $headerOffset);
            }

            // For local files, use direct reading
            $filePath = $this->getUploadedFilePath($file);
            $reader = $this->createReaderForFile($file, $filePath);
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

            $spreadsheet = $reader->load($filePath);
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
            // Log the error for debugging
            \Illuminate\Support\Facades\Log::warning('Failed to read Excel headers', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'is_s3' => $this->isS3File($file),
                'file_extension' => pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION),
            ]);

            return [];
        }
    }

    /**
     * Get Excel headers from S3 file by downloading temporarily.
     * This avoids issues with PhpSpreadsheet reading xlsx files through S3 streams.
     */
    protected function getExcelHeadersFromS3(TemporaryUploadedFile $file, int $headerOffset = 0): array
    {
        $tempFile = null;

        try {
            // Create a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_headers_');
            if (!$tempFile) {
                throw new \Exception('Failed to create temporary file');
            }

            // Download the S3 file to temporary location
            /** @phpstan-ignore-next-line */
            $fileDisk = invade($file)->disk;
            $filePath = $file->getRealPath();

            $disk = Storage::disk($fileDisk);
            $content = $disk->get($filePath);

            if ($content === null) {
                throw new \Exception('Failed to download S3 file');
            }

            file_put_contents($tempFile, $content);

            // Now read headers from temporary file
            $reader = $this->createReaderForFile($file, $tempFile);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $headerRowNumber = $headerOffset + 1;

            // Only read the header row
            $reader->setReadFilter(new class($headerRowNumber) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function __construct(private int $headerRowNumber) {}

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row === $this->headerRowNumber;
                }
            });

            $spreadsheet = $reader->load($tempFile);
            $worksheet = $spreadsheet->getActiveSheet();

            // Extract headers
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
                        break;
                    }
                }
            }

            // Cleanup
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);

            return $headers;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to read Excel headers from S3', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return [];
        } finally {
            // Always clean up temporary file
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Create appropriate PhpSpreadsheet reader based on file extension.
     * This is needed for S3 files where IOFactory::createReaderForFile fails.
     */
    protected function createReaderForFile(TemporaryUploadedFile $file, string $filePath): \PhpOffice\PhpSpreadsheet\Reader\IReader
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx', 'xlsm', 'xltx', 'xltm' => new \PhpOffice\PhpSpreadsheet\Reader\Xlsx(),
            'xls', 'xlt' => new \PhpOffice\PhpSpreadsheet\Reader\Xls(),
            'ods', 'ots' => new \PhpOffice\PhpSpreadsheet\Reader\Ods(),
            'csv' => new \PhpOffice\PhpSpreadsheet\Reader\Csv(),
            default => IOFactory::createReaderForFile($filePath), // Fallback for local files
        };
    }

    /**
     * Ensure S3 stream wrapper is registered for the uploaded file.
     * Note: We now handle S3 Excel files by downloading them temporarily,
     * so stream wrapper registration is not strictly necessary for header reading.
     */
    protected function ensureS3StreamWrapper(TemporaryUploadedFile $file): void
    {
        // This method is kept for compatibility but is no longer needed
        // since we handle S3 Excel files by downloading them temporarily
        // in getExcelHeadersFromS3() method.
    }

    /**
     * Get Excel sheet names - memory efficient.
     */
    protected function getExcelSheetNames(TemporaryUploadedFile $file): array
    {
        try {
            // For S3 files, use temporary download approach
            if ($this->isS3File($file)) {
                $tempFile = null;
                try {
                    $tempFile = tempnam(sys_get_temp_dir(), 'excel_sheets_');
                    if (!$tempFile) {
                        return [];
                    }

                    /** @phpstan-ignore-next-line */
                    $fileDisk = invade($file)->disk;
                    $content = Storage::disk($fileDisk)->get($file->getRealPath());

                    if ($content === null) {
                        return [];
                    }

                    file_put_contents($tempFile, $content);
                    $filePath = $tempFile;
                } catch (\Exception $e) {
                    return [];
                }
            } else {
                $filePath = $this->getUploadedFilePath($file);
            }

            $reader = IOFactory::createReaderForFile($filePath);
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

            $spreadsheet = $reader->load($filePath);

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
        } finally {
            if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Check if the uploaded file is on S3.
     */
    protected function isS3File(TemporaryUploadedFile $file): bool
    {
        /** @phpstan-ignore-next-line */
        $fileDisk = invade($file)->disk;
        return config("filesystems.disks.{$fileDisk}.driver") === 's3';
    }

    /**
     * Increase resource limits for file processing to prevent timeouts.
     */
    protected function increaseResourceLimits(): void
    {
        // Increase execution time for file upload and processing
        if (function_exists('set_time_limit')) {
            set_time_limit(300); // 5 minutes
        }

        // Increase memory limit if possible
        $currentMemoryLimit = ini_get('memory_limit');
        if ($currentMemoryLimit !== '-1') {
            $currentBytes = $this->convertToBytes($currentMemoryLimit);
            $recommendedBytes = 512 * 1024 * 1024; // 512MB

            if ($currentBytes < $recommendedBytes) {
                ini_set('memory_limit', '512M');
            }
        }
    }

    /**
     * Convert memory limit string to bytes.
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }
}

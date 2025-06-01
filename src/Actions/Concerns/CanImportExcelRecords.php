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
    protected int | Closure | null $headerRow = null;
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
                ->label(__('filament-excel-import::import.modal.form.file.label'))
                ->placeholder(__('filament-excel-import::import.modal.form.file.placeholder'))
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
                        $spreadsheet = $this->getUploadedFileSpreadsheet($state);
                        if (! $spreadsheet) {
                            return;
                        }
                        $worksheet = $this->getActiveWorksheet($spreadsheet);
                        $headerRow = $action->getHeaderRow() ?? 1;
                        // Get header row from the worksheet
                        $excelColumns = [];
                        foreach ($worksheet->getRowIterator($headerRow, $headerRow) as $row) {
                            $cellIterator = $row->getCellIterator();
                            $cellIterator->setIterateOnlyExistingCells(false);
                            foreach ($cellIterator as $cell) {
                                if ($cell->getValue() !== null) {
                                    $excelColumns[] = (string) $cell->getValue();
                                }
                            }
                        }
                        $lowercaseExcelColumnValues = array_map(Str::lower(...), $excelColumns);
                        $lowercaseExcelColumnKeys = array_combine(
                            $lowercaseExcelColumnValues,
                            $excelColumns,
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

                        // Set available sheets for selection
                        $sheetNames = [];
                        $index = 0;
                        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                            $sheetNames[$index] = $sheet->getTitle();
                            $index++;
                        }
                        $set('availableSheets', $sheetNames);
                        $set('activeSheet', $action->getActiveSheet() ?? 0);
                    } catch (ReaderException $e) {
                        Notification::make()
                            ->title(__('Error reading Excel file'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        $component->state([]);
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
                        $spreadsheet = $this->getUploadedFileSpreadsheet($file);
                        if (! $spreadsheet) {
                            return;
                        }
                        $worksheet = $spreadsheet->getSheet((int) $state);
                        $headerRow = $action->getHeaderRow() ?? 1;
                        // Get header row from the worksheet
                        $excelColumns = [];
                        foreach ($worksheet->getRowIterator($headerRow, $headerRow) as $row) {
                            $cellIterator = $row->getCellIterator();
                            $cellIterator->setIterateOnlyExistingCells(false);
                            foreach ($cellIterator as $cell) {
                                if ($cell->getValue() !== null) {
                                    $excelColumns[] = (string) $cell->getValue();
                                }
                            }
                        }

                        // Reset column map to ensure clean state
                        $set('columnMap', []);

                        $lowercaseExcelColumnValues = array_map(Str::lower(...), $excelColumns);
                        $lowercaseExcelColumnKeys = array_combine(
                            $lowercaseExcelColumnValues,
                            $excelColumns,
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
                    } catch (ReaderException $e) {
                        Notification::make()
                            ->title(__('Error reading Excel sheet'))
                            ->body($e->getMessage())
                            ->danger()
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

                    // Get the selected sheet index
                    $activeSheetIndex = $get('activeSheet') ?? $action->getActiveSheet() ?? 0;

                    try {
                        $spreadsheet = $this->getUploadedFileSpreadsheet($file);
                        if (! $spreadsheet) {
                            return [];
                        }

                        $worksheet = $spreadsheet->getSheet((int) $activeSheetIndex);
                        $headerRow = $action->getHeaderRow() ?? 1;
                        // Get header row from the worksheet
                        $excelColumns = [];
                        foreach ($worksheet->getRowIterator($headerRow, $headerRow) as $row) {
                            $cellIterator = $row->getCellIterator('A', $worksheet->getHighestDataColumn());
                            $cellIterator->setIterateOnlyExistingCells(false);
                            foreach ($cellIterator as $cell) {
                                if ($cell->getValue() !== null) {
                                    $excelColumns[] = (string) $cell->getValue();
                                }
                            }
                        }
                        $excelColumnOptions = array_combine($excelColumns, $excelColumns);

                        return array_map(
                            fn(ImportColumn $column): Select => $column->getSelect()->options($excelColumnOptions),
                            $action->getImporter()::getColumns(),
                        );
                    } catch (ReaderException $e) {
                        return [];
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
                $spreadsheet = $this->getUploadedFileSpreadsheet($excelFile);
                if (! $spreadsheet) {
                    return;
                }

                $worksheet = $spreadsheet->getSheet((int) $activeSheetIndex);
                $headerRow = $action->getHeaderRow() ?? 1;
                // Get all data from the worksheet
                $rows = [];
                $highestRow = $worksheet->getHighestDataRow();
                $highestColumn = $worksheet->getHighestDataColumn();
                // Get header row
                $headers = [];
                foreach ($worksheet->getRowIterator($headerRow, $headerRow) as $row) {
                    $cellIterator = $row->getCellIterator('A', $highestColumn);
                    $cellIterator->setIterateOnlyExistingCells(false);
                    foreach ($cellIterator as $cell) {
                        $headers[] = $cell->getValue();
                    }
                }
                // Get data rows
                for ($rowIndex = $headerRow + 1; $rowIndex <= $highestRow; $rowIndex++) {
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
                $totalRows = count($rows);
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

                $import = app(Import::class);
                if ($user) {
                    $import->user()->associate($user);
                }
                $import->file_name = $excelFile->getClientOriginalName();
                $import->file_path = $excelFile->getRealPath();
                $import->importer = $action->getImporter();
                $import->total_rows = $totalRows;
                $import->save();

                // Store the import ID for later use
                $importId = $import->id;

                // Convert options to serializable format and include additional form data
                $options = array_merge(
                    $action->getOptions(),
                    Arr::except($data, ['file', 'columnMap']),
                    ['additional_form_data' => $additionalFormData]
                );

                // Unset non-serializable relations to prevent issues
                $import->unsetRelation('user');

                $columnMap = $data['columnMap'];

                // Create import chunks with import ID instead of full model
                $importChunks = collect($rows)->chunk($action->getChunkSize())
                    ->map(fn($chunk) => app($action->getJob(), [
                        'importId' => $importId,
                        'rows' => base64_encode(serialize($chunk->all())),
                        'columnMap' => $columnMap,
                        'options' => $options,
                    ]));

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
                    ->finally(function () use ($importId, $columnMap, $options, $jobConnection) {
                        // Retrieve fresh import from database in the callback to avoid serialization issues
                        $import = Import::query()->find($importId);

                        if (! $import) {
                            return;
                        }

                        $import->touch('completed_at');

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
                ->label(__('filament-excel-import::import.actions.example_template.label'))
                ->link()
                ->action(function (): StreamedResponse {
                    $columns = $this->getImporter()::getColumns();
                    // Create a new Spreadsheet
                    $spreadsheet = new Spreadsheet();
                    $worksheet = $spreadsheet->getActiveSheet();
                    // Add headers
                    $columnIndex = 1;
                    foreach ($columns as $column) {
                        $worksheet->setCellValueByColumnAndRow($columnIndex, 1, $column->getExampleHeader());
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
                            $worksheet->setCellValueByColumnAndRow(
                                $columnIndex,
                                $rowIndex + 2,
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
     * Get the uploaded file spreadsheet.
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

            return $reader->load($path);
        } catch (ReaderException $e) {
            Notification::make()
                ->title(__('Error reading Excel file'))
                ->body($e->getMessage())
                ->danger()
                ->send();

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
    public function getHeaderRow(): ?int
    {
        return $this->evaluate($this->headerRow);
    }

    /**
     * Set the header row number (1-based).
     *
     * @param  int | Closure | null  $row
     */
    public function headerRow(int | Closure | null $row): static
    {
        $this->headerRow = $row;

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
                ->collapsible()
                ->collapsed(false)
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
}

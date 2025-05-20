<?php

namespace HayderHatem\FilamentExcelImport\Actions\Imports\Jobs;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use HayderHatem\FilamentExcelImport\Traits\HasImportProgressNotifications;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportExcel implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HasImportProgressNotifications;

    /**
     * @param  int  $importId The ID of the Import model
     * @param  string  $rows Base64-encoded serialized array of rows
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $importId,
        public string $rows,
        public array $columnMap,
        public array $options = [],
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Retrieve the import model by ID
        $import = Import::findOrFail($this->importId);

        $rows = unserialize(base64_decode($this->rows));

        $importedRowsCount = 0;
        $failedRowsCount = 0;

        $importer = $import->getImporter(
            columnMap: $this->columnMap,
            options: $this->options,
        );

        $user = $import->user;

        if (! $user instanceof Authenticatable) {
            return;
        }

        $processedRows = [];

        foreach ($rows as $row) {
            $processedRow = [];

            foreach ($this->columnMap as $importerColumn => $excelColumn) {
                if (blank($excelColumn)) {
                    continue;
                }

                $processedRow[$importerColumn] = $row[$excelColumn] ?? null;
            }

            $processedRows[] = $processedRow;
        }

        // The transform method doesn't exist in the importer, so we'll use the processed rows directly

        foreach ($processedRows as $processedRow) {
            try {
                DB::transaction(fn() => $importer->import(
                    $processedRow,
                    $this->columnMap,
                    $this->options,
                ));

                $importedRowsCount++;
            } catch (Throwable $exception) {
                $failedRowsCount++;

                try {
                    $import->failedRows()->create([
                        'data' => array_map(
                            fn($value) => is_null($value) ? null : (string) $value,
                            $processedRow,
                        ),
                        'validation_errors' => [],
                        'import_id' => $import->getKey(),
                        'error' => $exception->getMessage(),
                    ]);
                } catch (Throwable $e) {
                    // If there's an issue with validation_errors column, try without it
                    try {
                        $import->failedRows()->create([
                            'data' => array_map(
                                fn($value) => is_null($value) ? null : (string) $value,
                                $processedRow,
                            ),
                            'import_id' => $import->getKey(),
                            'error' => $exception->getMessage(),
                        ]);
                    } catch (Throwable $e2) {
                        // If there's also an issue with error column, try with minimal data
                        try {
                            $import->failedRows()->create([
                                'data' => array_map(
                                    fn($value) => is_null($value) ? null : (string) $value,
                                    $processedRow,
                                ),
                                'import_id' => $import->getKey(),
                            ]);
                        } catch (Throwable $e3) {
                            // Log the error but continue processing
                            \Illuminate\Support\Facades\Log::error('Failed to record import error: ' . $e3->getMessage());
                        }
                    }
                }
            }
        }

        // Try to update counters, handling missing columns gracefully
        try {
            $import->increment('processed_rows', count($processedRows));
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update processed_rows: ' . $e->getMessage());
        }

        try {
            $import->increment('imported_rows', $importedRowsCount);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update imported_rows: ' . $e->getMessage());
        }

        try {
            $import->increment('failed_rows', $failedRowsCount);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update failed_rows: ' . $e->getMessage());
        }

        // Notify only if we can safely do so
        try {
            $this->notifyImportProgress($import, $user);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send import notification: ' . $e->getMessage());
        }
    }
}

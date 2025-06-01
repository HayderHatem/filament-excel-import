<?php

namespace HayderHatem\FilamentExcelImport\Actions\Imports\Jobs;

use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Traits\HasImportProgressNotifications;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
                    $validationError = null;

                    // Extract validation errors if it's a ValidationException
                    if ($exception instanceof ValidationException) {
                        $errors = $exception->errors();
                        $validationError = collect($errors)
                            ->map(function ($fieldErrors, $field) {
                                return $field . ': ' . implode(', ', $fieldErrors);
                            })
                            ->implode('; ');
                    } else {
                        // For non-validation errors, use the exception message
                        $validationError = $exception->getMessage();
                    }

                    $import->failedRows()->create([
                        'data' => array_map(
                            fn($value) => is_null($value) ? null : (string) $value,
                            $processedRow,
                        ),
                        'validation_error' => $validationError,
                        'import_id' => $import->getKey(),
                    ]);
                } catch (Throwable $e) {
                    // Log the error but continue processing
                    Log::error('Failed to record import error: ' . $e->getMessage(), [
                        'import_id' => $import->getKey(),
                        'row_data' => $processedRow,
                        'original_error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        // Try to update counters, handling missing columns gracefully
        try {
            $import->increment('processed_rows', count($processedRows));
        } catch (Throwable $e) {
            Log::error('Failed to update processed_rows: ' . $e->getMessage());
        }

        try {
            $import->increment('imported_rows', $importedRowsCount);
        } catch (Throwable $e) {
            Log::error('Failed to update imported_rows: ' . $e->getMessage());
        }

        try {
            $import->increment('failed_rows', $failedRowsCount);
        } catch (Throwable $e) {
            Log::error('Failed to update failed_rows: ' . $e->getMessage());
        }

        // Notify only if we can safely do so
        try {
            $this->notifyImportProgress($import, $user);
        } catch (Throwable $e) {
            Log::error('Failed to send import notification: ' . $e->getMessage());
        }
    }
}

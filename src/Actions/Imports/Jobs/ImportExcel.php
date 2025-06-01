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
use Illuminate\Database\QueryException;
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

        // Set additional form data if the importer supports it
        if (method_exists($importer, 'setAdditionalFormData') && isset($this->options['additional_form_data'])) {
            $importer->setAdditionalFormData($this->options['additional_form_data']);
        }

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
                        // For non-validation errors, parse them to user-friendly messages
                        $validationError = $this->parseErrorMessage($exception);
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

    /**
     * Parse error messages to user-friendly format
     */
    protected function parseErrorMessage(Throwable $exception): string
    {
        // Handle database query exceptions
        if ($exception instanceof QueryException) {
            $message = $exception->getMessage();

            // Parse "not null violation" errors
            if (preg_match('/null value in column "([^"]+)".*violates not-null constraint/i', $message, $matches)) {
                $field = $matches[1];
                $fieldName = ucfirst(str_replace('_', ' ', $field));
                return __('filament-excel-import::import.errors.field_required', ['field' => $fieldName]);
            }

            // Parse unique constraint violations
            if (preg_match('/duplicate key value violates unique constraint.*\(([^)]+)\)/i', $message, $matches)) {
                $field = $matches[1];
                $fieldName = ucfirst(str_replace('_', ' ', $field));
                return __('filament-excel-import::import.errors.field_exists', ['field' => $fieldName]);
            }

            // Parse foreign key constraint violations
            if (preg_match('/violates foreign key constraint.*on table "([^"]+)"/i', $message, $matches)) {
                $table = $matches[1];
                $tableName = str_replace('_', ' ', $table);
                return __('filament-excel-import::import.errors.invalid_reference', ['table' => $tableName]);
            }

            // Parse check constraint violations
            if (preg_match('/violates check constraint "([^"]+)"/i', $message, $matches)) {
                $constraint = $matches[1];
                $constraintName = str_replace('_', ' ', $constraint);
                return __('filament-excel-import::import.errors.check_constraint_failed', ['constraint' => $constraintName]);
            }

            // For other SQL errors, try to extract just the main error message
            if (preg_match('/ERROR:\s*([^(]+)/i', $message, $matches)) {
                return trim($matches[1]);
            }
        }

        // For other exceptions, return a simplified message
        $message = $exception->getMessage();

        // Remove SQL statements from the message
        $message = preg_replace('/\(SQL:.*\)$/s', '', $message);

        // Clean up the message
        $message = trim($message);

        // If message is still too technical, provide a generic error
        if (strlen($message) > 200 || stripos($message, 'SQLSTATE') !== false) {
            return __('filament-excel-import::import.errors.generic_validation');
        }

        return $message;
    }
}

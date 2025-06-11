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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\AwsS3V3Adapter;

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
     * @param  string|null  $rows Base64-encoded serialized array of rows (for regular import)
     * @param  int|null  $startRow Starting row number to process (for streaming import)
     * @param  int|null  $endRow Ending row number to process (for streaming import)
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $importId,
        public ?string $rows = null,
        public ?int $startRow = null,
        public ?int $endRow = null,
        public array $columnMap = [],
        public array $options = [],
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Retrieve the import model by ID
        $import = Import::findOrFail($this->importId);

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

        // Determine if this is a streaming import or regular import
        $isStreamingImport = $this->startRow !== null && $this->endRow !== null;

        try {
            if ($isStreamingImport) {
                // Streaming import: read rows from file
                $rows = $this->readExcelRowsFromFile($import->file_path, $this->startRow, $this->endRow);
            } else {
                // Regular import: deserialize rows
                $rows = unserialize(base64_decode($this->rows));
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
        } catch (Throwable $e) {
            if ($isStreamingImport) {
                Log::error('Failed to read Excel file chunk: ' . $e->getMessage(), [
                    'import_id' => $import->getKey(),
                    'start_row' => $this->startRow,
                    'end_row' => $this->endRow,
                    'file_path' => $import->file_path,
                ]);
            }
            throw $e;
        }

        $processedRowsCount = count($processedRows ?? []);

        // Try to update counters, handling missing columns gracefully
        try {
            $import->increment('processed_rows', $processedRowsCount);
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
     * Check if the file path is an S3 URL
     */
    protected function isS3FilePath(string $filePath): bool
    {
        return str_starts_with($filePath, 's3://');
    }

    /**
     * Ensure S3 stream wrapper is registered for S3 files
     */
    protected function ensureS3StreamWrapper(string $filePath): void
    {
        if ($this->isS3FilePath($filePath)) {
            // Extract bucket and file path from S3 URL
            if (preg_match('/^s3:\/\/([^\/]+)\/(.+)$/', $filePath, $matches)) {
                $bucket = $matches[1];

                // Find the disk configuration for this bucket
                foreach (config('filesystems.disks') as $diskName => $diskConfig) {
                    if (($diskConfig['driver'] ?? '') === 's3' && ($diskConfig['bucket'] ?? '') === $bucket) {
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                        $disk = Storage::disk($diskName);
                        /** @var AwsS3V3Adapter $s3Adapter */
                        $s3Adapter = $disk->getAdapter();

                        // Register S3 stream wrapper - handle different adapter versions
                        try {
                            if (method_exists($s3Adapter, 'getClient')) {
                                $s3Client = $s3Adapter->getClient();
                                $s3Client->registerStreamWrapper();
                            } else {
                                // For newer versions, try using invade to access the client
                                invade($s3Adapter)->client->registerStreamWrapper();
                            }
                        } catch (\Throwable $e) {
                            // If stream wrapper registration fails, we'll try without it
                            \Illuminate\Support\Facades\Log::warning('Failed to register S3 stream wrapper', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                        /** @phpstan-ignore-line */
                        break;
                    }
                }
            }
        }
    }

    /**
     * Check if file exists (works for both local and S3 files)
     */
    protected function fileExists(string $filePath): bool
    {
        if ($this->isS3FilePath($filePath)) {
            $this->ensureS3StreamWrapper($filePath);
            return file_exists($filePath);
        }

        return file_exists($filePath);
    }

    /**
     * Read specific rows from Excel file using streaming approach
     */
    protected function readExcelRowsFromFile(string $filePath, int $startRow, int $endRow): array
    {
        if (!$this->fileExists($filePath)) {
            throw new \Exception("Import file not found: {$filePath}");
        }

        $tempFile = null;

        try {
            // For S3 files, download to temporary file for reliable reading
            if ($this->isS3FilePath($filePath)) {
                $tempFile = $this->downloadS3FileTemporarily($filePath);
                $actualFilePath = $tempFile;
            } else {
                $actualFilePath = $filePath;
            }

            $reader = IOFactory::createReaderForFile($actualFilePath);

            // Apply memory optimizations
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Set a read filter to only read the rows we need
            $reader->setReadFilter(new class($startRow, $endRow, $this->options) implements IReadFilter {
                public function __construct(
                    private int $startRow,
                    private int $endRow,
                    private array $options
                ) {}

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    $headerOffset = $this->options['headerOffset'] ?? 0;
                    $headerRowNumber = $headerOffset + 1;

                    // Read header row and our target rows
                    return $row === $headerRowNumber || ($row >= $this->startRow && $row <= $this->endRow);
                }
            });

            $spreadsheet = $reader->load($actualFilePath);

            // Get the active sheet (assuming sheet index is stored in options)
            $activeSheetIndex = $this->options['activeSheet'] ?? 0;
            $worksheet = $spreadsheet->getSheet($activeSheetIndex);

            // Check if the file has enough rows
            $highestRow = $worksheet->getHighestDataRow();
            $headerOffset = $this->options['headerOffset'] ?? 0;
            $headerRowNumber = $headerOffset + 1;

            // If start row is beyond the highest row, return empty array
            if ($startRow > $highestRow) {
                // Clean up memory
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet, $reader);
                return [];
            }

            // Adjust end row to not exceed the highest row
            $adjustedEndRow = min($endRow, $highestRow);

            // Get headers from row 1
            $headers = [];
            foreach ($worksheet->getRowIterator($headerRowNumber, $headerRowNumber) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $headers[] = $cell->getValue();
                }
            }

            // Get data rows
            $rows = [];
            $highestColumn = $worksheet->getHighestDataColumn();

            for ($rowIndex = $startRow; $rowIndex <= $adjustedEndRow; $rowIndex++) {
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

            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);

            return $rows;
        } catch (ReaderException $e) {
            throw new \Exception('Error reading Excel file: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Handle memory limit errors specifically
            if (strpos($e->getMessage(), 'memory') !== false || strpos($e->getMessage(), 'Memory') !== false) {
                throw new \Exception('File chunk too large to process. The file may be corrupted or contains extremely wide rows.');
            }
            throw $e;
        } finally {
            // Clean up temporary file if it was created
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Download S3 file to temporary location for reliable reading
     */
    protected function downloadS3FileTemporarily(string $s3FilePath): string
    {
        // Extract bucket and key from S3 URL
        if (!preg_match('/^s3:\/\/([^\/]+)\/(.+)$/', $s3FilePath, $matches)) {
            throw new \Exception("Invalid S3 path format: {$s3FilePath}");
        }

        $bucket = $matches[1];
        $key = $matches[2];

        // Find the disk configuration for this bucket
        $diskName = null;
        foreach (config('filesystems.disks') as $name => $diskConfig) {
            if (($diskConfig['driver'] ?? '') === 's3' && ($diskConfig['bucket'] ?? '') === $bucket) {
                $diskName = $name;
                break;
            }
        }

        if (!$diskName) {
            throw new \Exception("No disk configuration found for S3 bucket: {$bucket}");
        }

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'import_excel_');
        if (!$tempFile) {
            throw new \Exception('Failed to create temporary file');
        }

        try {
            // Download file content
            $disk = Storage::disk($diskName);
            $content = $disk->get($key);

            if ($content === null) {
                throw new \Exception("Failed to download S3 file: {$s3FilePath}");
            }

            // Write to temporary file
            file_put_contents($tempFile, $content);

            return $tempFile;
        } catch (\Exception $e) {
            // Clean up on failure
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
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

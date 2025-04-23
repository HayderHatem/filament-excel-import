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
     * @param  Import  $import
     * @param  string  $rows Base64-encoded serialized array of rows
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public Import $import,
        public string $rows,
        public array $columnMap,
        public array $options = [],
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = unserialize(base64_decode($this->rows));

        $importedRowsCount = 0;
        $failedRowsCount = 0;

        $importer = $this->import->getImporter(
            columnMap: $this->columnMap,
            options: $this->options,
        );

        $user = $this->import->user;

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

        $processedRows = $importer->transform(
            Collection::make($processedRows),
            $this->columnMap,
            $this->options,
        )->all();

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

                $this->import->failedRows()->create([
                    'data' => array_map(
                        fn($value) => is_null($value) ? null : (string) $value,
                        $processedRow,
                    ),
                    'validation_errors' => [],
                    'import_id' => $this->import->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->import->increment('processed_rows', count($processedRows));
        $this->import->increment('imported_rows', $importedRowsCount);
        $this->import->increment('failed_rows', $failedRowsCount);

        $this->notifyImportProgress($this->import, $user);
    }
}

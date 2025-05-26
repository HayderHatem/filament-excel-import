<?php

namespace HayderHatem\FilamentExcelImport\Models;

use Filament\Actions\Imports\Models\Import as BaseImport;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends BaseImport
{
    /**
     * Get the failed rows for this import.
     */
    public function failedRows(): HasMany
    {
        return $this->hasMany(FailedImportRow::class, 'import_id');
    }

    /**
     * Get the count of failed rows.
     */
    public function getFailedRowsCount(): int
    {
        return $this->failedRows()->count();
    }
}

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

    /**
     * Get additional form data from options.
     */
    public function getAdditionalFormData(): array
    {
        return $this->options['additional_form_data'] ?? [];
    }

    /**
     * Get specific additional form value.
     */
    public function getAdditionalFormValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->getAdditionalFormData(), $key, $default);
    }

    /**
     * Check if additional form data has a specific key.
     */
    public function hasAdditionalFormValue(string $key): bool
    {
        return data_get($this->getAdditionalFormData(), $key) !== null;
    }
}

<?php

namespace HayderHatem\FilamentExcelImport\Traits;

trait CanAccessAdditionalFormData
{
    /**
     * Additional form data from import form
     */
    protected array $additionalFormData = [];

    /**
     * Set additional form data
     */
    public function setAdditionalFormData(array $data): static
    {
        $this->additionalFormData = $data;
        return $this;
    }

    /**
     * Get all additional form data
     */
    public function getAdditionalFormData(): array
    {
        return $this->additionalFormData;
    }

    /**
     * Get specific additional form value
     */
    public function getAdditionalFormValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->additionalFormData, $key, $default);
    }

    /**
     * Check if additional form data has a specific key
     */
    public function hasAdditionalFormValue(string $key): bool
    {
        return data_get($this->additionalFormData, $key) !== null;
    }
}

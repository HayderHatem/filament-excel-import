<?php

namespace HayderHatem\FilamentExcelImport;

use Filament\Contracts\Plugin;
use Filament\Panel;
use HayderHatem\FilamentExcelImport\Resources\ImportResource;

class FilamentExcelImportPlugin implements Plugin
{
    protected bool $hasImportResource = true;

    public function getId(): string
    {
        return 'filament-excel-import';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasImportResource) {
            $panel->resources([
                ImportResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function hasImportResource(bool $condition = true): static
    {
        $this->hasImportResource = $condition;
        return $this;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}

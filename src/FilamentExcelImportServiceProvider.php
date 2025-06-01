<?php

namespace HayderHatem\FilamentExcelImport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class FilamentExcelImportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(
            __DIR__ . '/../resources/lang', // Correct path to package lang files
            'filament-excel-import' // Translation namespace
        );

        // Ensure migration directory exists
        if (! File::exists(__DIR__ . '/../database/migrations')) {
            File::makeDirectory(__DIR__ . '/../database/migrations', 0o755, true);
        }

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish migrations if needed
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filament-excel-import-migrations');

        // Publishing resources
        if ($this->app->runningInConsole()) {
            // Publish translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/filament-excel-import'),
            ], 'filament-excel-import-translations');

            // Publish config if needed
            $this->publishes([
                __DIR__ . '/../config/filament-excel-import.php' => config_path('filament-excel-import.php'),
            ], 'filament-excel-import-config');
        }
    }

    /**
     * Ensure that migration files have timestamps.
     */
    protected function ensureMigrationsHaveTimestamps(): void
    {
        $migrationFiles = File::glob(__DIR__ . '/../database/migrations/*.php');

        foreach ($migrationFiles as $file) {
            $filename = basename($file);

            // Check if the migration has a timestamp (e.g., 2023_01_01_000000_)
            if (! preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename)) {
                $timestamp = date('Y_m_d_His');
                $newFilename = $timestamp . '_' . $filename;
                $newPath = __DIR__ . '/../database/migrations/' . $newFilename;

                // Rename the file if it doesn't have a timestamp
                if (! File::exists($newPath)) {
                    File::move($file, $newPath);
                }
            }
        }
    }

    public function register(): void
    {
        // Register any package services/bindings here
    }
}

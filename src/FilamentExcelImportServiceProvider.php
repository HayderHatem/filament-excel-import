<?php

namespace HayderHatem\FilamentExcelImport;

use Illuminate\Support\ServiceProvider;

class FilamentExcelImportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(
            __DIR__ . '/../resources/lang', // Correct path to package lang files
            'filament-excel-import' // Translation namespace
        );

        $this->loadViewsFrom(
            __DIR__ . '/../resources/views', // Correct path to package view files
            'filament-excel-import' // View namespace
        );

        // Publishing resources
        if ($this->app->runningInConsole()) {
            // Publish translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/filament-excel-import'),
            ], 'filament-excel-import-translations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-excel-import'),
            ], 'filament-excel-import-views');

            // Publish config if needed
            $this->publishes([
                __DIR__ . '/../config/filament-excel-import.php' => config_path('filament-excel-import.php'),
            ], 'filament-excel-import-config');
        }
    }



    public function register(): void
    {
        // Register the plugin
        $this->app->scoped(FilamentExcelImportPlugin::class);

        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-excel-import.php',
            'filament-excel-import'
        );
    }
}

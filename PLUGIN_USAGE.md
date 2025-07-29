# Filament Excel Import Plugin Usage

This document explains how to use the Filament Excel Import Plugin in your Filament application.

## Installation

The plugin is automatically registered when you install the package. The service provider will register the plugin class.

## Panel Registration

To use the plugin in your Filament panel, register it in your `PanelProvider`:

### Basic Usage

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use HayderHatem\FilamentExcelImport\FilamentExcelImportPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentExcelImportPlugin::make(),
            ]);
    }
}
```

### Advanced Configuration

```php
->plugins([
    FilamentExcelImportPlugin::make()
        ->hasImportResource(true), // Enable/disable the import resource
])
```

## Features

Once registered, the plugin provides:

1. **Import Resource**: A complete Filament resource for viewing and managing import operations
2. **Failed Rows Relation Manager**: View and manage failed import rows
3. **Import Statistics**: Track success rates, duration, and error information
4. **Retry Functionality**: Retry failed imports
5. **Export Failed Rows**: Download failed rows for correction
6. **Comprehensive Filtering**: Filter imports by status, type, date range
7. **Import Guide**: Interactive guide with best practices and troubleshooting tips
8. **Internationalization**: Multi-language support

## Database

The plugin uses Filament's existing import tables:

- Leverages Filament's built-in `imports` table
- Uses Filament's `failed_import_rows` table
- No additional migrations required - works with existing Filament import infrastructure

### Import Duration Calculation

The plugin automatically calculates import duration with robust error handling:
- **Start Time**: `created_at` timestamp (parsed with Carbon)
- **End Time**: `completed_at` timestamp (parsed with Carbon)  
- **Format**: Displays as HH:MM:SS (hours:minutes:seconds)
- **Long Duration**: Handles imports longer than 24 hours correctly
- **In Progress**: Shows "In Progress" for active imports
- **Error Handling**: Shows "Duration Unavailable" if calculation fails

### Relationships

The plugin uses Filament's standard relationship names:
- **Failed Rows**: `failedRows` relationship (not `failedImportRows`)
- **Model**: Uses `Filament\Actions\Imports\Models\FailedImportRow`
- **Foreign Key**: `import_id` references the imports table

## Permissions

If you're using Filament Shield, the plugin supports the following permissions:

- `view_import`
- `view_any_import`
- `create_import`
- `update_import`
- `delete_import`
- `delete_any_import`
- `export_import`
- `retry_import`

Generate permissions using:

```bash
php artisan shield:generate --all
```

## Configuration

Publish the configuration file to customize the plugin:

```bash
php artisan vendor:publish --tag="filament-excel-import-config"
```

### Available Configuration Options

```php
<?php

return [
    // Import Resource Configuration
    'import_resource' => [
        'enabled' => true,
        'navigation' => [
            'group' => 'Data Management',
            'sort' => 1,
            'icon' => 'heroicon-o-arrow-up-tray',
        ],
    ],

    // Database Configuration
    'database' => [
        'use_filament_tables' => true,
    ],

    // User Model
    'user_model' => \App\Models\User::class,

    // Import Tracking
    'tracking' => [
        'track_progress' => true,
        'track_duration' => true,
        'auto_cleanup_days' => 30,
    ],

    // UI Configuration
    'ui' => [
        'items_per_page' => [10, 25, 50, 100],
        'default_items_per_page' => 25,
        'show_import_guide' => true,
        'show_navigation_badge' => true,
    ],
];
```

## Translations

The plugin supports multiple languages with full internationalization. Publish the translation files:

```bash
php artisan vendor:publish --tag="filament-excel-import-translations"
```

### Available Languages

- **English (`en`)** - Complete with all features
- **Arabic (`ar`)** - Complete with RTL support and proper Arabic typography

### RTL Support

The plugin automatically detects the current locale and applies appropriate direction:

- **LTR Languages**: Uses `dir="ltr"` with `ml-4` margins
- **RTL Languages**: Uses `dir="rtl"` with `mr-4` margins
- **Import Guide**: Fully translatable view with direction-aware styling

### Features Covered by Translations

All plugin components support translations:

- ✅ **Resource Labels**: Model names, navigation, badges
- ✅ **Form Fields**: All input labels and descriptions
- ✅ **Table Columns**: Headers, status badges, filters
- ✅ **Actions**: Buttons, confirmations, tooltips
- ✅ **Import Guide**: Complete step-by-step guide
- ✅ **Error Messages**: Validation and system messages
- ✅ **Status Indicators**: Progress, completion states
- ✅ **Navigation Groups**: Configurable via translations

### Adding Custom Translations

Create translation files in your application's `lang` directory:

```php
// lang/es/vendor/filament-excel-import/import.php
return [
    'model_label' => 'Importación',
    'plural_model_label' => 'Importaciones',
    'navigation_label' => 'Importar Datos',
    'data_management_group' => 'Gestión de Datos',
    'guide_title' => 'Cómo Importar Datos',
    // ... other translations
];
```

### Language Switching

The plugin respects Laravel's current locale:

```php
// Set locale programmatically
app()->setLocale('ar');

// Or via middleware/user preferences
// Translations will be applied automatically
```

## Integration with Import Actions

The plugin displays imports created by Filament's built-in import system, including those from your `FullImportAction`:

```php
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

// In your resource
public static function getHeaderActions(): array
{
    return [
        FullImportAction::make()
            ->importer(UserImporter::class),
    ];
}
```

All imports created through any Filament import action will automatically appear in the Import Management resource.

## Navigation

The plugin adds a "Data Imports" navigation item to your panel. You can customize this in the configuration:

```php
'import_resource' => [
    'navigation' => [
        'group' => 'Your Custom Group',
        'sort' => 10,
        'icon' => 'heroicon-o-document-arrow-up',
    ],
],
```

## Best Practices

### 1. Performance Optimization

- Use eager loading for relationships in custom queries
- Consider implementing pagination for large datasets
- Add database indexes for frequently filtered columns

### 2. Security Considerations

- Implement proper authorization policies
- Validate file uploads and data integrity
- Use HTTPS for file uploads in production

### 3. Monitoring

- Regularly monitor import success rates
- Set up alerts for failed imports
- Clean up old import records periodically

### 4. User Experience

- Provide clear import templates and documentation
- Use the built-in import guide feature
- Implement proper error messages and validation

## Troubleshooting

### Common Issues

**1. Plugin not appearing in navigation**
- Ensure the plugin is registered in your `PanelProvider`
- Check that `import_resource.enabled` is `true` in configuration

**2. Permission errors**
- Generate Shield permissions: `php artisan shield:generate --all`
- Assign appropriate roles to users

**3. Failed rows not showing**
- Verify that Filament's import tables exist
- Check that the `failedRows` relationship is working

**4. Duration calculation errors**
- Ensure timestamps are properly formatted
- Check for null values in date fields

### Getting Help

For additional support:

1. Check the [main package documentation](../README.md)
2. Review Filament's import system documentation
3. Ensure you're using compatible versions of Filament and the plugin

## Examples

### Basic Resource Usage

```php
// View all imports
/admin/imports

// View specific import with failed rows
/admin/imports/{id}

// Download failed rows
/admin/imports/{id}/failed-rows/download
```

### Custom Import Actions

```php
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

Actions\HeaderAction::make('import_users')
    ->label('Import Users')
    ->action(function (array $data) {
        return FullImportAction::make()
            ->importer(UserImporter::class)
            ->run($data);
    });
```

For more information, refer to the main package documentation and Filament's official import documentation. 
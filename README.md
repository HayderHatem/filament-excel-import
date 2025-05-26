# Filament Excel Import

A powerful Excel import extension for Filament PHP v3 that enables importing data from various Excel file formats with support for multiple sheets.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)
[![Total Downloads](https://img.shields.io/packagist/dt/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)

## Features

- 📊 Support for multiple Excel formats (.xlsx, .xls, .xlsm, .xlsb, .xltx, .xltm, etc.)
- 📑 Multiple sheet handling with dynamic sheet selection
- 🔄 Maintains compatibility with Filament's existing import system
- 🧩 Flexible configuration options for header rows and active sheets
- 📝 Automatic column mapping based on header names
- 🚀 Background processing with Laravel queues
- 📈 Progress tracking and notifications
- 📋 Failed row handling and reporting
- 🛡️ Robust error handling with detailed logging
- 🔧 Custom Import and FailedImportRow models for enhanced functionality

## Requirements

- PHP 8.1+
- Laravel 10.0+
- Filament 3.0+
- PhpSpreadsheet

## Installation

You can install the package via composer:

```bash
composer require hayderhatem/filament-excel-import
```

## Database Setup

The package includes migrations for the required database tables. You can publish and run them with:

```bash
php artisan vendor:publish --tag="filament-excel-import-migrations"
php artisan migrate
```

Alternatively, the migrations will run automatically when your application migrates.

### Migration Notes

The package's migrations are designed to be safe and handle various scenarios:

1. If the tables don't exist yet, they will be created with all required columns
2. If the tables already exist but are missing some columns, only the missing columns will be added
3. Migration files are automatically timestamped to ensure they run in the correct order

This approach ensures compatibility with existing databases and prevents migration issues when updating the package.

## Usage

### Basic Usage

1. First, create an importer class that defines how your Excel data should be processed:

```php
<?php

namespace App\Filament\Imports;

use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use HayderHatem\FilamentExcelImport\Models\Import;
use Illuminate\Support\Facades\Hash;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'unique:users,email']),
            ImportColumn::make('password')
                ->requiredMapping()
                ->rules(['required', 'string', 'min:8']),
        ];
    }

    public static function getLabel(): string
    {
        return 'User';
    }

    public function resolveRecord(): ?User
    {
        // You can customize this to update existing records
        // return User::firstOrNew([
        //     'email' => $this->data['email'],
        // ]);

        return new User();
    }

    /**
     * Import a single row of data
     * 
     * This method must be implemented to process each row of data
     */
    public function import(array $data, array $map, array $options = []): void
    {
        $user = $this->resolveRecord();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();
    }

    /**
     * Define the notification message shown when import is complete
     * Note: This method must use Filament's Import model in the signature
     * but can access our custom Import model internally
     */
    public static function getCompletedNotificationBody(\Filament\Actions\Imports\Models\Import $import): string
    {
        // Access our custom Import model for additional functionality
        $customImport = Import::find($import->id);
        
        $body = 'Your user import has completed and ' . 
                number_format($import->successful_rows ?? $customImport?->imported_rows ?? 0) . ' ' . 
                str('user')->plural($import->successful_rows ?? $customImport?->imported_rows ?? 0) . ' imported.';

        if ($failedRowsCount = $customImport?->getFailedRowsCount() ?? 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . 
                     str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
```

2. Add the import action to your Filament resource:

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Imports\UserImporter;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            // Use the Excel import action
            FullImportAction::make()
                ->importer(UserImporter::class),
        ];
    }
}
```

### Advanced Configuration

You can customize the Excel import behavior with various configuration options:

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->headerRow(2)                // Use the second row as headers (1-based index)
    ->activeSheet(0)              // Set the default active sheet (0-based index)
    ->chunkSize(50)               // Process 50 rows per job
    ->maxRows(1000)               // Allow importing up to 1000 rows
    ->options([                   // Pass additional options to the importer
        'update_existing' => true,
    ])
    ->fileValidationRules([       // Add custom validation rules
        'max:10240',              // 10MB max file size
    ]);
```

## Excel-Specific Features

### Multiple Sheet Support

The Excel import trait automatically detects multiple sheets in an Excel file and allows users to select which sheet to import from:

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->activeSheet(0) // Set the default active sheet (0-based index)
```

When an Excel file contains multiple sheets, users will see a dropdown to select which sheet to import from.

### Header Row Configuration

You can specify which row contains the headers in your Excel file:

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->headerRow(2) // Use the second row as headers (1-based index)
```

### Supported File Formats

The trait supports a wide range of Excel file formats:

- `.xlsx` - Excel 2007+ XML Format
- `.xls` - Excel 97-2003 Binary Format
- `.xlsm` - Excel 2007+ Macro-Enabled XML Format
- `.xlsb` - Excel 2007+ Binary Format
- `.xltx` - Excel 2007+ XML Template Format
- `.xltm` - Excel 2007+ Macro-Enabled XML Template Format
- `.csv` - CSV Format (for backward compatibility)

## Enhanced Features

### Custom Import Model

The package includes a custom Import model that extends Filament's default Import model with additional functionality:

- `failedRows()` relationship for accessing failed import rows
- `getFailedRowsCount()` method for counting failed rows
- Enhanced error tracking and reporting

### Failed Row Handling

Failed rows are automatically stored in the `failed_import_rows` table with:

- Complete row data
- Validation errors (if any)
- Error messages
- Relationship to the parent import

### Progress Notifications

The package provides enhanced progress notifications that show:

- Number of successfully imported rows
- Number of failed rows
- Links to download failed rows (when applicable)
- Appropriate notification colors based on import status

## Customizing the Import Process

### Custom Job Class

You can use a custom job class for processing the import:

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->job(CustomImportExcelJob::class)
```

### Error Handling

The package includes robust error handling:

- Failed rows are logged with detailed error information
- Import progress is tracked even when errors occur
- Graceful degradation when database operations fail
- Comprehensive logging for debugging

### Queue Configuration

For large imports, configure your queue settings:

```php
// In your importer class
public function getJobQueue(): ?string
{
    return 'imports'; // Use a dedicated queue for imports
}

public function getJobConnection(): ?string
{
    return 'redis'; // Use Redis for better performance
}
```

## Available Actions

The package provides two main action classes:

### FullImportAction

Use this for complete Excel import functionality with all features:

```php
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

FullImportAction::make()
    ->importer(UserImporter::class)
```

### ImportAction (Alternative)

For basic import functionality:

```php
use HayderHatem\FilamentExcelImport\Actions\ImportAction;

ImportAction::make()
    ->importer(UserImporter::class)
```

## Troubleshooting

### Common Issues

1. **"System error, please contact support" message**: 
   - Ensure your importer class implements the `import()` method
   - Check that you're using the correct Import model in method signatures
   - Verify database migrations have been run

2. **File Format Issues**:
   - Ensure PhpSpreadsheet library is properly installed
   - Check file extension matches the actual file format
   - Verify the file is not password-protected or corrupted

3. **Memory Limitations**:
   - Increase PHP memory limit in your `php.ini` file
   - Reduce chunk size to process fewer rows per job
   - Use queue system for background processing

### Debugging

Enable detailed logging by checking your Laravel logs when imports fail. The package logs detailed error information to help with debugging.

### Database Issues

If you encounter database-related errors:

1. Run migrations: `php artisan migrate`
2. Check that all required tables exist: `imports`, `failed_import_rows`
3. Verify foreign key constraints are properly set up

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Hayder Hatem](https://github.com/hayderhatem)
- [All Contributors](../../contributors)

This package is built on top of the excellent [Filament](https://filamentphp.com/) admin panel framework.

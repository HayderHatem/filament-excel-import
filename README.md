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
use Illuminate\Support\Collection;
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
                ->rules(['required', 'string', 'min:8'])
                ->transform(fn (string $value) => Hash::make($value)),
        ];
    }

    /**
     * Import a single row of data
     * 
     * This method must be implemented to process each row of data
     */
    public function import(array $data, array $map, array $options = []): void
    {
        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->save();
    }

    /**
     * Define the notification message shown when import is complete
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and ' . number_format($import->successful_rows) . ' ' . str('user')->plural($import->successful_rows) . ' ' . str('was')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
```

2. Add the import action to your Filament resource:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Imports\UserImporter;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use HayderHatem\FilamentExcelImport\Actions\ImportAction;

class UserResource extends Resource
{
    // ... other resource configuration

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ... your columns
            ])
            ->headerActions([
                // Use the Excel import action
                ImportAction::make()
                    ->importer(UserImporter::class)
            ]);
    }
}
```

### Advanced Configuration

You can customize the Excel import behavior with various configuration options:

```php
ImportAction::make()
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
ImportAction::make()
    ->importer(UserImporter::class)
    ->activeSheet(0) // Set the default active sheet (0-based index)
```

### Header Row Configuration

You can specify which row contains the headers in your Excel file:

```php
ImportAction::make()
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

## Customizing the Import Process

### Custom Job Class

You can use a custom job class for processing the import:

```php
ImportAction::make()
    ->importer(UserImporter::class)
    ->job(CustomImportExcelJob::class)
```

### Data Transformation

You can transform the data before it's imported using the `transform` method in your importer:

```php
public function transform(Collection $rows, array $map, array $options = []): Collection
{
    return $rows->map(function (array $row) {
        // Transform the data
        $row['name'] = ucfirst($row['name']);
        
        return $row;
    });
}
```

## Troubleshooting

### File Format Issues

If you encounter issues with specific Excel file formats, ensure that:

1. The PhpSpreadsheet library is properly installed
2. The file extension matches the actual file format
3. The file is not password-protected or corrupted

### Memory Limitations

Excel files can be memory-intensive to process. If you encounter memory issues:

1. Increase the PHP memory limit in your `php.ini` file
2. Reduce the chunk size to process fewer rows per job
3. Consider using the queue system to process imports in the background

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Hayder Hatem](https://github.com/hayderhatem)
- [All Contributors](../../contributors)

This package is built on top of the excellent [Filament](https://filamentphp.com/) admin panel framework.

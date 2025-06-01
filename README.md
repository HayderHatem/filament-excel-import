# Filament Excel Import

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)

A Laravel package that extends Filament's ImportAction to support Excel file imports with automatic validation error capture.

## Features

- **Seamless Integration**: Works automatically with Filament's ImportAction
- **Excel Support**: Import from Excel files (.xlsx, .xls) in addition to CSV
- **Automatic Validation**: Captures validation errors from ImportColumn rules automatically
- **Failed Rows Tracking**: Automatically tracks failed rows with detailed validation error messages
- **Standard Compatibility**: Fully compatible with Filament's standard import structure

## Installation

Install the package via Composer:

```bash
composer require hayderhatem/filament-excel-import
```

The package will automatically register itself and run migrations.

## Basic Usage

### 1. Create an Importer

Create a standard Filament Importer with ImportColumn rules:

```php
<?php

namespace App\Filament\Imports;

use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
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

    public function resolveRecord(): ?User
    {
        return new User();
    }

    // No manual validation needed - the package captures validation errors automatically!
}
```

### 2. Use the Excel Import Action

Replace Filament's standard ImportAction with the Excel version:

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Imports\UserImporter;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FullImportAction::make()
                ->importer(UserImporter::class),
        ];
    }
}
```

### 3. That's it!

The package automatically:
- ✅ Handles Excel file uploads
- ✅ Captures validation errors from your ImportColumn rules
- ✅ Tracks failed rows with detailed error messages
- ✅ Provides download of failed rows CSV
- ✅ Works with existing Filament notifications

## How Validation Works

The package automatically captures validation errors from:

1. **ImportColumn Rules**: Any validation rules defined on ImportColumn objects
2. **Model Validation**: Laravel model validation during save
3. **Database Constraints**: Unique constraints, foreign key violations, etc.

**No manual validation needed!** Just define your rules in ImportColumn and the package handles the rest.

### Example with Validation Errors

```php
ImportColumn::make('email')
    ->requiredMapping()
    ->rules(['required', 'email', 'unique:users,email']),
```

If validation fails, the error will be automatically captured as:
- `"email: The email field is required"`
- `"email: The email must be a valid email address"`
- `"email: The email has already been taken"`

## Advanced Usage

### Custom Options

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->activeSheet(0) // Import from first sheet
    ->headerRow(2)   // Headers are on row 2
    ->chunkSize(50)  // Process 50 rows at a time
```

### Custom File Validation

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->fileValidationRules([
        'max:10240',           // Max 10MB
        'mimes:xlsx,xls,csv',  // Allowed formats
    ])
```

### Using in Table Actions

```php
use HayderHatem\FilamentExcelImport\Actions\TableImportAction;

protected function getHeaderActions(): array
{
    return [
        TableImportAction::make()
            ->importer(UserImporter::class),
    ];
}
```

## Error Handling

### Automatic Failed Rows Tracking

The package automatically:
- Records failed rows with validation errors
- Provides downloadable CSV of failed rows
- Shows error counts in notifications
- Maintains data integrity

### Accessing Failed Rows Programmatically

```php
use HayderHatem\FilamentExcelImport\Models\Import;

$import = Import::find($importId);
$failedRows = $import->failedRows;

foreach ($failedRows as $failedRow) {
    echo "Row data: " . json_encode($failedRow->data);
    echo "Validation error: " . $failedRow->validation_error;
}
```

## Database Structure

The package creates these tables (compatible with standard Filament):

- `imports`: Tracks import sessions
- `failed_import_rows`: Stores failed rows with validation errors

The structure is fully compatible with Filament's standard import tables.

## Requirements

- Laravel 10.0+
- Filament 3.0+
- PHP 8.1+

## Migration Compatibility

The package automatically handles migrations and is fully compatible with standard Filament import migrations. If you already have Filament's import tables, the package will use them seamlessly.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

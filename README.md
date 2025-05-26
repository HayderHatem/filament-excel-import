# Filament Excel Import

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)

A powerful and flexible Excel import package for Filament that extends the native import functionality with enhanced features, better error handling, and comprehensive validation.

## Features

- 🚀 **Enhanced Import Action**: Extends Filament's native ImportAction with Excel-specific features
- 📊 **Multi-Sheet Support**: Import from specific sheets in Excel workbooks
- 🔍 **Advanced Validation**: Comprehensive validation with detailed error reporting
- 📈 **Progress Tracking**: Real-time import progress with detailed statistics
- 🛡️ **Error Handling**: Robust error handling with failed row tracking
- 🎯 **Flexible Configuration**: Configurable chunk sizes, header rows, and validation rules
- 📱 **Notification System**: Built-in notifications for import completion and errors
- 🧪 **Fully Tested**: Comprehensive test suite with 44 tests covering all functionality

## Installation

You can install the package via Composer:

```bash
composer require hayderhatem/filament-excel-import
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-excel-import-migrations"
php artisan migrate
```

Optionally, you can publish the config file:

```bash
php artisan vendor:publish --tag="filament-excel-import-config"
```

## Usage

### Basic Usage

1. **Create an Importer** (extends Filament's Importer):

```php
<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use App\Models\User;

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
}
```

2. **Use the Enhanced Import Action**:

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Imports\UserImporter;
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            FullImportAction::make()
                ->importer(UserImporter::class)
                ->chunkSize(100)
                ->headerRow(1)
                ->maxRows(1000),
        ];
    }
}
```

### Advanced Configuration

#### Multi-Sheet Excel Files

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->activeSheet(0) // Import from first sheet
    ->headerRow(2)   // Headers are on row 2
    ->chunkSize(50)  // Process 50 rows at a time
```

#### Custom Validation Rules

```php
FullImportAction::make()
    ->importer(UserImporter::class)
    ->fileValidationRules([
        'max:10240',           // Max 10MB
        'mimes:xlsx,xls,csv',  // Allowed formats
    ])
```

#### Using the Trait in Custom Actions

```php
<?php

namespace App\Filament\Actions;

use Filament\Actions\ImportAction;
use HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords;

class CustomImportAction extends ImportAction
{
    use CanImportExcelRecords;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->chunkSize(200)
             ->maxRows(5000)
             ->options(['update_existing' => true]);
    }
}
```

### Error Handling and Failed Rows

The package automatically tracks failed rows with detailed error information:

```php
// Access failed rows after import
$import = Import::find($importId);
$failedRows = $import->failedRows;

foreach ($failedRows as $failedRow) {
    echo "Row data: " . json_encode($failedRow->data);
    echo "Validation errors: " . json_encode($failedRow->validation_errors);
}
```

### Notifications

The package provides built-in notifications for import completion:

```php
// Customize notification messages in your Importer
public static function getCompletedNotificationBody(\Filament\Actions\Imports\Models\Import $import): string
{
    $customImport = Import::find($import->id);
    
    $body = 'Import completed successfully! ' .
        number_format($customImport->imported_rows) . ' records imported.';
    
    if ($failedRowsCount = $customImport->getFailedRowsCount()) {
        $body .= ' ' . number_format($failedRowsCount) . ' rows failed.';
    }
    
    return $body;
}
```

## Configuration

The package supports various configuration options:

| Option | Description | Default |
|--------|-------------|---------|
| `chunkSize` | Number of rows to process at once | `100` |
| `headerRow` | Row number containing headers | `1` |
| `activeSheet` | Sheet index to import from | `0` |
| `maxRows` | Maximum rows to import | `null` |
| `fileValidationRules` | File validation rules | `[]` |

## Testing

The package includes a comprehensive test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Check code style:

```bash
composer cs-check
```

Fix code style issues:

```bash
composer cs-fix
```

Run static analysis:

```bash
composer phpstan
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Hayder Hatem](https://github.com/hayderhatem)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

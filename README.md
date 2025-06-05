# Filament Excel Import

A powerful Excel import package for Filament that provides seamless Excel file import functionality with automatic column mapping and memory-efficient processing.

## Installation

You can install the package via composer:

```bash
composer require hayderhatem/filament-excel-import
```

## Features

- 📊 **Excel & CSV Import**: Support for `.xlsx`, `.xls`, `.csv` and other spreadsheet formats
- 🔄 **Drop-in Replacement**: Compatible with Filament's existing `CanImportRecords` trait
- 🗂️ **Multi-Sheet Support**: Import from specific sheets in Excel workbooks
- 🎯 **Smart Column Mapping**: Automatic header detection and column mapping
- 🚀 **Memory Efficient**: Handles large files without memory exhaustion
- 🔁 **Streaming Import**: Automatic streaming for large files (configurable)
- 📝 **Custom Import Options**: Add additional form fields to import modal
- 🌐 **Multi-language**: Translatable error messages and UI text
- ⚠️ **Error Handling**: User-friendly error messages and failed rows export
- 🎨 **Easy Configuration**: Minimal setup required

## Usage

### Basic Setup

Replace Filament's `CanImportRecords` trait with `CanImportExcelRecords` in your resource:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Imports\UserImporter;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Resource;
use HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(UserImporter::class),
        ];
    }
}
```

### Configuration Options

The package supports all of Filament's original import options plus additional Excel-specific features:

```php
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    // Standard Filament options
    ->chunkSize(1000)
    ->maxRows(10000)
    ->headerOffset(0) // Row number where headers are located (0-based)
    ->job(CustomImportJob::class) // Custom job class
    // Excel-specific options
    ->activeSheet(0) // Which Excel sheet to import (0-based)
    ->useStreaming(true) // Force streaming mode
    ->streamingThreshold(10 * 1024 * 1024) // 10MB threshold for auto-streaming
```

### Multi-Sheet Excel Files

When importing Excel files with multiple sheets, users can select which sheet to import:

```php
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    ->activeSheet(0) // Default to first sheet
```

The import modal will automatically show a sheet selector dropdown if multiple sheets are detected.

### Additional Form Components

You can add custom form fields to the import modal:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

Actions\ImportAction::make()
    ->importer(UserImporter::class)
    ->additionalFormComponents([
        Select::make('department_id')
            ->label('Default Department')
            ->options(Department::pluck('name', 'id'))
            ->required(),
        
        Toggle::make('send_welcome_email')
            ->label('Send Welcome Email')
            ->default(true),
    ])
```

Access additional form data in your importer:

```php
use HayderHatem\FilamentExcelImport\Traits\CanAccessAdditionalFormData;

class UserImporter extends Importer
{
    use CanAccessAdditionalFormData;
    
    public function import(array $data, array $map, array $options = []): void
    {
        $departmentId = $this->getAdditionalFormValue('department_id');
        $sendEmail = $this->getAdditionalFormValue('send_welcome_email', false);
        
        // Your import logic...
    }
}
```

## Memory Optimization & Streaming

### Automatic Streaming

The package automatically detects large files and switches to streaming mode to prevent memory exhaustion:

```php
// Files larger than 10MB (default) will automatically use streaming
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    ->streamingThreshold(5 * 1024 * 1024) // Custom 5MB threshold
```

### Manual Streaming Control

Force streaming mode on or off:

```php
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    ->useStreaming(true) // Always use streaming
    // or
    ->useStreaming(false) // Never use streaming
    // or
    ->useStreaming(null) // Auto-detect (default)
```

### Memory Usage Comparison

| File Size | Standard Import | Streaming Import |
|-----------|----------------|------------------|
| 1MB       | ~10MB RAM      | ~5MB RAM         |
| 100MB     | Memory Error   | ~5MB RAM         |
| 1GB       | Memory Error   | ~5MB RAM         |

## Advanced Configuration

### Custom Job

You can use your own import job class:

```php
use HayderHatem\FilamentExcelImport\Actions\Imports\Jobs\ImportExcel;

class CustomImportJob extends ImportExcel
{
    public function handle(): void
    {
        // Custom pre-processing
        
        parent::handle();
        
        // Custom post-processing
    }
}

// Use in your action
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    ->job(CustomImportJob::class)
```

### Error Handling

The package provides user-friendly error messages for common database errors:

```php
// Errors are automatically translated and user-friendly
// - "Email field is required" instead of SQL constraint errors  
// - "Email already exists" instead of unique constraint violations
// - "Invalid department reference" instead of foreign key errors
```

### Custom Error Messages

Add custom error message translations:

```php
// resources/lang/en/filament-excel-import.php
return [
    'import' => [
        'errors' => [
            'field_required' => 'The :field field is required.',
            'field_exists' => 'The :field already exists.',
            'invalid_reference' => 'Invalid reference to :table.',
        ]
    ]
];
```

## File Support

### Supported Formats

- **Excel**: `.xlsx`, `.xls`, `.xlsm`, `.xltx`, `.xltm`
- **CSV**: `.csv`, `.txt`
- All formats supported by PhpSpreadsheet

### File Upload Behavior

- **Small files**: Full preview with dropdown column mapping
- **Large files**: Header-only reading for memory efficiency
- **Very large files**: Manual text input mapping
- **Any size**: Import processing always works via streaming

## API Compatibility

The package is a drop-in replacement for Filament's `CanImportRecords` trait:

| Filament Method | Supported | Notes |
|----------------|-----------|-------|
| `importer()` | ✅ | Fully compatible |
| `job()` | ✅ | Fully compatible |
| `chunkSize()` | ✅ | Fully compatible |
| `maxRows()` | ✅ | Fully compatible |
| `headerOffset()` | ✅ | Fully compatible |
| `options()` | ✅ | Fully compatible |
| `fileValidationRules()` | ✅ | Enhanced for Excel |

### Additional Methods

| Method | Description |
|--------|-------------|
| `useStreaming(bool\|null)` | Control streaming mode |
| `streamingThreshold(int)` | Set auto-streaming threshold |
| `activeSheet(int)` | Set Excel sheet to import |
| `additionalFormComponents(array)` | Add custom form fields |

## Migration from CanImportRecords

Migrating from Filament's built-in import is simple:

### Step 1: Update the Trait

```php
// Before
use Filament\Actions\Concerns\CanImportRecords;

// After  
use HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords;
```

### Step 2: No Other Changes Required

All your existing code will work exactly the same! The package is designed as a drop-in replacement.

### Step 3: Optional Enhancements

Take advantage of new features:

```php
Actions\ImportAction::make()
    ->importer(UserImporter::class)
    // Your existing configuration works as-is
    ->chunkSize(500)
    ->maxRows(5000)
    // Add new Excel features
    ->activeSheet(0)
    ->useStreaming(true)
```

## Testing

The package includes comprehensive tests:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run package test runner
php test-runner.php
```

## Requirements

- PHP 8.1+
- Laravel 10+
- Filament 3+
- PhpSpreadsheet (automatically installed)

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email the maintainer instead of using the issue tracker.

## Credits

- [Hayder Hatem](https://github.com/hayderhatem)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

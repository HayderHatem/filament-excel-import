# Filament Excel Import Plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hayderhatem/filament-excel-import/tests.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hayderhatem/filament-excel-import/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hayderhatem/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/hayderhatem/filament-excel-import)

A powerful Excel import plugin for Filament that extends the standard ImportAction with Excel-specific features and enhanced validation handling.

## Features

- 📊 **Excel File Support**: Import XLSX, XLS, and CSV files with native PhpSpreadsheet integration
- 🎯 **User-Friendly Error Messages**: SQL errors are automatically converted to plain, understandable messages
- 🌍 **Translatable Error Messages**: Full support for multi-language error messages
- 📑 **Multi-Sheet Support**: Import from any sheet in your Excel file with dynamic header detection
- ✅ **Automatic Validation**: Captures and displays validation errors in a user-friendly format
- 📥 **Failed Rows Export**: Download failed rows as CSV with clear error descriptions
- 🚀 **Queue Support**: Handle large imports efficiently with Laravel's queue system
- 🎨 **Seamless Integration**: Works with your existing Filament importers
- 🔧 **Additional Form Components**: Add custom select dropdowns and form fields to enhance import context

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

## Additional Form Components

You can add custom form components (like select dropdowns) to the import form to provide context, defaults, or options that affect how the import is processed.

### Basic Usage

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;

FullImportAction::make()
    ->importer(UserImporter::class)
    ->additionalFormComponents([
        Select::make('default_status')
            ->label('Default Status')
            ->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending'
            ])
            ->default('active')
            ->required(),
            
        Select::make('import_mode')
            ->label('Import Mode')
            ->options([
                'create_only' => 'Create New Records Only',
                'update_only' => 'Update Existing Records Only',
                'create_or_update' => 'Create or Update Records'
            ])
            ->default('create_only')
            ->required(),
            
        TextInput::make('batch_name')
            ->label('Batch Name')
            ->placeholder('Enter batch identifier')
            ->helperText('Optional identifier for this import batch'),
    ]);
```

### Using Additional Form Data in Importer

```php
<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use HayderHatem\FilamentExcelImport\Traits\CanAccessAdditionalFormData;

class UserImporter extends Importer
{
    use CanAccessAdditionalFormData;

    public function import(array $data, array $map, array $options = []): void
    {
        $record = $this->resolveRecord();
        
        // Use regular mapped data
        $record->name = $data['name'];
        $record->email = $data['email'];
        
        // Use additional form data for context and defaults
        $defaultStatus = $this->getAdditionalFormValue('default_status', 'active');
        $importMode = $this->getAdditionalFormValue('import_mode', 'create_only');
        $batchName = $this->getAdditionalFormValue('batch_name');
        
        // Apply defaults for empty values
        $record->status = $data['status'] ?? $defaultStatus;
        
        // Add batch information if provided
        if ($batchName) {
            $record->import_batch = $batchName;
        }
        
        // Handle different import modes
        switch ($importMode) {
            case 'update_only':
                $existing = static::getModel()::where('email', $record->email)->first();
                if (!$existing) {
                    throw new \Exception('Record not found for update-only mode');
                }
                $existing->update($record->toArray());
                return;
                
            case 'create_or_update':
                static::getModel()::updateOrCreate(
                    ['email' => $record->email],
                    $record->toArray()
                );
                return;
                
            default: // create_only
                $record->save();
        }
    }
}
```

### Dynamic Select Options

```php
use App\Models\Department;
use App\Models\Role;

FullImportAction::make()
    ->importer(UserImporter::class)
    ->additionalFormComponents([
        Select::make('default_department_id')
            ->label('Default Department')
            ->options(
                Department::active()
                    ->pluck('name', 'id')
                    ->toArray()
            )
            ->searchable()
            ->placeholder('Select default department for empty values'),
            
        Select::make('default_role')
            ->label('Default Role')
            ->options(function () {
                $user = auth()->user();
                
                // Show different roles based on user permissions
                if ($user->hasRole('admin')) {
                    return Role::all()->pluck('name', 'name')->toArray();
                }
                
                return Role::where('level', '<=', $user->role_level)
                    ->pluck('name', 'name')
                    ->toArray();
            })
            ->searchable()
            ->required(),
            
        Select::make('validation_level')
            ->label('Validation Level')
            ->options([
                'strict' => 'Strict - Reject any invalid data',
                'moderate' => 'Moderate - Skip invalid rows with warnings',
                'lenient' => 'Lenient - Accept data with minor issues'
            ])
            ->default('moderate')
            ->helperText('Choose how strictly to validate imported data'),
    ]);
```

### Available Methods in Importer

When using the `CanAccessAdditionalFormData` trait in your importer:

```php
// Get all additional form data
$allData = $this->getAdditionalFormData();

// Get specific value with default
$status = $this->getAdditionalFormValue('default_status', 'active');

// Check if value exists
if ($this->hasAdditionalFormValue('batch_name')) {
    $batchName = $this->getAdditionalFormValue('batch_name');
    // Handle batch processing
}

// Use in validation logic
$validationLevel = $this->getAdditionalFormValue('validation_level', 'moderate');
if ($validationLevel === 'strict') {
    // Apply strict validation rules
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

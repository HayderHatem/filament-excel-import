# Test Suite Documentation

This directory contains comprehensive tests for the Filament Excel Import package.

## Test Structure

```
tests/
├── Feature/                    # Integration and feature tests
│   ├── CanImportExcelRecordsTest.php
│   ├── ExcelImportIntegrationTest.php
│   └── FullImportActionTest.php
├── Unit/                       # Unit tests
│   ├── ImportModelTest.php
│   ├── FailedImportRowTest.php
│   └── ImportExcelJobTest.php
├── Helpers/                    # Test helper classes
│   └── ExcelTestHelper.php
├── Importers/                  # Test importer classes
│   └── TestUserImporter.php
├── Models/                     # Test model classes
│   └── User.php
├── TestCase.php               # Base test case
└── README.md                  # This file
```

## Running Tests

### Prerequisites

1. Install test dependencies:
```bash
cd packages/hayderhatem/filament-excel-import
composer install
```

2. Make sure you have PHPUnit installed:
```bash
composer require --dev phpunit/phpunit
```

### Running All Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage
```

### Running Specific Test Suites

```bash
# Run only unit tests
vendor/bin/phpunit tests/Unit

# Run only feature tests
vendor/bin/phpunit tests/Feature

# Run a specific test file
vendor/bin/phpunit tests/Unit/ImportModelTest.php

# Run a specific test method
vendor/bin/phpunit --filter it_can_create_an_import_record tests/Unit/ImportModelTest.php
```

## Test Categories

### Unit Tests

**ImportModelTest.php**
- Tests the custom Import model functionality
- Verifies relationships and methods
- Tests failed row counting

**FailedImportRowTest.php**
- Tests the FailedImportRow model
- Verifies data casting and relationships
- Tests error handling

**ImportExcelJobTest.php**
- Tests the ImportExcel job class
- Verifies row processing logic
- Tests error handling and batch cancellation

### Feature Tests

**CanImportExcelRecordsTest.php**
- Tests the Excel import trait functionality
- Verifies Excel file reading and parsing
- Tests multiple sheet support
- Tests header row configuration

**FullImportActionTest.php**
- Tests the FullImportAction class
- Verifies configuration methods
- Tests file type acceptance

**ExcelImportIntegrationTest.php**
- End-to-end integration tests
- Tests complete import workflows
- Verifies error handling and validation
- Tests large file processing

## Test Helpers

### ExcelTestHelper

The `ExcelTestHelper` class provides utility methods for creating test Excel files:

```php
// Create a simple user Excel file
$file = ExcelTestHelper::createUserExcelFile();

// Create a multi-sheet Excel file
$file = ExcelTestHelper::createMultiSheetExcelFile();

// Create an Excel file with custom header row
$file = ExcelTestHelper::createExcelFileWithCustomHeaderRow(3);

// Create an Excel file with invalid data
$file = ExcelTestHelper::createInvalidDataExcelFile();

// Clean up temporary files
ExcelTestHelper::cleanup($file);
```

### TestUserImporter

A test importer class that implements the required methods for testing:

```php
class TestUserImporter extends Importer
{
    protected static ?string $model = User::class;
    
    public function import(array $data, array $map, array $options = []): void
    {
        // Import logic for testing
    }
}
```

## Test Data

The tests use various types of test data:

1. **Valid Data**: Standard user records with name, email, and password
2. **Invalid Data**: Records with validation errors (empty fields, invalid emails, etc.)
3. **Duplicate Data**: Records with duplicate email addresses
4. **Empty Cells**: Records with empty or null values
5. **Large Datasets**: Files with hundreds or thousands of rows

## Database Setup

Tests use an in-memory SQLite database that is recreated for each test. The test case automatically:

1. Runs package migrations
2. Creates a users table for testing
3. Sets up proper relationships
4. Cleans up after each test

## Mocking and Fakes

The tests use Laravel's built-in testing features:

- `Storage::fake()` for file system operations
- `Queue::fake()` for queue testing (when needed)
- In-memory database for fast test execution

## Coverage

The test suite aims for comprehensive coverage of:

- ✅ Model functionality (Import, FailedImportRow)
- ✅ Job processing (ImportExcel)
- ✅ Trait functionality (CanImportExcelRecords)
- ✅ Action classes (FullImportAction)
- ✅ Excel file parsing and reading
- ✅ Error handling and validation
- ✅ Multi-sheet support
- ✅ Large file processing
- ✅ Integration workflows

## Common Test Patterns

### Creating Test Excel Files

```php
$excelFile = ExcelTestHelper::createUserExcelFile([
    ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
    ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'password456'],
]);

try {
    // Test logic here
} finally {
    ExcelTestHelper::cleanup($excelFile);
}
```

### Testing Import Jobs

```php
$import = Import::create([
    'user_id' => $this->authUser->id,
    'file_name' => 'test.xlsx',
    'file_path' => $excelFile,
    'importer' => TestUserImporter::class,
    'total_rows' => 2,
]);

$job = new ImportExcel(
    importId: $import->id,
    rows: base64_encode(serialize($rows)),
    columnMap: $columnMap,
    options: []
);

$job->handle();
```

### Testing Validation Errors

```php
// Create data with validation errors
$rows = [
    ['name' => '', 'email' => 'invalid-email', 'password' => '123'], // Multiple errors
];

// Execute import and verify failed rows are recorded
$this->assertEquals(1, $import->failed_rows);
$this->assertEquals(1, FailedImportRow::count());
```

## Debugging Tests

### Verbose Output

```bash
# Run tests with verbose output
vendor/bin/phpunit --verbose

# Run tests with debug information
vendor/bin/phpunit --debug
```

### Test Specific Issues

1. **Memory Issues**: Large file tests may require increased memory limits
2. **Temporary Files**: Tests clean up temporary Excel files automatically
3. **Database State**: Each test runs in isolation with a fresh database

## Contributing

When adding new tests:

1. Follow the existing naming conventions
2. Use appropriate test categories (Unit vs Feature)
3. Include proper setup and teardown
4. Add documentation for complex test scenarios
5. Ensure tests are isolated and don't depend on each other

## Continuous Integration

The test suite is designed to run in CI environments:

- Uses in-memory SQLite for speed
- No external dependencies required
- Comprehensive error reporting
- Coverage reporting available 
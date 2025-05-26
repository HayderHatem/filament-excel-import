# Testing Guide for Filament Excel Import Package

This document provides comprehensive information about testing the Filament Excel Import package.

## 🧪 Test Suite Overview

The package includes a comprehensive test suite that covers all major functionality:

### Test Categories

1. **Unit Tests** (`tests/Unit/`)
   - Model functionality (Import, FailedImportRow)
   - Job processing (ImportExcel)
   - Basic functionality verification

2. **Feature Tests** (`tests/Feature/`)
   - Integration tests
   - Excel file processing
   - End-to-end workflows

3. **Helper Classes** (`tests/Helpers/`)
   - ExcelTestHelper for creating test files
   - Utility functions for testing

## 🚀 Quick Start

### Running Tests

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test categories
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Feature

# Run a specific test file
vendor/bin/phpunit tests/Unit/SimpleImportTest.php

# Run with detailed output
vendor/bin/phpunit --testdox
```

### Quick Functionality Test

Run the included test runner to verify basic functionality:

```bash
php test-runner.php
```

This will test:
- ✅ Excel file creation with user data
- ✅ Multi-sheet Excel file support
- ✅ Invalid data handling
- ✅ Large file processing
- ✅ PhpSpreadsheet integration

## 📊 Test Results

### Current Test Status

As of the latest version, the package includes:

- **6 Unit Tests** - All passing ✅
- **Basic functionality** - Verified ✅
- **Excel file creation** - Working ✅
- **Model relationships** - Functional ✅
- **JSON casting** - Working ✅

### Working Features

✅ **Import Model**
- Custom Import model extending Filament's Import
- Failed rows relationship
- Failed rows counting
- Proper table mapping

✅ **FailedImportRow Model**
- JSON casting for data and validation_errors
- Relationship with Import model
- Null handling for validation errors

✅ **Excel File Processing**
- Multiple Excel formats support (.xlsx, .xls, .xlsm, etc.)
- Multi-sheet file creation and handling
- Large file processing (tested with 100+ rows)
- Invalid data handling

✅ **Test Helpers**
- ExcelTestHelper with multiple file creation methods
- Automatic cleanup functionality
- Performance testing capabilities

## 🔧 Test Configuration

### PHPUnit Configuration

The package uses PHPUnit 10.x with the following configuration:

```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <testsuite name="Package Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <!-- Coverage and logging configuration -->
</phpunit>
```

### Test Environment

Tests run with:
- **Database**: In-memory SQLite
- **Storage**: Fake local storage
- **Queue**: Sync driver
- **Cache**: Array driver

### Dependencies

Test dependencies include:
- `orchestra/testbench` - Laravel package testing
- `phpunit/phpunit` - Testing framework
- `mockery/mockery` - Mocking framework

## 📝 Writing Tests

### Test Structure

```php
<?php

namespace HayderHatem\FilamentExcelImport\Tests\Unit;

use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_does_something()
    {
        // Arrange
        $data = ['test' => 'data'];

        // Act
        $result = $this->performAction($data);

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Using Test Helpers

```php
use HayderHatem\FilamentExcelImport\Tests\Helpers\ExcelTestHelper;

// Create test Excel files
$excelFile = ExcelTestHelper::createUserExcelFile([
    ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'],
]);

try {
    // Your test logic here
} finally {
    ExcelTestHelper::cleanup($excelFile);
}
```

### Testing Models

```php
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Models\FailedImportRow;

// Test Import model
$import = Import::create([
    'file_name' => 'test.xlsx',
    'file_path' => '/tmp/test.xlsx',
    'importer' => TestUserImporter::class,
    'total_rows' => 10,
]);

// Test relationships
$failedRow = FailedImportRow::create([
    'import_id' => $import->id,
    'data' => ['name' => 'John'],
    'validation_errors' => ['email' => ['Required']],
    'error' => 'Validation failed',
]);

$this->assertCount(1, $import->failedRows);
```

## 🐛 Troubleshooting

### Common Issues

1. **Livewire Service Provider Error**
   - **Issue**: `Target class [livewire] does not exist`
   - **Solution**: Ensure LivewireServiceProvider is registered in TestCase

2. **Migration Errors**
   - **Issue**: Tables not found during tests
   - **Solution**: Check migration files are properly included in TestCase

3. **Memory Issues with Large Files**
   - **Issue**: Tests fail with memory exhaustion
   - **Solution**: Increase PHP memory limit or reduce test file sizes

4. **PhpSpreadsheet Errors**
   - **Issue**: Excel file creation fails
   - **Solution**: Ensure PhpSpreadsheet is properly installed

### Debug Mode

Enable debug mode for detailed test output:

```bash
vendor/bin/phpunit --debug --verbose
```

### Test Coverage

Generate coverage reports:

```bash
vendor/bin/phpunit --coverage-html coverage
```

## 🔄 Continuous Integration

### GitHub Actions

The package includes GitHub Actions workflow (`.github/workflows/tests.yml`) that:

- Tests multiple PHP versions (8.1, 8.2, 8.3)
- Tests multiple Laravel versions (10.x, 11.x)
- Runs code style checks
- Performs static analysis
- Generates coverage reports

### Local CI Testing

Test the CI configuration locally:

```bash
# Test with different dependency versions
composer update --prefer-lowest
vendor/bin/phpunit

composer update --prefer-stable
vendor/bin/phpunit
```

## 📈 Performance Testing

### Benchmarking

The test suite includes performance benchmarks:

```php
// Large file processing test
$start = microtime(true);
$largeFile = ExcelTestHelper::createLargeExcelFile(1000);
$end = microtime(true);

echo "Creation time: " . (($end - $start) * 1000) . " ms\n";
```

### Memory Usage

Monitor memory usage during tests:

```bash
vendor/bin/phpunit --verbose | grep Memory
```

## 🎯 Test Goals

### Coverage Goals

- **Unit Tests**: 90%+ coverage of core functionality
- **Feature Tests**: Cover all major user workflows
- **Integration Tests**: Verify package works with Filament

### Quality Goals

- All tests must pass consistently
- No memory leaks in large file processing
- Performance benchmarks within acceptable ranges
- Comprehensive error handling coverage

## 📚 Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel Testing](https://laravel.com/docs/testing)
- [Orchestra Testbench](https://github.com/orchestral/testbench)
- [Filament Testing](https://filamentphp.com/docs/panels/testing)

## 🤝 Contributing Tests

When contributing to the package:

1. Write tests for new features
2. Ensure existing tests still pass
3. Follow the established test patterns
4. Update this documentation if needed
5. Include performance considerations

### Test Checklist

- [ ] Unit tests for new models/classes
- [ ] Feature tests for new workflows
- [ ] Error handling tests
- [ ] Performance tests for large data
- [ ] Documentation updates
- [ ] CI pipeline passes 
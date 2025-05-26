<?php

namespace HayderHatem\FilamentExcelImport\Tests\Unit;

use HayderHatem\FilamentExcelImport\Models\FailedImportRow;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SimpleImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_import_and_failed_import_row_models()
    {
        // Test Import model creation
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 10,
        ]);

        $this->assertInstanceOf(Import::class, $import);
        $this->assertEquals('test.xlsx', $import->file_name);
        $this->assertEquals(TestUserImporter::class, $import->importer);

        // Test FailedImportRow model creation
        $failedRow = FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => 'John', 'email' => 'invalid-email'],
            'validation_errors' => ['email' => ['The email must be a valid email address.']],
            'error' => 'Validation failed',
        ]);

        $this->assertInstanceOf(FailedImportRow::class, $failedRow);
        $this->assertEquals($import->id, $failedRow->import_id);
        $this->assertIsArray($failedRow->data);
        $this->assertIsArray($failedRow->validation_errors);

        // Test relationship
        $this->assertCount(1, $import->failedRows);
        $this->assertEquals($failedRow->id, $import->failedRows->first()->id);

        // Test failed rows count
        $this->assertEquals(1, $import->getFailedRowsCount());
    }

    /** @test */
    public function it_can_handle_json_casting()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 1,
        ]);

        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $errors = ['email' => ['Invalid format'], 'name' => ['Too short']];

        $failedRow = FailedImportRow::create([
            'import_id' => $import->id,
            'data' => $data,
            'validation_errors' => $errors,
            'error' => 'Multiple validation errors',
        ]);

        // Refresh from database to test JSON casting
        $failedRow->refresh();

        $this->assertEquals($data, $failedRow->data);
        $this->assertEquals($errors, $failedRow->validation_errors);
        $this->assertIsArray($failedRow->data);
        $this->assertIsArray($failedRow->validation_errors);
    }

    /** @test */
    public function it_can_handle_null_validation_errors()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 1,
        ]);

        $failedRow = FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => 'John', 'email' => 'john@example.com'],
            'validation_errors' => null,
            'error' => 'System error',
        ]);

        $this->assertNull($failedRow->validation_errors);
        $this->assertEquals('System error', $failedRow->error);
    }

    /** @test */
    public function it_extends_filament_import_model()
    {
        $import = new Import();
        $this->assertInstanceOf(\Filament\Actions\Imports\Models\Import::class, $import);
    }

    /** @test */
    public function import_model_has_correct_table_name()
    {
        $import = new Import();
        $this->assertEquals('imports', $import->getTable());
    }

    /** @test */
    public function failed_import_row_model_has_correct_table_name()
    {
        $failedRow = new FailedImportRow();
        $this->assertEquals('failed_import_rows', $failedRow->getTable());
    }
}

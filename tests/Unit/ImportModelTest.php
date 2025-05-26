<?php

namespace HayderHatem\FilamentExcelImport\Tests\Unit;

use HayderHatem\FilamentExcelImport\Models\FailedImportRow;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\Models\User;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ImportModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_import_record()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 10,
            'processed_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
        ]);

        $this->assertInstanceOf(Import::class, $import);
        $this->assertEquals('test.xlsx', $import->file_name);
        $this->assertEquals(TestUserImporter::class, $import->importer);
        $this->assertEquals(10, $import->total_rows);
    }

    #[Test]
    public function it_has_failed_rows_relationship()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 10,
        ]);

        $failedRow = FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => 'John', 'email' => 'invalid-email'],
            'validation_errors' => ['email' => ['The email must be a valid email address.']],
            'error' => 'Validation failed',
        ]);

        $this->assertCount(1, $import->failedRows);
        $this->assertEquals($failedRow->id, $import->failedRows->first()->id);
    }

    #[Test]
    public function it_can_count_failed_rows()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 10,
        ]);

        // Create multiple failed rows
        FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => 'John', 'email' => 'invalid-email'],
            'validation_errors' => ['email' => ['The email must be a valid email address.']],
            'error' => 'Validation failed',
        ]);

        FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => '', 'email' => 'test@example.com'],
            'validation_errors' => ['name' => ['The name field is required.']],
            'error' => 'Validation failed',
        ]);

        $this->assertEquals(2, $import->getFailedRowsCount());
    }

    #[Test]
    public function it_can_get_importer_instance()
    {
        $import = Import::create([
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 10,
        ]);

        $importer = $import->getImporter(
            columnMap: ['name' => 'Name', 'email' => 'Email', 'password' => 'Password'],
            options: []
        );

        $this->assertInstanceOf(TestUserImporter::class, $importer);
    }

    #[Test]
    public function it_extends_filament_import_model()
    {
        $import = new Import();

        $this->assertInstanceOf(\Filament\Actions\Imports\Models\Import::class, $import);
    }
}

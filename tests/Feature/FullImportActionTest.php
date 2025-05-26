<?php

namespace HayderHatem\FilamentExcelImport\Tests\Feature;

use HayderHatem\FilamentExcelImport\Actions\FullImportAction;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FullImportActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** @test */
    public function it_can_create_full_import_action()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class);

        $this->assertInstanceOf(FullImportAction::class, $action);
        $this->assertEquals(TestUserImporter::class, $action->getImporter());
    }

    /** @test */
    public function it_can_configure_header_row()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->headerRow(3);

        $this->assertEquals(3, $action->getHeaderRow());
    }

    /** @test */
    public function it_can_configure_active_sheet()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->activeSheet(1);

        $this->assertEquals(1, $action->getActiveSheet());
    }

    /** @test */
    public function it_can_configure_chunk_size()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->chunkSize(100);

        $this->assertEquals(100, $action->getChunkSize());
    }

    /** @test */
    public function it_can_configure_max_rows()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->maxRows(1000);

        $this->assertEquals(1000, $action->getMaxRows());
    }

    /** @test */
    public function it_can_configure_options()
    {
        $options = ['update_existing' => true, 'skip_duplicates' => false];

        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->options($options);

        $this->assertEquals($options, $action->getOptions());
    }

    /** @test */
    public function it_can_configure_file_validation_rules()
    {
        $rules = ['max:10240', 'mimes:xlsx,xls'];

        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->fileValidationRules($rules);

        $this->assertEquals($rules, $action->getFileValidationRules());
    }

    /** @test */
    public function it_accepts_excel_file_formats()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class);

        // Test various Excel formats
        $this->assertTrue($action->acceptsFileType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')); // .xlsx
        $this->assertTrue($action->acceptsFileType('application/vnd.ms-excel')); // .xls
        $this->assertTrue($action->acceptsFileType('application/vnd.ms-excel.sheet.macroEnabled.12')); // .xlsm
    }

    /** @test */
    public function it_has_correct_default_values()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class);

        $this->assertEquals(1, $action->getHeaderRow()); // Default header row
        $this->assertEquals(0, $action->getActiveSheet()); // Default active sheet
        $this->assertEquals(25, $action->getChunkSize()); // Default chunk size
        $this->assertNull($action->getMaxRows()); // No max rows by default
        $this->assertEquals([], $action->getOptions()); // Empty options by default
    }

    /** @test */
    public function it_can_chain_configuration_methods()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class)
            ->headerRow(2)
            ->activeSheet(1)
            ->chunkSize(50)
            ->maxRows(500)
            ->options(['test' => true])
            ->fileValidationRules(['max:5120']);

        $this->assertEquals(TestUserImporter::class, $action->getImporter());
        $this->assertEquals(2, $action->getHeaderRow());
        $this->assertEquals(1, $action->getActiveSheet());
        $this->assertEquals(50, $action->getChunkSize());
        $this->assertEquals(500, $action->getMaxRows());
        $this->assertEquals(['test' => true], $action->getOptions());
        $this->assertEquals(['max:5120'], $action->getFileValidationRules());
    }

    /** @test */
    public function it_extends_filament_import_action()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class);

        $this->assertInstanceOf(\Filament\Actions\ImportAction::class, $action);
    }

    /** @test */
    public function it_uses_excel_import_trait()
    {
        $action = FullImportAction::make()
            ->importer(TestUserImporter::class);

        $traits = class_uses_recursive(get_class($action));
        $this->assertContains('HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords', $traits);
    }

    protected function createTestExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Data
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', 'john@example.com');
        $sheet->setCellValue('C2', 'password123');

        $sheet->setCellValue('A3', 'Jane Smith');
        $sheet->setCellValue('B3', 'jane@example.com');
        $sheet->setCellValue('C3', 'password456');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}

<?php

namespace HayderHatem\FilamentExcelImport\Tests\Feature;

use HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CanImportExcelRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** @test */
    public function it_can_detect_excel_file_format()
    {
        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        $this->assertTrue($trait->isExcelFile('test.xlsx'));
        $this->assertTrue($trait->isExcelFile('test.xls'));
        $this->assertTrue($trait->isExcelFile('test.xlsm'));
        $this->assertTrue($trait->isExcelFile('test.xlsb'));
        $this->assertFalse($trait->isExcelFile('test.csv'));
        $this->assertFalse($trait->isExcelFile('test.txt'));
    }

    /** @test */
    public function it_can_read_excel_file_and_get_sheets()
    {
        // Create a test Excel file
        $spreadsheet = new Spreadsheet();

        // First sheet
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Users');
        $sheet1->setCellValue('A1', 'Name');
        $sheet1->setCellValue('B1', 'Email');
        $sheet1->setCellValue('C1', 'Password');
        $sheet1->setCellValue('A2', 'John Doe');
        $sheet1->setCellValue('B2', 'john@example.com');
        $sheet1->setCellValue('C2', 'password123');

        // Second sheet
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Products');
        $sheet2->setCellValue('A1', 'Product Name');
        $sheet2->setCellValue('B1', 'Price');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        // Test getting sheet names
        $sheets = $trait->getExcelSheets($tempFile);
        $this->assertCount(2, $sheets);
        $this->assertEquals(['Users', 'Products'], array_values($sheets));

        // Test reading data from specific sheet
        $data = $trait->readExcelFile($tempFile, 0, 1); // Sheet 0, header row 1
        $this->assertCount(1, $data); // One data row
        $this->assertEquals('John Doe', $data[0]['Name']);
        $this->assertEquals('john@example.com', $data[0]['Email']);
        $this->assertEquals('password123', $data[0]['Password']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_multiple_header_rows()
    {
        // Create a test Excel file with headers on row 3
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Some metadata rows
        $sheet->setCellValue('A1', 'Company: Test Corp');
        $sheet->setCellValue('A2', 'Report Date: 2024-01-01');

        // Headers on row 3
        $sheet->setCellValue('A3', 'Name');
        $sheet->setCellValue('B3', 'Email');
        $sheet->setCellValue('C3', 'Password');

        // Data rows
        $sheet->setCellValue('A4', 'John Doe');
        $sheet->setCellValue('B4', 'john@example.com');
        $sheet->setCellValue('C4', 'password123');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        // Test reading data with header row 3
        $data = $trait->readExcelFile($tempFile, 0, 3); // Sheet 0, header row 3
        $this->assertCount(1, $data); // One data row
        $this->assertEquals('John Doe', $data[0]['Name']);
        $this->assertEquals('john@example.com', $data[0]['Email']);
        $this->assertEquals('password123', $data[0]['Password']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_empty_cells_gracefully()
    {
        // Create a test Excel file with empty cells
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Row with empty cells
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', ''); // Empty email
        $sheet->setCellValue('C2', 'password123');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        $data = $trait->readExcelFile($tempFile, 0, 1);
        $this->assertCount(1, $data);
        $this->assertEquals('John Doe', $data[0]['Name']);
        $this->assertEquals('', $data[0]['Email']); // Empty string for empty cell
        $this->assertEquals('password123', $data[0]['Password']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_can_process_large_excel_files_in_chunks()
    {
        // Create a test Excel file with many rows
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Add 100 rows of data
        for ($i = 2; $i <= 101; $i++) {
            $sheet->setCellValue('A' . $i, 'User ' . ($i - 1));
            $sheet->setCellValue('B' . $i, 'user' . ($i - 1) . '@example.com');
            $sheet->setCellValue('C' . $i, 'password123');
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        $data = $trait->readExcelFile($tempFile, 0, 1);
        $this->assertCount(100, $data); // 100 data rows
        $this->assertEquals('User 1', $data[0]['Name']);
        $this->assertEquals('user1@example.com', $data[0]['Email']);
        $this->assertEquals('User 100', $data[99]['Name']);
        $this->assertEquals('user100@example.com', $data[99]['Email']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_corrupted_excel_files()
    {
        // Create a fake corrupted file
        $tempFile = tempnam(sys_get_temp_dir(), 'corrupted_excel_');
        file_put_contents($tempFile, 'This is not an Excel file');

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        $this->expectException(\Exception::class);
        $trait->readExcelFile($tempFile, 0, 1);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_validates_sheet_index()
    {
        // Create a test Excel file with one sheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Name');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $trait = $this->getMockForTrait(CanImportExcelRecords::class);

        // Try to access non-existent sheet
        $this->expectException(\Exception::class);
        $trait->readExcelFile($tempFile, 5, 1); // Sheet 5 doesn't exist

        // Clean up
        unlink($tempFile);
    }
}

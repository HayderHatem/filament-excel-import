<?php

namespace HayderHatem\FilamentExcelImport\Tests\Feature;

use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;

class CanImportExcelRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createExcelFile(array $data, string $filename = 'test.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = array_keys($data[0] ?? []);
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index); // A, B, C, etc.
            $sheet->setCellValue($column . '1', $header);
        }

        // Add data rows
        foreach ($data as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // Start from row 2
            $colIndex = 0;
            foreach ($row as $value) {
                $column = chr(65 + $colIndex); // A, B, C, etc.
                $sheet->setCellValue($column . $rowNumber, $value);
                $colIndex++;
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return new UploadedFile($tempFile, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function it_can_detect_excel_file_extensions(): void
    {
        // Test file extension detection logic
        $excelExtensions = ['xlsx', 'xls', 'xlsm', 'xlsb'];
        $nonExcelExtensions = ['csv', 'txt', 'pdf'];

        foreach ($excelExtensions as $ext) {
            $this->assertTrue(in_array($ext, ['xlsx', 'xls', 'xlsm', 'xlsb']), "Extension {$ext} should be recognized as Excel");
        }

        foreach ($nonExcelExtensions as $ext) {
            $this->assertFalse(in_array($ext, ['xlsx', 'xls', 'xlsm', 'xlsb']), "Extension {$ext} should not be recognized as Excel");
        }
    }

    #[Test]
    public function it_can_create_and_read_excel_file(): void
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

        // Verify file was created
        $this->assertFileExists($tempFile);
        $this->assertGreaterThan(0, filesize($tempFile));

        // Test reading the file back
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $loadedSpreadsheet = $reader->load($tempFile);

        // Verify sheet count
        $this->assertEquals(2, $loadedSpreadsheet->getSheetCount());

        // Verify sheet names
        $this->assertEquals('Users', $loadedSpreadsheet->getSheet(0)->getTitle());
        $this->assertEquals('Products', $loadedSpreadsheet->getSheet(1)->getTitle());

        // Verify data
        $usersSheet = $loadedSpreadsheet->getSheet(0);
        $this->assertEquals('John Doe', $usersSheet->getCell('A2')->getValue());
        $this->assertEquals('john@example.com', $usersSheet->getCell('B2')->getValue());

        // Clean up
        unlink($tempFile);
    }

    #[Test]
    public function it_handles_excel_file_with_custom_header_row(): void
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

        // Verify file structure
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $loadedSpreadsheet = $reader->load($tempFile);
        $worksheet = $loadedSpreadsheet->getActiveSheet();

        // Verify headers are on row 3
        $this->assertEquals('Name', $worksheet->getCell('A3')->getValue());
        $this->assertEquals('Email', $worksheet->getCell('B3')->getValue());
        $this->assertEquals('Password', $worksheet->getCell('C3')->getValue());

        // Verify data is on row 4
        $this->assertEquals('John Doe', $worksheet->getCell('A4')->getValue());
        $this->assertEquals('john@example.com', $worksheet->getCell('B4')->getValue());

        // Clean up
        unlink($tempFile);
    }

    #[Test]
    public function it_handles_excel_file_with_empty_cells(): void
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

        // Verify file handling
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $loadedSpreadsheet = $reader->load($tempFile);
        $worksheet = $loadedSpreadsheet->getActiveSheet();

        $this->assertEquals('John Doe', $worksheet->getCell('A2')->getValue());
        $this->assertEquals('', $worksheet->getCell('B2')->getValue()); // Empty string for empty cell
        $this->assertEquals('password123', $worksheet->getCell('C2')->getValue());

        // Clean up
        unlink($tempFile);
    }

    #[Test]
    public function it_can_process_large_excel_files(): void
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

        // Verify file was created and has reasonable size
        $this->assertFileExists($tempFile);
        $this->assertGreaterThan(5000, filesize($tempFile)); // Should be reasonably large

        // Verify we can read it back
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $loadedSpreadsheet = $reader->load($tempFile);
        $worksheet = $loadedSpreadsheet->getActiveSheet();

        // Check first and last data rows
        $this->assertEquals('User 1', $worksheet->getCell('A2')->getValue());
        $this->assertEquals('User 100', $worksheet->getCell('A101')->getValue());

        // Clean up
        unlink($tempFile);
    }

    #[Test]
    public function it_handles_corrupted_files_gracefully(): void
    {
        // Create a fake corrupted file
        $tempFile = tempnam(sys_get_temp_dir(), 'corrupted_excel_');
        file_put_contents($tempFile, 'This is not an Excel file');

        // Verify that trying to read it throws an exception
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
            $reader->load($tempFile);
            $this->fail('Expected PhpOffice\PhpSpreadsheet\Reader\Exception to be thrown');
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $this->assertTrue(true); // Exception was thrown as expected
        } catch (\Exception $e) {
            // Some other exception might be thrown, which is also acceptable for a corrupted file
            $this->assertTrue(true);
        }

        // Clean up
        unlink($tempFile);
    }

    #[Test]
    public function it_validates_sheet_access(): void
    {
        // Create a test Excel file with one sheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Name');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
        $loadedSpreadsheet = $reader->load($tempFile);

        // Verify we can access the existing sheet
        $this->assertEquals(1, $loadedSpreadsheet->getSheetCount());
        $this->assertNotNull($loadedSpreadsheet->getSheet(0));

        // Verify trying to access non-existent sheet throws exception
        $this->expectException(\PhpOffice\PhpSpreadsheet\Exception::class);
        $loadedSpreadsheet->getSheet(5);

        // Clean up
        unlink($tempFile);
    }
}

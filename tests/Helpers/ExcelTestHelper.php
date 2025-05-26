<?php

namespace HayderHatem\FilamentExcelImport\Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelTestHelper
{
    /**
     * Create a simple Excel file with user data
     */
    public static function createUserExcelFile(array $users = null): string
    {
        $users ??= [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'password456'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'password' => 'password789'],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Data rows
        foreach ($users as $index => $user) {
            $row = $index + 2; // Start from row 2
            $sheet->setCellValue('A' . $row, $user['name']);
            $sheet->setCellValue('B' . $row, $user['email']);
            $sheet->setCellValue('C' . $row, $user['password']);
        }

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create an Excel file with multiple sheets
     */
    public static function createMultiSheetExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();

        // First sheet - Users
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Users');
        $sheet1->setCellValue('A1', 'Name');
        $sheet1->setCellValue('B1', 'Email');
        $sheet1->setCellValue('C1', 'Password');
        $sheet1->setCellValue('A2', 'John Doe');
        $sheet1->setCellValue('B2', 'john@example.com');
        $sheet1->setCellValue('C2', 'password123');

        // Second sheet - Products
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Products');
        $sheet2->setCellValue('A1', 'Product Name');
        $sheet2->setCellValue('B1', 'Price');
        $sheet2->setCellValue('C1', 'Category');
        $sheet2->setCellValue('A2', 'Laptop');
        $sheet2->setCellValue('B2', '999.99');
        $sheet2->setCellValue('C2', 'Electronics');

        // Third sheet - Orders
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Orders');
        $sheet3->setCellValue('A1', 'Order ID');
        $sheet3->setCellValue('B1', 'Customer');
        $sheet3->setCellValue('C1', 'Total');
        $sheet3->setCellValue('A2', 'ORD001');
        $sheet3->setCellValue('B2', 'John Doe');
        $sheet3->setCellValue('C2', '999.99');

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create an Excel file with headers on a specific row
     */
    public static function createExcelFileWithCustomHeaderRow(int $headerRow = 3): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add some metadata rows before headers
        $sheet->setCellValue('A1', 'Company: Test Corporation');
        $sheet->setCellValue('A2', 'Report Date: ' . date('Y-m-d'));

        // Headers on the specified row
        $sheet->setCellValue('A' . $headerRow, 'Name');
        $sheet->setCellValue('B' . $headerRow, 'Email');
        $sheet->setCellValue('C' . $headerRow, 'Password');

        // Data rows
        $dataRow = $headerRow + 1;
        $sheet->setCellValue('A' . $dataRow, 'John Doe');
        $sheet->setCellValue('B' . $dataRow, 'john@example.com');
        $sheet->setCellValue('C' . $dataRow, 'password123');

        $dataRow++;
        $sheet->setCellValue('A' . $dataRow, 'Jane Smith');
        $sheet->setCellValue('B' . $dataRow, 'jane@example.com');
        $sheet->setCellValue('C' . $dataRow, 'password456');

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create an Excel file with invalid data for testing validation
     */
    public static function createInvalidDataExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Valid row
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', 'john@example.com');
        $sheet->setCellValue('C2', 'password123');

        // Invalid rows
        $sheet->setCellValue('A3', ''); // Missing name
        $sheet->setCellValue('B3', 'jane@example.com');
        $sheet->setCellValue('C3', 'password456');

        $sheet->setCellValue('A4', 'Bob Johnson');
        $sheet->setCellValue('B4', 'invalid-email'); // Invalid email
        $sheet->setCellValue('C4', 'password789');

        $sheet->setCellValue('A5', 'Alice Brown');
        $sheet->setCellValue('B5', 'alice@example.com');
        $sheet->setCellValue('C5', '123'); // Password too short

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create an Excel file with empty cells
     */
    public static function createExcelFileWithEmptyCells(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Row with empty cells
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', ''); // Empty email
        $sheet->setCellValue('C2', 'password123');

        // Row with null values
        $sheet->setCellValue('A3', '');
        $sheet->setCellValue('B3', 'jane@example.com');
        $sheet->setCellValue('C3', '');

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create a large Excel file for performance testing
     */
    public static function createLargeExcelFile(int $rowCount = 1000): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Password');

        // Generate data rows
        for ($i = 2; $i <= $rowCount + 1; $i++) {
            $userNumber = $i - 1;
            $sheet->setCellValue('A' . $i, 'User ' . $userNumber);
            $sheet->setCellValue('B' . $i, 'user' . $userNumber . '@example.com');
            $sheet->setCellValue('C' . $i, 'password' . $userNumber);
        }

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * Create a corrupted file (not actually an Excel file)
     */
    public static function createCorruptedFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'corrupted_excel_');
        file_put_contents($tempFile, 'This is not an Excel file content');

        return $tempFile;
    }

    /**
     * Save spreadsheet to temporary file
     */
    private static function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Clean up temporary file
     */
    public static function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

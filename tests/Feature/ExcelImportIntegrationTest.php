<?php

namespace HayderHatem\FilamentExcelImport\Tests\Feature;

use HayderHatem\FilamentExcelImport\Actions\Imports\Jobs\ImportExcel;
use HayderHatem\FilamentExcelImport\Models\FailedImportRow;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\Models\User;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;

class ExcelImportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user
        $this->authUser = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        Auth::login($this->authUser);
    }

    private function createUserExcelFile(array $users): string
    {
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

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function createInvalidDataExcelFile(): string
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

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function createMultiSheetExcelFile(): string
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

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function createExcelFileWithEmptyCells(): string
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

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function createLargeExcelFile(int $rowCount): string
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

        $tempFile = tempnam(sys_get_temp_dir(), 'test_excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    #[Test]
    public function it_can_import_users_from_excel_file_successfully()
    {
        // Create test Excel file
        $excelFile = $this->createUserExcelFile([
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'password456'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'password' => 'password789'],
        ]);

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'users.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 3,
            ]);

            // Prepare data for import job
            $rows = [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'password456'],
                ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'password' => 'password789'],
            ];

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: []
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions
            $this->assertEquals(3, $import->processed_rows);
            $this->assertEquals(3, $import->imported_rows);
            $this->assertEquals(0, $import->failed_rows);
            $this->assertEquals(0, $import->getFailedRowsCount());

            // Check that users were created (excluding auth user)
            $this->assertEquals(4, User::count()); // 3 imported + 1 auth user
            $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
            $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
            $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_handles_validation_errors_during_import()
    {
        // Create test Excel file with invalid data
        $excelFile = $this->createInvalidDataExcelFile();

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'invalid_users.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 4,
            ]);

            // Prepare data for import job (including invalid data)
            $rows = [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'], // Valid
                ['name' => '', 'email' => 'jane@example.com', 'password' => 'password456'], // Invalid: empty name
                ['name' => 'Bob Johnson', 'email' => 'invalid-email', 'password' => 'password789'], // Invalid: bad email
                ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'password' => '123'], // Invalid: short password
            ];

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: []
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions
            $this->assertEquals(4, $import->processed_rows);
            $this->assertEquals(1, $import->imported_rows); // Only John Doe should be imported
            $this->assertEquals(3, $import->failed_rows);
            $this->assertEquals(3, $import->getFailedRowsCount());

            // Check that only valid user was created
            $this->assertEquals(2, User::count()); // 1 imported + 1 auth user
            $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
            $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
            $this->assertDatabaseMissing('users', ['email' => 'invalid-email']);
            $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);

            // Check that failed rows were recorded
            $this->assertEquals(3, FailedImportRow::count());
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_handles_duplicate_email_validation()
    {
        // Create test Excel file with duplicate emails
        $excelFile = $this->createUserExcelFile([
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ['name' => 'Jane Smith', 'email' => 'john@example.com', 'password' => 'password456'], // Duplicate email
        ]);

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'duplicate_users.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 2,
            ]);

            // Prepare data for import job
            $rows = [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
                ['name' => 'Jane Smith', 'email' => 'john@example.com', 'password' => 'password456'],
            ];

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: []
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions
            $this->assertEquals(2, $import->processed_rows);
            $this->assertEquals(1, $import->imported_rows); // Only first user should be imported
            $this->assertEquals(1, $import->failed_rows);
            $this->assertEquals(1, $import->getFailedRowsCount());

            // Check that only first user was created
            $this->assertEquals(2, User::count()); // 1 imported + 1 auth user
            $this->assertDatabaseHas('users', ['name' => 'John Doe', 'email' => 'john@example.com']);

            // Check that failed row was recorded
            $failedRow = FailedImportRow::first();
            $this->assertEquals($import->id, $failedRow->import_id);
            $this->assertEquals(['name' => 'Jane Smith', 'email' => 'john@example.com', 'password' => 'password456'], $failedRow->data);
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_can_import_from_specific_sheet()
    {
        // Create multi-sheet Excel file
        $excelFile = $this->createMultiSheetExcelFile();

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'multi_sheet.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 1,
            ]);

            // Prepare data from Users sheet (sheet 0)
            $rows = [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ];

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: ['sheet' => 0] // Specify sheet 0 (Users)
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions
            $this->assertEquals(1, $import->processed_rows);
            $this->assertEquals(1, $import->imported_rows);
            $this->assertEquals(0, $import->failed_rows);

            // Check that user was created
            $this->assertEquals(2, User::count()); // 1 imported + 1 auth user
            $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_handles_empty_cells_gracefully()
    {
        // Create Excel file with empty cells
        $excelFile = $this->createExcelFileWithEmptyCells();

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'empty_cells.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 2,
            ]);

            // Prepare data with empty cells
            $rows = [
                ['name' => 'John Doe', 'email' => '', 'password' => 'password123'], // Empty email
                ['name' => '', 'email' => 'jane@example.com', 'password' => ''], // Empty name and password
            ];

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: []
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions - both rows should fail validation
            $this->assertEquals(2, $import->processed_rows);
            $this->assertEquals(0, $import->imported_rows);
            $this->assertEquals(2, $import->failed_rows);
            $this->assertEquals(2, $import->getFailedRowsCount());

            // Check that no users were created (except auth user)
            $this->assertEquals(1, User::count()); // Only auth user

            // Check that failed rows were recorded
            $this->assertEquals(2, FailedImportRow::count());
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_can_process_large_imports_in_chunks()
    {
        // Create large Excel file
        $excelFile = $this->createLargeExcelFile(100); // 100 rows

        try {
            // Create import record
            $import = Import::create([
                'user_id' => $this->authUser->id,
                'file_name' => 'large_import.xlsx',
                'file_path' => $excelFile,
                'importer' => TestUserImporter::class,
                'total_rows' => 100,
            ]);

            // Prepare data (simulate first chunk of 25 rows)
            $rows = [];
            for ($i = 1; $i <= 25; $i++) {
                $rows[] = [
                    'name' => 'User ' . $i,
                    'email' => 'user' . $i . '@example.com',
                    'password' => 'password' . $i,
                ];
            }

            $columnMap = [
                'name' => 'name',
                'email' => 'email',
                'password' => 'password',
            ];

            // Execute import job for first chunk
            $job = new ImportExcel(
                importId: $import->id,
                rows: base64_encode(serialize($rows)),
                columnMap: $columnMap,
                options: []
            );

            $job->handle();

            // Refresh import model
            $import->refresh();

            // Assertions for first chunk
            $this->assertEquals(25, $import->processed_rows);
            $this->assertEquals(25, $import->imported_rows);
            $this->assertEquals(0, $import->failed_rows);

            // Check that users were created
            $this->assertEquals(26, User::count()); // 25 imported + 1 auth user
            $this->assertDatabaseHas('users', ['email' => 'user1@example.com']);
            $this->assertDatabaseHas('users', ['email' => 'user25@example.com']);
        } finally {
            $this->cleanup($excelFile);
        }
    }

    #[Test]
    public function it_generates_correct_completion_notification_message()
    {
        // Create import with some successful and failed rows
        $import = Import::create([
            'user_id' => $this->authUser->id,
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 3,
            'processed_rows' => 3,
            'imported_rows' => 2,
            'failed_rows' => 1,
        ]);

        // Create a failed row
        FailedImportRow::create([
            'import_id' => $import->id,
            'data' => ['name' => 'Invalid User', 'email' => 'invalid-email'],
            'validation_errors' => ['email' => ['The email must be a valid email address.']],
            'error' => 'Validation failed',
        ]);

        // Create Filament's Import model for the notification method
        $filamentImport = new \Filament\Actions\Imports\Models\Import();
        $filamentImport->id = $import->id;
        $filamentImport->successful_rows = 2;

        $message = TestUserImporter::getCompletedNotificationBody($filamentImport);

        $this->assertStringContainsString('2 users imported', $message);
        $this->assertStringContainsString('1 row failed', $message);
    }
}

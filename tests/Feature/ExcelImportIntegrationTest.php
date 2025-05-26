<?php

namespace HayderHatem\FilamentExcelImport\Tests\Feature;

use HayderHatem\FilamentExcelImport\Actions\Imports\Jobs\ImportExcel;
use HayderHatem\FilamentExcelImport\Models\FailedImportRow;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Helpers\ExcelTestHelper;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\Models\User;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

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

    /** @test */
    public function it_can_import_users_from_excel_file_successfully()
    {
        // Create test Excel file
        $excelFile = ExcelTestHelper::createUserExcelFile([
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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
    public function it_handles_validation_errors_during_import()
    {
        // Create test Excel file with invalid data
        $excelFile = ExcelTestHelper::createInvalidDataExcelFile();

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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
    public function it_handles_duplicate_email_validation()
    {
        // Create test Excel file with duplicate emails
        $excelFile = ExcelTestHelper::createUserExcelFile([
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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
    public function it_can_import_from_specific_sheet()
    {
        // Create multi-sheet Excel file
        $excelFile = ExcelTestHelper::createMultiSheetExcelFile();

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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
    public function it_handles_empty_cells_gracefully()
    {
        // Create Excel file with empty cells
        $excelFile = ExcelTestHelper::createExcelFileWithEmptyCells();

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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
    public function it_can_process_large_imports_in_chunks()
    {
        // Create large Excel file
        $excelFile = ExcelTestHelper::createLargeExcelFile(100); // 100 rows

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
            ExcelTestHelper::cleanup($excelFile);
        }
    }

    /** @test */
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

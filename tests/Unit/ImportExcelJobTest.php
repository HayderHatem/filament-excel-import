<?php

namespace HayderHatem\FilamentExcelImport\Tests\Unit;

use HayderHatem\FilamentExcelImport\Actions\Imports\Jobs\ImportExcel;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Importers\TestUserImporter;
use HayderHatem\FilamentExcelImport\Tests\Models\User;
use HayderHatem\FilamentExcelImport\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class ImportExcelJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_process_import_rows_successfully()
    {
        // Create a test user for authentication
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        Auth::login($user);

        // Create an import record
        $import = Import::create([
            'user_id' => $user->id,
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 2,
        ]);

        // Prepare test data
        $rows = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'password456'],
        ];

        $columnMap = [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ];

        // Create and execute the job
        $job = new ImportExcel(
            importId: $import->id,
            rows: base64_encode(serialize($rows)),
            columnMap: $columnMap,
            options: []
        );

        $job->handle();

        // Refresh the import model
        $import->refresh();

        // Assert that users were created
        $this->assertEquals(2, User::count() - 1); // Subtract 1 for the auth user
        $this->assertEquals(2, $import->processed_rows);
        $this->assertEquals(2, $import->imported_rows);
        $this->assertEquals(0, $import->failed_rows);

        // Check that users were actually created
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    /** @test */
    public function it_handles_failed_rows_gracefully()
    {
        // Create a test user for authentication
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        Auth::login($user);

        // Create an import record
        $import = Import::create([
            'user_id' => $user->id,
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 2,
        ]);

        // Prepare test data with one invalid row
        $rows = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
            ['name' => 'Jane Smith', 'email' => 'test@example.com', 'password' => 'password456'], // Duplicate email
        ];

        $columnMap = [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ];

        // Create and execute the job
        $job = new ImportExcel(
            importId: $import->id,
            rows: base64_encode(serialize($rows)),
            columnMap: $columnMap,
            options: []
        );

        $job->handle();

        // Refresh the import model
        $import->refresh();

        // Assert that one user was created and one failed
        $this->assertEquals(2, User::count()); // Auth user + 1 imported user
        $this->assertEquals(2, $import->processed_rows);
        $this->assertEquals(1, $import->imported_rows);
        $this->assertEquals(1, $import->failed_rows);
        $this->assertEquals(1, $import->getFailedRowsCount());

        // Check that the successful user was created
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        // Check that failed row was recorded
        $this->assertDatabaseHas('failed_import_rows', [
            'import_id' => $import->id,
        ]);
    }

    /** @test */
    public function it_skips_processing_when_batch_is_cancelled()
    {
        // Create a test user for authentication
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        Auth::login($user);

        // Create an import record
        $import = Import::create([
            'user_id' => $user->id,
            'file_name' => 'test.xlsx',
            'file_path' => '/tmp/test.xlsx',
            'importer' => TestUserImporter::class,
            'total_rows' => 1,
        ]);

        $rows = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123'],
        ];

        $columnMap = [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ];

        // Create a job with a mock batch that returns cancelled
        $job = new class ($import->id, base64_encode(serialize($rows)), $columnMap, []) extends ImportExcel {
            public function batch()
            {
                return new class () {
                    public function cancelled()
                    {
                        return true;
                    }
                };
            }
        };

        $job->handle();

        // Refresh the import model
        $import->refresh();

        // Assert that no processing occurred
        $this->assertEquals(1, User::count()); // Only the auth user
        $this->assertEquals(0, $import->processed_rows);
        $this->assertEquals(0, $import->imported_rows);
        $this->assertEquals(0, $import->failed_rows);
    }
}

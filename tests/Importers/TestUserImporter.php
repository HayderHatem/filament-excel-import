<?php

namespace HayderHatem\FilamentExcelImport\Tests\Importers;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use HayderHatem\FilamentExcelImport\Models\Import;
use HayderHatem\FilamentExcelImport\Tests\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TestUserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'unique:users,email']),
            ImportColumn::make('password')
                ->requiredMapping()
                ->rules(['required', 'string', 'min:8']),
        ];
    }

    public static function getLabel(): string
    {
        return 'Test User';
    }

    public function resolveRecord(): ?User
    {
        return new User();
    }

    public function import(array $data, array $map, array $options = []): void
    {
        // Validate the data before importing
        $rules = [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = $this->resolveRecord();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();
    }

    public static function getCompletedNotificationBody(\Filament\Actions\Imports\Models\Import $import): string
    {
        $customImport = Import::find($import->id);

        $body = 'Your test import has completed and ' .
            number_format($import->successful_rows ?? $customImport?->imported_rows ?? 0) . ' ' .
            str('user')->plural($import->successful_rows ?? $customImport?->imported_rows ?? 0) . ' imported.';

        if ($failedRowsCount = $customImport?->getFailedRowsCount() ?? 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' .
                str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}

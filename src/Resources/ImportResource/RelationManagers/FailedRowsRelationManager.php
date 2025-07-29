<?php

namespace HayderHatem\FilamentExcelImport\Resources\ImportResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Imports\Models\FailedImportRow;

class FailedRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'failedRows';

    protected static ?string $recordTitleAttribute = 'row_number';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('filament-excel-import::import.failed_rows');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament-excel-import::import.row_information'))
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label(__('filament-excel-import::import.row_number'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\Textarea::make('data')
                            ->label(__('filament-excel-import::import.row_data'))
                            ->rows(4)
                            ->disabled()
                            ->formatStateUsing(function (FailedImportRow $record): string {
                                if (is_array($record->data)) {
                                    return implode(', ', array_filter($record->data));
                                }
                                return (string) $record->data;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament-excel-import::import.error_information'))
                    ->schema([
                        Forms\Components\Textarea::make('validation_error')
                            ->label(__('filament-excel-import::import.validation_errors'))
                            ->rows(4)
                            ->disabled()
                            ->formatStateUsing(function (FailedImportRow $record): string {
                                if (is_array($record->validation_error)) {
                                    $formatted = [];
                                    foreach ($record->validation_error as $field => $errors) {
                                        if (is_array($errors)) {
                                            $formatted[] = $field . ': ' . implode(', ', $errors);
                                        } else {
                                            $formatted[] = $field . ': ' . $errors;
                                        }
                                    }
                                    return implode('; ', $formatted);
                                }
                                return (string) $record->validation_error;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('error')
                            ->label(__('filament-excel-import::import.general_errors'))
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('filament-excel-import::import.row_number'))
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),

                Tables\Columns\TextColumn::make('data')
                    ->label(__('filament-excel-import::import.data'))
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (is_array($state)) {
                            $formatted = implode(', ', array_filter($state));
                            return strlen($formatted) > 50 ? $formatted : null;
                        }
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->formatStateUsing(function (FailedImportRow $record): string {
                        if (is_array($record->data)) {
                            return implode(', ', array_filter($record->data));
                        }
                        return (string) $record->data;
                    }),

                Tables\Columns\TextColumn::make('validation_error')
                    ->label(__('filament-excel-import::import.validation_errors'))
                    ->limit(50)
                    ->color('danger')
                    ->tooltip(function (Tables\Columns\TextColumn $column, FailedImportRow $record): ?string {
                        if (is_array($record->validation_error)) {
                            $formatted = [];
                            foreach ($record->validation_error as $field => $errors) {
                                if (is_array($errors)) {
                                    $formatted[] = $field . ': ' . implode(', ', $errors);
                                } else {
                                    $formatted[] = $field . ': ' . $errors;
                                }
                            }
                            $result = implode('; ', $formatted);
                            return strlen($result) > 50 ? $result : null;
                        }
                        return strlen((string) $record->validation_error) > 50 ? (string) $record->validation_error : null;
                    })
                    ->formatStateUsing(function (FailedImportRow $record): string {
                        if (is_array($record->validation_error)) {
                            $formatted = [];
                            foreach ($record->validation_error as $field => $errors) {
                                if (is_array($errors)) {
                                    $formatted[] = $field . ': ' . implode(', ', $errors);
                                } else {
                                    $formatted[] = $field . ': ' . $errors;
                                }
                            }
                            return implode('; ', $formatted);
                        }
                        return (string) $record->validation_error;
                    }),

                Tables\Columns\TextColumn::make('error')
                    ->label(__('filament-excel-import::import.general_errors'))
                    ->limit(50)
                    ->color('warning')
                    ->tooltip(function (Tables\Columns\TextColumn $column, FailedImportRow $record): ?string {
                        $errorText = (string) $record->error;
                        return strlen($errorText) > 50 ? $errorText : null;
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-excel-import::import.failed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_validation_errors')
                    ->label(__('filament-excel-import::import.has_validation_errors'))
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNotNull('validation_error')
                    ),

                Tables\Filters\Filter::make('has_general_errors')
                    ->label(__('filament-excel-import::import.has_general_errors'))
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNotNull('error')
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_failed_rows')
                    ->label(__('filament-excel-import::import.export_failed_rows'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        // Export logic would be implemented here
                        // This could generate a CSV or Excel file with failed rows
                    })
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('2xl'),

                Tables\Actions\Action::make('retry_row')
                    ->label(__('filament-excel-import::import.retry_row'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (FailedImportRow $record) {
                        // Logic to retry importing this specific row
                        // This would typically involve re-running the import logic for this row
                    })
                    ->requiresConfirmation()
                    ->color('warning'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('retry_selected')
                        ->label(__('filament-excel-import::import.retry_selected'))
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Logic to retry importing selected rows
                            foreach ($records as $record) {
                                // Retry logic for each record
                            }
                        })
                        ->color('warning'),

                    Tables\Actions\BulkAction::make('export_selected')
                        ->label(__('filament-excel-import::import.export_selected'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Export selected failed rows
                        })
                        ->color('gray'),
                ]),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading(__('filament-excel-import::import.no_failed_rows_heading'))
            ->emptyStateDescription(__('filament-excel-import::import.no_failed_rows_description'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}

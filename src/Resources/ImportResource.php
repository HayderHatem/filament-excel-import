<?php

namespace HayderHatem\FilamentExcelImport\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Imports\Models\Import;
use HayderHatem\FilamentExcelImport\Resources\ImportResource\Pages;
use HayderHatem\FilamentExcelImport\Resources\ImportResource\RelationManagers;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    // Navigation configuration
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('filament-excel-import::import.data_management_group');
    }

    // Resource labels
    public static function getLabel(): string
    {
        return __('filament-excel-import::import.model_label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament-excel-import::import.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-excel-import::import.navigation_label');
    }

    // Navigation badge
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $count = static::getModel()::count();
        return trans_choice('filament-excel-import::import.rows_imported', $count, ['count' => $count]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament-excel-import::import.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label(__('filament-excel-import::import.file_name'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\TextInput::make('importer')
                            ->label(__('filament-excel-import::import.importer_class'))
                            ->disabled(),

                        Forms\Components\TextInput::make('completed_at')
                            ->label(__('filament-excel-import::import.status'))
                            ->formatStateUsing(function ($state) {
                                if ($state) return __('filament-excel-import::import.status_completed');
                                return __('filament-excel-import::import.status_processing');
                            })
                            ->disabled(),

                        Forms\Components\Select::make('user_id')
                            ->label(__('filament-excel-import::import.imported_by'))
                            ->relationship('user', 'name')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament-excel-import::import.statistics'))
                    ->schema([
                        Forms\Components\TextInput::make('total_rows')
                            ->label(__('filament-excel-import::import.total_rows'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('processed_rows')
                            ->label(__('filament-excel-import::import.processed_rows'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('imported_rows')
                            ->label(__('filament-excel-import::import.successful_rows'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('failed_rows')
                            ->label(__('filament-excel-import::import.failed_rows'))
                            ->formatStateUsing(function (Import $record): int {
                                return max(0, $record->total_rows - $record->imported_rows);
                            })
                            ->numeric()
                            ->disabled(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make(__('filament-excel-import::import.timestamps'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(__('filament-excel-import::import.started_at'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label(__('filament-excel-import::import.completed_at'))
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament-excel-import::import.additional_information'))
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label(__('filament-excel-import::import.error_message'))
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('options')
                            ->label(__('filament-excel-import::import.options'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn(?Import $record): bool =>
                        $record && ($record->error_message || $record->options)
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label(__('filament-excel-import::import.file_name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('importer')
                    ->label(__('filament-excel-import::import.type'))
                    ->formatStateUsing(
                        fn(string $state): string =>
                        str($state)->afterLast('\\')->before('Importer')->title()
                    )
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('filament-excel-import::import.status'))
                    ->formatStateUsing(function (Import $record): string {
                        if ($record->completed_at) {
                            $failedCount = max(0, $record->total_rows - $record->imported_rows);
                            return $failedCount > 0 ?
                                __('filament-excel-import::import.status_completed_with_errors') :
                                __('filament-excel-import::import.status_completed');
                        }
                        return $record->processed_rows > 0 ?
                            __('filament-excel-import::import.status_processing') :
                            __('filament-excel-import::import.status_pending');
                    })
                    ->badge()
                    ->color(function (Import $record): string {
                        if ($record->completed_at) {
                            $failedCount = max(0, $record->total_rows - $record->imported_rows);
                            return $failedCount > 0 ? 'warning' : 'success';
                        }
                        return $record->processed_rows > 0 ? 'info' : 'gray';
                    }),

                Tables\Columns\TextColumn::make('total_rows')
                    ->label(__('filament-excel-import::import.total_rows'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_rows')
                    ->label(__('filament-excel-import::import.processed'))
                    ->numeric()
                    ->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('failed_rows')
                    ->label(__('filament-excel-import::import.failed'))
                    ->getStateUsing(function (Import $record): int {
                        // Calculate failed rows as total - imported
                        return max(0, $record->total_rows - $record->imported_rows);
                    })
                    ->numeric()
                    ->sortable()
                    ->color(function (Import $record): string {
                        $failedCount = max(0, $record->total_rows - $record->imported_rows);
                        return $failedCount > 0 ? 'danger' : 'gray';
                    }),

                Tables\Columns\TextColumn::make('success_rate')
                    ->label(__('filament-excel-import::import.success_rate'))
                    ->getStateUsing(function (Import $record): string {
                        if ($record->total_rows === 0) return '0%';
                        $rate = ($record->imported_rows / $record->total_rows) * 100;
                        return number_format($rate, 1) . '%';
                    })
                    ->badge()
                    ->color(function (Import $record): string {
                        if ($record->total_rows === 0) return 'gray';
                        $rate = ($record->imported_rows / $record->total_rows) * 100;
                        return match (true) {
                            $rate >= 95 => 'success',
                            $rate >= 80 => 'warning',
                            default => 'danger',
                        };
                    }),

                Tables\Columns\TextColumn::make('duration')
                    ->label(__('filament-excel-import::import.duration'))
                    ->getStateUsing(function (Import $record): string {
                        if (!$record->completed_at) {
                            return __('filament-excel-import::import.in_progress');
                        }

                        try {
                            $startTime = \Carbon\Carbon::parse($record->created_at);
                            $endTime = \Carbon\Carbon::parse($record->completed_at);
                            $duration = $startTime->diff($endTime);

                            $hours = $duration->h + ($duration->days * 24);
                            return sprintf('%02d:%02d:%02d', $hours, $duration->i, $duration->s);
                        } catch (\Exception $e) {
                            return __('filament-excel-import::import.duration_unavailable');
                        }
                    })
                    ->placeholder(__('filament-excel-import::import.in_progress')),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('filament-excel-import::import.imported_by'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-excel-import::import.started'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('filament-excel-import::import.completed'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('completed')
                    ->label(__('filament-excel-import::import.status_completed'))
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNotNull('completed_at')
                    ),

                Tables\Filters\Filter::make('processing')
                    ->label(__('filament-excel-import::import.status_processing'))
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNull('completed_at')->where('processed_rows', '>', 0)
                    ),

                Tables\Filters\SelectFilter::make('importer')
                    ->label(__('filament-excel-import::import.import_type'))
                    ->options(function () {
                        return Import::distinct('importer')
                            ->pluck('importer')
                            ->mapWithKeys(fn($importer) => [
                                $importer => str($importer)->afterLast('\\')->before('Importer')->title()
                            ]);
                    }),

                Tables\Filters\Filter::make('has_failures')
                    ->label(__('filament-excel-import::import.has_failures'))
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereRaw('total_rows > imported_rows')
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('started_from')
                            ->label(__('filament-excel-import::import.started_from')),
                        Forms\Components\DatePicker::make('started_until')
                            ->label(__('filament-excel-import::import.started_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['started_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['started_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('filament-excel-import::import.view_details')),

                Tables\Actions\Action::make('download_failed_rows')
                    ->label(__('filament-excel-import::import.download_failed_rows'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(
                        fn(Import $record): string =>
                        route('filament.imports.failed-rows.download', ['import' => $record])
                    )
                    ->openUrlInNewTab()
                    ->visible(fn(Import $record): bool => ($record->total_rows - $record->imported_rows) > 0)
                    ->color('danger'),

                Tables\Actions\Action::make('retry')
                    ->label(__('filament-excel-import::import.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Import $record) {
                        // Retry logic would be implemented here
                        $record->update([
                            'completed_at' => null,
                            'processed_rows' => 0,
                            'imported_rows' => 0,
                            'failed_rows' => 0,
                        ]);
                    })
                    ->visible(
                        fn(Import $record): bool =>
                        $record->completed_at && ($record->total_rows - $record->imported_rows) > 0
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_as_processed')
                        ->label(__('filament-excel-import::import.mark_as_processed'))
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->update(['completed_at' => now()]);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('filament-excel-import::import.import_overview'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('file_name')
                                    ->label(__('filament-excel-import::import.file_name'))
                                    ->weight(FontWeight::Medium),

                                Infolists\Components\TextEntry::make('importer')
                                    ->label(__('filament-excel-import::import.import_type_field'))
                                    ->formatStateUsing(
                                        fn(string $state): string =>
                                        str($state)->afterLast('\\')->before('Importer')->title()
                                    )
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('user.name')
                                    ->label(__('filament-excel-import::import.imported_by')),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament-excel-import::import.import_statistics'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_rows')
                                    ->label(__('filament-excel-import::import.total_rows'))
                                    ->numeric()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('imported_rows')
                                    ->label(__('filament-excel-import::import.successfully_imported'))
                                    ->numeric()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('failed_rows')
                                    ->label(__('filament-excel-import::import.failed_rows_count'))
                                    ->getStateUsing(function (Import $record): int {
                                        return max(0, $record->total_rows - $record->imported_rows);
                                    })
                                    ->numeric()
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('success_rate')
                                    ->label(__('filament-excel-import::import.success_rate'))
                                    ->getStateUsing(function (Import $record): string {
                                        if ($record->total_rows === 0) return '0%';
                                        $rate = ($record->imported_rows / $record->total_rows) * 100;
                                        return number_format($rate, 1) . '%';
                                    })
                                    ->badge()
                                    ->color(function (Import $record): string {
                                        if ($record->total_rows === 0) return 'gray';
                                        $rate = ($record->imported_rows / $record->total_rows) * 100;
                                        return match (true) {
                                            $rate >= 95 => 'success',
                                            $rate >= 80 => 'warning',
                                            default => 'danger',
                                        };
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament-excel-import::import.timing_information'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('started_at')
                                    ->label(__('filament-excel-import::import.started_at'))
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label(__('filament-excel-import::import.completed_at'))
                                    ->dateTime()
                                    ->placeholder(__('filament-excel-import::import.not_completed')),

                                Infolists\Components\TextEntry::make('duration')
                                    ->label(__('filament-excel-import::import.duration'))
                                    ->getStateUsing(function (Import $record): string {
                                        if (!$record->completed_at) {
                                            return __('filament-excel-import::import.in_progress');
                                        }

                                        try {
                                            $startTime = \Carbon\Carbon::parse($record->created_at);
                                            $endTime = \Carbon\Carbon::parse($record->completed_at);
                                            $duration = $startTime->diff($endTime);

                                            $hours = $duration->h + ($duration->days * 24);
                                            return sprintf('%02d:%02d:%02d', $hours, $duration->i, $duration->s);
                                        } catch (\Exception $e) {
                                            return __('filament-excel-import::import.duration_unavailable');
                                        }
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament-excel-import::import.additional_information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('options')
                            ->label(__('filament-excel-import::import.import_options'))
                            ->getStateUsing(function (Import $record): string {
                                $options = $record->options ?? [];
                                if (empty($options)) return __('filament-excel-import::import.none');

                                $formattedOptions = [];
                                foreach ($options as $key => $value) {
                                    if ($key === 'additional_form_data' && is_array($value)) {
                                        foreach ($value as $subKey => $subValue) {
                                            $formattedOptions[] = ucfirst(str_replace('_', ' ', $subKey)) . ': ' . $subValue;
                                        }
                                    } else {
                                        $formattedOptions[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
                                    }
                                }

                                return implode("\n", $formattedOptions);
                            })
                            ->markdown()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('error_message')
                            ->label(__('filament-excel-import::import.error_message'))
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn(Import $record): bool => !empty($record->error_message)),
                    ])
                    ->visible(
                        fn(Import $record): bool =>
                        !empty($record->options) || !empty($record->error_message)
                    ),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FailedRowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
            'view' => Pages\ViewImport::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user']);
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'export',
            'retry',
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Imports are created programmatically
    }

    public static function canEdit($record): bool
    {
        return false; // Imports are not editable
    }
}

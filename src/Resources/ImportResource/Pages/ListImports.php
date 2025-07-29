<?php

namespace HayderHatem\FilamentExcelImport\Resources\ImportResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use HayderHatem\FilamentExcelImport\Resources\ImportResource;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_guide')
                ->label(__('filament-excel-import::import.import_guide'))
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading(__('filament-excel-import::import.how_to_import'))
                ->modalDescription(__('filament-excel-import::import.import_guide_description'))
                ->modalContent(view('filament-excel-import::import-guide'))
                ->modalWidth('lg'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('filament-excel-import::import.all'))
                ->badge(Import::count()),

            'pending' => Tab::make(__('filament-excel-import::import.pending'))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('completed_at')->where('processed_rows', 0))
                ->badge(Import::whereNull('completed_at')->where('processed_rows', 0)->count())
                ->badgeColor('gray'),

            'processing' => Tab::make(__('filament-excel-import::import.processing'))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('completed_at')->where('processed_rows', '>', 0))
                ->badge(Import::whereNull('completed_at')->where('processed_rows', '>', 0)->count())
                ->badgeColor('info'),

            'completed' => Tab::make(__('filament-excel-import::import.completed'))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('completed_at')->whereRaw('total_rows = imported_rows'))
                ->badge(Import::whereNotNull('completed_at')->whereRaw('total_rows = imported_rows')->count())
                ->badgeColor('success'),

            'with_failures' => Tab::make(__('filament-excel-import::import.with_failures'))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereRaw('total_rows > imported_rows'))
                ->badge(Import::whereRaw('total_rows > imported_rows')->count())
                ->badgeColor('warning'),
        ];
    }
}

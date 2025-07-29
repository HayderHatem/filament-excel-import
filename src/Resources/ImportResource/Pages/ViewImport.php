<?php

namespace HayderHatem\FilamentExcelImport\Resources\ImportResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use HayderHatem\FilamentExcelImport\Resources\ImportResource;
use Filament\Actions\Imports\Models\Import;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_failed_rows')
                ->label(__('filament-excel-import::import.download_failed_rows'))
                ->icon('heroicon-o-arrow-down-tray')
                ->url(
                    fn(Import $record): string =>
                    route('filament.imports.failed-rows.download', ['import' => $record])
                )
                ->openUrlInNewTab()
                ->visible(fn(Import $record): bool => ($record->total_rows - $record->imported_rows) > 0)
                ->color('danger'),

            Actions\Action::make('retry')
                ->label(__('filament-excel-import::import.retry'))
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading(__('filament-excel-import::import.retry_confirmation'))
                ->modalDescription(__('filament-excel-import::import.retry_description'))
                ->action(function (Import $record) {
                    // Reset the import status to retry
                    $record->update([
                        'completed_at' => null,
                        'processed_rows' => 0,
                        'imported_rows' => 0,
                        'failed_rows' => 0,
                    ]);

                    // You could dispatch a job here to reprocess the import
                    // dispatch(new RetryImportJob($record));
                })
                ->visible(
                    fn(Import $record): bool =>
                    $record->completed_at && ($record->total_rows - $record->imported_rows) > 0
                )
                ->color('warning'),

            Actions\DeleteAction::make()
                ->visible(
                    fn(Import $record): bool =>
                    $record->completed_at !== null
                ),
        ];
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();
        return __('filament-excel-import::import.view_import_title', [
            'file_name' => $record->file_name,
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // ImportStatsWidget can be added here when created
        ];
    }
}

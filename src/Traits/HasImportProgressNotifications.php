<?php

namespace HayderHatem\FilamentExcelImport\Traits;

use Filament\Notifications\Notification;
use HayderHatem\FilamentExcelImport\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;

trait HasImportProgressNotifications
{
    protected function notifyImportProgress(Import $import, Authenticatable $user): void
    {
        if (! $import->processed_rows) {
            return;
        }

        // Prevent division by zero when total_rows is 0
        if (! $import->total_rows || $import->total_rows <= 0) {
            return;
        }

        $progress = ($import->processed_rows / $import->total_rows) * 100;

        if ($progress < 100) {
            return;
        }

        $failedRowsCount = $import->getFailedRowsCount();

        Notification::make()
            ->title($import->importer::getCompletedNotificationTitle($import))
            ->body($import->importer::getCompletedNotificationBody($import))
            ->when(
                ! $failedRowsCount,
                fn(Notification $notification) => $notification->success(),
            )
            ->when(
                $failedRowsCount && ($failedRowsCount < $import->total_rows),
                fn(Notification $notification) => $notification->warning(),
            )
            ->when(
                $failedRowsCount === $import->total_rows,
                fn(Notification $notification) => $notification->danger(),
            )
            ->sendToDatabase($user);
    }
}

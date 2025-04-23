<?php

namespace HayderHatem\FilamentExcelImport\Actions;

use Filament\Actions\ImportAction as ActionsImportAction;
use HayderHatem\FilamentExcelImport\Actions\Concerns\CanImportExcelRecords;

class FullImportAction extends ActionsImportAction
{
    use CanImportExcelRecords;
}

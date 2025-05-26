<?php

namespace HayderHatem\FilamentExcelImport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedImportRow extends Model
{
    protected $table = 'failed_import_rows';

    protected $fillable = [
        'import_id',
        'data',
        'validation_errors',
        'error',
    ];

    protected $casts = [
        'data' => 'array',
        'validation_errors' => 'array',
    ];

    /**
     * Get the import that owns this failed row.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_id');
    }
}

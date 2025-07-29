<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Import Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the import resource behavior and navigation settings.
    |
    */

    'import_resource' => [
        'enabled' => true,
        'navigation' => [
            'group' => 'filament-excel-import::import.data_management_group',
            'sort' => 1,
            'icon' => 'heroicon-o-arrow-up-tray',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database settings for the import system.
    | The plugin uses Filament's built-in import tables by default.
    |
    */

    'database' => [
        'use_filament_tables' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Specify the user model class for tracking who performed imports.
    |
    */

    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Import Tracking
    |--------------------------------------------------------------------------
    |
    | Configure how imports are tracked and managed.
    |
    */

    'tracking' => [
        'track_progress' => true,
        'track_duration' => true,
        'auto_cleanup_days' => 30, // Clean up completed imports after X days (0 = disabled)
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure file storage settings for imports.
    |
    */

    'storage' => [
        'disk' => 'local',
        'path' => 'imports',
        'failed_rows_path' => 'failed-rows',
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure export settings for failed rows.
    |
    */

    'export' => [
        'format' => 'csv', // csv, xlsx
        'include_headers' => true,
        'include_error_column' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure user interface elements and behavior.
    |
    */

    'ui' => [
        'items_per_page' => [10, 25, 50, 100],
        'default_items_per_page' => 25,
        'show_import_guide' => true,
        'show_navigation_badge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Configuration
    |--------------------------------------------------------------------------
    |
    | Configure permission prefixes for Filament Shield integration.
    |
    */

    'permissions' => [
        'prefixes' => [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'export',
            'retry',
        ],
    ],

];

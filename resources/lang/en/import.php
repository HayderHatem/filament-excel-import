<?php


return [
    'actions' => [
        'example_template' => [
            'label' => 'Download Example Template XLSX'
        ]
    ],
    'modal' => [
        'heading' => 'Import :label',
        'form' => [
            'file' => [
                'label' => 'Upload XLSX / CSV File',
                'placeholder' => 'Upload a XLSX / CSV file',
            ],
            'import_options' => [
                'label' => 'Import Options',
            ],
        ]
    ],
    'errors' => [
        'field_required' => ':field is required and cannot be empty',
        'field_exists' => ':field already exists',
        'invalid_reference' => 'Invalid reference to :table',
        'check_constraint_failed' => 'Invalid value: :constraint constraint failed',
        'generic_validation' => 'Failed to import row due to data validation error',
    ]
];

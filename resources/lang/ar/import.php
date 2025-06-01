<?php


return [
    'actions' => [
        'example_template' => [
            'label' => 'تنزيل مثال لملف XLSX',
        ],
    ],
    'modal' => [
        'heading' => 'Import :label',
        'form' => [
            'file' => [
                'label' => 'رفع ملف XLSX / CSV',
                'placeholder' => 'رفع ملف XLSX / CSV',
            ]
        ]
    ],
    'errors' => [
        'field_required' => ':field مطلوب ولا يمكن أن يكون فارغاً',
        'field_exists' => ':field موجود بالفعل',
        'invalid_reference' => 'مرجع غير صالح إلى :table',
        'check_constraint_failed' => 'قيمة غير صالحة: فشل قيد :constraint',
        'generic_validation' => 'فشل استيراد الصف بسبب خطأ في التحقق من البيانات',
    ]
];

<?php

return [
    // Model labels
    'model_label' => 'استيراد',
    'plural_model_label' => 'الاستيرادات',
    'navigation_label' => 'استيراد البيانات',

    // Field labels
    'file_name' => 'اسم الملف',
    'importer_class' => 'فئة المستورد',
    'type' => 'النوع',
    'status' => 'الحالة',
    'total_rows' => 'إجمالي الصفوف',
    'imported_rows' => 'الصفوف المستوردة',
    'processed_rows' => 'الصفوف المعالجة',
    'successful_rows' => 'الصفوف الناجحة',
    'failed_rows' => 'الصفوف الفاشلة',
    'processed' => 'معالج',
    'success_rate' => 'معدل النجاح',
    'imported_by' => 'تم الاستيراد بواسطة',
    'started_at' => 'بدأ في',
    'completed_at' => 'اكتمل في',
    'started' => 'بدأ',
    'completed' => 'مكتمل',
    'duration' => 'المدة',
    'error_message' => 'رسالة الخطأ',
    'options' => 'الخيارات',

    // Status values
    'status_pending' => 'في الانتظار',
    'status_processing' => 'قيد المعالجة',
    'status_completed' => 'مكتمل',
    'status_completed_with_errors' => 'مكتمل مع أخطاء',
    'status_failed' => 'فشل',

    // Section titles
    'basic_information' => 'المعلومات الأساسية',
    'statistics' => 'الإحصائيات',
    'timestamps' => 'الطوابع الزمنية',
    'additional_information' => 'معلومات إضافية',
    'import_overview' => 'نظرة عامة على الاستيراد',
    'import_statistics' => 'إحصائيات الاستيراد',
    'timing_information' => 'معلومات التوقيت',

    // Actions and buttons
    'view_details' => 'عرض التفاصيل',
    'download_template' => 'تحميل القالب',
    'download_failed_rows' => 'تحميل الصفوف الفاشلة',
    'retry' => 'إعادة المحاولة',
    'retry_confirmation' => 'إعادة محاولة الاستيراد',
    'retry_description' => 'سيؤدي هذا إلى إعادة تعيين حالة الاستيراد والسماح لك بإعادة معالجة هذا الاستيراد. هل أنت متأكد؟',
    'mark_as_processed' => 'وضع علامة كمعالج',
    'import_guide' => 'دليل الاستيراد',
    'how_to_import' => 'كيفية استيراد البيانات',
    'import_guide_description' => 'تعلم كيفية استيراد البيانات باستخدام ملفات Excel',

    // Table labels
    'successful' => 'ناجح',
    'failed' => 'فاشل',
    'import_type' => 'نوع الاستيراد',

    // Tab labels
    'all' => 'الكل',
    'pending' => 'في الانتظار',
    'processing' => 'قيد المعالجة',
    'with_failures' => 'مع إخفاقات',

    // States
    'status_running' => 'قيد التشغيل',
    'status_queued' => 'في قائمة الانتظار',
    'in_progress' => 'قيد التقدم',
    'not_completed' => 'غير مكتمل',
    'duration_unavailable' => 'المدة غير متاحة',

    // Failed rows manager
    'failed_rows' => 'الصفوف الفاشلة',
    'failed_import_rows' => 'صفوف الاستيراد الفاشلة',
    'row_number' => 'الصف',
    'data' => 'البيانات',
    'errors' => 'الأخطاء',
    'validation_errors' => 'أخطاء التحقق',
    'general_errors' => 'الأخطاء العامة',
    'export_failed_rows' => 'تصدير الصفوف الفاشلة',
    'retry_row' => 'إعادة محاولة الصف',
    'retry_selected' => 'إعادة محاولة المحدد',
    'export_selected' => 'تصدير المحدد',
    'has_validation_errors' => 'لديه أخطاء تحقق',
    'has_general_errors' => 'لديه أخطاء عامة',
    'row_information' => 'معلومات الصف',
    'error_information' => 'معلومات الخطأ',
    'row_data' => 'بيانات الصف',
    'failed_at' => 'فشل في',

    // View page
    'view_import_title' => 'عرض الاستيراد: :file_name',
    'successfully_imported' => 'تم الاستيراد بنجاح',
    'failed_rows_count' => 'الصفوف الفاشلة',
    'import_type_field' => 'نوع الاستيراد',

    // Messages
    'no_failed_rows' => 'لم يتم العثور على صفوف فاشلة.',
    'no_failed_rows_heading' => 'لا توجد صفوف فاشلة',
    'no_failed_rows_description' => 'تم معالجة جميع الصفوف في هذا الاستيراد بنجاح. لا توجد صفوف فاشلة للعرض.',
    'import_completed_successfully' => 'تم إكمال الاستيراد بنجاح!',
    'import_completed_with_errors' => 'تم إكمال الاستيراد مع بعض الأخطاء.',
    'no_headers_detected' => 'لم يتم اكتشاف رؤوس أعمدة',
    'sheet_reading_error' => 'خطأ في قراءة الورقة',
    'error_reading_file' => 'خطأ في قراءة الملف',
    'switching_to_streaming_mode' => 'التبديل إلى وضع التدفق',
    'error_processing_excel_file' => 'خطأ في معالجة ملف Excel',
    'import_failed' => 'فشل الاستيراد',
    'file_preview_unavailable' => 'معاينة الملف غير متاحة',
    'invalid_file' => 'ملف غير صالح',
    'sheet' => 'ورقة',

    // Notification body messages
    'could_not_detect_headers' => 'تعذر اكتشاف رؤوس الأعمدة. يرجى ربط الأعمدة يدوياً باستخدام حقول النص أدناه.',
    'unable_to_preview_file' => 'تعذر معاينة محتويات الملف. يمكنك الاستيراد ولكن يرجى ربط الأعمدة يدوياً.',
    'unable_to_read_sheet' => 'تعذر قراءة الورقة المحددة. تم إعادة تعيين ربط الأعمدة.',
    'please_upload_valid_excel' => 'يرجى تحميل ملف Excel صالح.',
    'unable_to_read_uploaded_file' => 'تعذر قراءة ملف Excel المرفوع.',
    'file_too_large_streaming' => 'الملف كبير جداً للمعالجة العادية، استخدام الاستيراد المتدفق...',
    'unexpected_error_occurred' => 'حدث خطأ غير متوقع: ',

    // Filters
    'has_failures' => 'لديه إخفاقات',
    'from' => 'من',
    'until' => 'حتى',

    // Pluralization
    'imports_count' => '{1} :count استيراد|[2,10] :count استيرادات|[11,*] :count استيراد',

    // Navigation
    'none' => 'لا يوجد',
    'data_management_group' => 'إدارة البيانات',

    // Import Guide Content
    'guide_title' => 'كيفية استيراد البيانات',
    'supported_formats_title' => 'تنسيقات الملفات المدعومة',
    'excel_files' => 'ملفات Excel (.xlsx, .xls)',
    'csv_files' => 'ملفات CSV (.csv)',
    'ods_files' => 'جداول البيانات المفتوحة (.ods)',

    'import_steps_title' => 'خطوات الاستيراد',
    'step_1' => 'انقر على زر "استيراد" في إجراءات الرأس',
    'step_2' => 'قم بتنزيل قالب المثال لرؤية التنسيق المتوقع',
    'step_3' => 'قم بإعداد ملف البيانات الخاص بك مع الأعمدة المطلوبة',
    'step_4' => 'قم بتحميل ملفك وقم بربط الأعمدة إذا لزم الأمر',
    'step_5' => 'راجع وابدأ عملية الاستيراد',

    'best_practices_title' => 'أفضل الممارسات',
    'practice_1' => 'استخدم الصف الأول لرؤوس الأعمدة',
    'practice_2' => 'تأكد من أن أنواع البيانات تطابق التنسيقات المتوقعة',
    'practice_3' => 'قم بإزالة الصفوف والأعمدة الفارغة',
    'practice_4' => 'تحقق من أن الحقول المطلوبة مملوءة',
    'practice_5' => 'احتفظ بحجم الملف أقل من 50 ميجابايت للحصول على أفضل أداء',
    'practice_6' => 'استخدم تشفير UTF-8 لتجنب مشاكل الأحرف',
    'practice_7' => 'قم بعمل نسخة احتياطية من بياناتك قبل الاستيرادات الكبيرة',

    'common_issues_title' => 'المشاكل الشائعة',
    'issue_1' => 'تحقق من الأحرف الخاصة في البيانات',
    'issue_2' => 'تأكد من أن التواريخ بالتنسيق الصحيح (YYYY-MM-DD)',
    'issue_3' => 'تحقق من أن عناوين البريد الإلكتروني صحيحة',
    'issue_4' => 'قم بإزالة أي خلايا مدمجة',
    'issue_5' => 'تحقق من الإدخالات المكررة',
    'issue_6' => 'تأكد من أن الحقول الرقمية تحتوي على أرقام فقط',
    'issue_7' => 'تجنب الصيغ في خلايا البيانات',

    'after_import_title' => 'بعد الاستيراد',
    'after_import_description' => 'يمكنك مراقبة تقدم الاستيراد وعرض النتائج في قسم إدارة الاستيراد:',
    'after_import_1' => 'عرض حالة الاستيراد والإحصائيات',
    'after_import_2' => 'تحميل الصفوف الفاشلة للتصحيح',
    'after_import_3' => 'إعادة محاولة الاستيرادات الفاشلة إذا لزم الأمر',
    'after_import_4' => 'تصدير البيانات المعالجة للتحقق',
    'after_import_5' => 'تتبع المدة ومعدلات النجاح',

    'troubleshooting_title' => 'استكشاف الأخطاء وإصلاحها',
    'import_failed_title' => 'فشل الاستيراد؟',
    'import_failed_1' => 'تحقق من تنسيق الملف والتشفير',
    'import_failed_2' => 'تحقق من أن ربط الأعمدة صحيح',
    'import_failed_3' => 'ابحث عن أخطاء التحقق في الصفوف الفاشلة',
    'import_failed_4' => 'تأكد من أن حجم الملف ضمن الحدود',

    'performance_issues_title' => 'مشاكل الأداء؟',
    'performance_1' => 'قسم الملفات الكبيرة إلى أجزاء أصغر',
    'performance_2' => 'قم بالاستيراد خلال ساعات الذروة المنخفضة',
    'performance_3' => 'قم بإزالة الأعمدة غير الضرورية',
    'performance_4' => 'تحقق من موارد الخادم',

    'statistics_title' => 'فهم الإحصائيات',
    'total_rows_desc' => 'عدد صفوف البيانات في ملفك (باستثناء الرؤوس)',
    'processed_desc' => 'الصفوف التي تم معالجتها بواسطة نظام الاستيراد',
    'imported_desc' => 'الصفوف المحفوظة بنجاح في قاعدة البيانات',
    'failed_desc' => 'الصفوف التي فشلت في التحقق أو الاستيراد',
    'success_rate_desc' => 'نسبة الصفوف المستوردة بنجاح',
    'duration_desc' => 'الوقت المستغرق لإكمال الاستيراد',
];

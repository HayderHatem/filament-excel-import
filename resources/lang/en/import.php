<?php

return [
    // Model labels
    'model_label' => 'Import',
    'plural_model_label' => 'Imports',
    'navigation_label' => 'Data Imports',

    // Field labels
    'file_name' => 'File Name',
    'importer_class' => 'Importer Class',
    'type' => 'Type',
    'status' => 'Status',
    'total_rows' => 'Total Rows',
    'imported_rows' => 'Imported Rows',
    'processed_rows' => 'Processed Rows',
    'successful_rows' => 'Successful Rows',
    'failed_rows' => 'Failed Rows',
    'processed' => 'Processed',
    'success_rate' => 'Success Rate',
    'imported_by' => 'Imported By',
    'started_at' => 'Started At',
    'completed_at' => 'Completed At',
    'started' => 'Started',
    'completed' => 'Completed',
    'duration' => 'Duration',
    'error_message' => 'Error Message',
    'options' => 'Options',

    // Status values
    'status_pending' => 'Pending',
    'status_processing' => 'Processing',
    'status_completed' => 'Completed',
    'status_completed_with_errors' => 'Completed with Errors',
    'status_failed' => 'Failed',

    // Section titles
    'basic_information' => 'Basic Information',
    'statistics' => 'Statistics',
    'timestamps' => 'Timestamps',
    'additional_information' => 'Additional Information',
    'import_overview' => 'Import Overview',
    'import_statistics' => 'Import Statistics',
    'timing_information' => 'Timing Information',

    // Actions and buttons
    'view_details' => 'View Details',
    'download_template' => 'Download Template',
    'download_failed_rows' => 'Download Failed Rows',
    'retry' => 'Retry',
    'retry_confirmation' => 'Retry Import',
    'retry_description' => 'This will reset the import status and allow you to reprocess this import. Are you sure?',
    'mark_as_processed' => 'Mark as Processed',
    'import_guide' => 'Import Guide',
    'how_to_import' => 'How to Import Data',
    'import_guide_description' => 'Learn how to import data using Excel files',

    // Table labels
    'successful' => 'Successful',
    'failed' => 'Failed',
    'import_type' => 'Import Type',

    // Tab labels
    'all' => 'All',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'with_failures' => 'With Failures',

    // States
    'status_running' => 'Running',
    'status_queued' => 'Queued',
    'in_progress' => 'In Progress',
    'not_completed' => 'Not Completed',
    'duration_unavailable' => 'Duration Unavailable',

    // Failed rows manager
    'failed_rows' => 'Failed Rows',
    'failed_import_rows' => 'Failed Import Rows',
    'row_number' => 'Row',
    'data' => 'Data',
    'errors' => 'Errors',
    'validation_errors' => 'Validation Errors',
    'general_errors' => 'General Errors',
    'export_failed_rows' => 'Export Failed Rows',
    'retry_row' => 'Retry Row',
    'retry_selected' => 'Retry Selected',
    'export_selected' => 'Export Selected',
    'has_validation_errors' => 'Has Validation Errors',
    'has_general_errors' => 'Has General Errors',
    'row_information' => 'Row Information',
    'error_information' => 'Error Information',
    'row_data' => 'Row Data',
    'failed_at' => 'Failed At',

    // View page
    'view_import_title' => 'View Import: :file_name',
    'successfully_imported' => 'Successfully Imported',
    'failed_rows_count' => 'Failed Rows',
    'import_type_field' => 'Import Type',

    // Messages
    'no_failed_rows' => 'No failed rows found.',
    'no_failed_rows_heading' => 'No Failed Rows',
    'no_failed_rows_description' => 'All rows in this import were processed successfully. There are no failed rows to display.',
    'import_completed_successfully' => 'Import completed successfully!',
    'import_completed_with_errors' => 'Import completed with some errors.',
    'no_headers_detected' => 'No headers detected',
    'sheet_reading_error' => 'Sheet reading error',
    'error_reading_file' => 'Error reading file',
    'switching_to_streaming_mode' => 'Switching to streaming mode',
    'error_processing_excel_file' => 'Error processing Excel file',
    'import_failed' => 'Import failed',
    'file_preview_unavailable' => 'File preview unavailable',
    'invalid_file' => 'Invalid file',
    'sheet' => 'Sheet',

    // Notification body messages
    'could_not_detect_headers' => 'Could not detect column headers. Please map columns manually using the text inputs below.',
    'unable_to_preview_file' => 'Unable to preview file contents. You can still import, but please map columns manually.',
    'unable_to_read_sheet' => 'Unable to read the selected sheet. Column mapping has been reset.',
    'please_upload_valid_excel' => 'Please upload a valid Excel file.',
    'unable_to_read_uploaded_file' => 'Unable to read the uploaded Excel file.',
    'file_too_large_streaming' => 'File too large for standard processing, using streaming import...',
    'unexpected_error_occurred' => 'An unexpected error occurred: ',

    // Filters
    'has_failures' => 'Has Failures',
    'from' => 'From',
    'until' => 'Until',

    // Pluralization
    'imports_count' => '{1} :count import|[2,*] :count imports',

    // Navigation
    'none' => 'None',
    'data_management_group' => 'Data Management',

    // Import Guide Content
    'guide_title' => 'How to Import Data',
    'supported_formats_title' => 'Supported File Formats',
    'excel_files' => 'Excel files (.xlsx, .xls)',
    'csv_files' => 'CSV files (.csv)',
    'ods_files' => 'OpenDocument Spreadsheets (.ods)',

    'import_steps_title' => 'Import Steps',
    'step_1' => 'Click the "Import" button in the header actions',
    'step_2' => 'Download the example template to see the expected format',
    'step_3' => 'Prepare your data file with the required columns',
    'step_4' => 'Upload your file and map columns if needed',
    'step_5' => 'Review and start the import process',

    'best_practices_title' => 'Best Practices',
    'practice_1' => 'Use the first row for column headers',
    'practice_2' => 'Ensure data types match expected formats',
    'practice_3' => 'Remove empty rows and columns',
    'practice_4' => 'Validate required fields are filled',
    'practice_5' => 'Keep file size under 50MB for best performance',
    'practice_6' => 'Use UTF-8 encoding to avoid character issues',
    'practice_7' => 'Backup your data before large imports',

    'common_issues_title' => 'Common Issues',
    'issue_1' => 'Check for special characters in data',
    'issue_2' => 'Ensure dates are in the correct format (YYYY-MM-DD)',
    'issue_3' => 'Verify email addresses are valid',
    'issue_4' => 'Remove any merged cells',
    'issue_5' => 'Check for duplicate entries',
    'issue_6' => 'Ensure numeric fields contain only numbers',
    'issue_7' => 'Avoid formulas in data cells',

    'after_import_title' => 'After Import',
    'after_import_description' => 'You can monitor import progress and view results in the Import Management section:',
    'after_import_1' => 'View import status and statistics',
    'after_import_2' => 'Download failed rows for correction',
    'after_import_3' => 'Retry failed imports if needed',
    'after_import_4' => 'Export processed data for verification',
    'after_import_5' => 'Track duration and success rates',

    'troubleshooting_title' => 'Troubleshooting',
    'import_failed_title' => 'Import Failed?',
    'import_failed_1' => 'Check file format and encoding',
    'import_failed_2' => 'Verify column mappings are correct',
    'import_failed_3' => 'Look for validation errors in failed rows',
    'import_failed_4' => 'Ensure file size is within limits',

    'performance_issues_title' => 'Performance Issues?',
    'performance_1' => 'Break large files into smaller chunks',
    'performance_2' => 'Import during off-peak hours',
    'performance_3' => 'Remove unnecessary columns',
    'performance_4' => 'Check server resources',

    'statistics_title' => 'Understanding Statistics',
    'total_rows_desc' => 'Number of data rows in your file (excluding headers)',
    'processed_desc' => 'Rows that have been processed by the import system',
    'imported_desc' => 'Rows successfully saved to the database',
    'failed_desc' => 'Rows that failed validation or import',
    'success_rate_desc' => 'Percentage of successfully imported rows',
    'duration_desc' => 'Time taken to complete the import',
];

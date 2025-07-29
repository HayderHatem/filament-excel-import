<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="prose prose-sm max-w-none dark:prose-invert">
        <h3 class="text-lg font-semibold mb-4">📋 {{ __('filament-excel-import::import.guide_title') }}</h3>

        <div class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">🔹 {{
                    __('filament-excel-import::import.supported_formats_title') }}</h4>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>• {{ __('filament-excel-import::import.excel_files') }}</li>
                    <li>• {{ __('filament-excel-import::import.csv_files') }}</li>
                    <li>• {{ __('filament-excel-import::import.ods_files') }}</li>
                </ul>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">📝 {{
                    __('filament-excel-import::import.import_steps_title') }}</h4>
                <ol class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
                    <li><strong>1.</strong> {{ __('filament-excel-import::import.step_1') }}</li>
                    <li><strong>2.</strong> {{ __('filament-excel-import::import.step_2') }}</li>
                    <li><strong>3.</strong> {{ __('filament-excel-import::import.step_3') }}</li>
                    <li><strong>4.</strong> {{ __('filament-excel-import::import.step_4') }}</li>
                    <li><strong>5.</strong> {{ __('filament-excel-import::import.step_5') }}</li>
                </ol>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <h4 class="font-medium text-green-900 dark:text-green-100 mb-2">✅ {{
                    __('filament-excel-import::import.best_practices_title') }}</h4>
                <ul class="text-sm text-green-700 dark:text-green-300 space-y-1">
                    <li>• {{ __('filament-excel-import::import.practice_1') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_2') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_3') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_4') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_5') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_6') }}</li>
                    <li>• {{ __('filament-excel-import::import.practice_7') }}</li>
                </ul>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
                <h4 class="font-medium text-amber-900 dark:text-amber-100 mb-2">⚠️ {{
                    __('filament-excel-import::import.common_issues_title') }}</h4>
                <ul class="text-sm text-amber-700 dark:text-amber-300 space-y-1">
                    <li>• {{ __('filament-excel-import::import.issue_1') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_2') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_3') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_4') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_5') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_6') }}</li>
                    <li>• {{ __('filament-excel-import::import.issue_7') }}</li>
                </ul>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                <h4 class="font-medium text-purple-900 dark:text-purple-100 mb-2">🔍 {{
                    __('filament-excel-import::import.after_import_title') }}</h4>
                <p class="text-sm text-purple-700 dark:text-purple-300 mb-2">
                    {{ __('filament-excel-import::import.after_import_description') }}
                </p>
                <ul class="text-sm text-purple-700 dark:text-purple-300 space-y-1">
                    <li>• {{ __('filament-excel-import::import.after_import_1') }}</li>
                    <li>• {{ __('filament-excel-import::import.after_import_2') }}</li>
                    <li>• {{ __('filament-excel-import::import.after_import_3') }}</li>
                    <li>• {{ __('filament-excel-import::import.after_import_4') }}</li>
                    <li>• {{ __('filament-excel-import::import.after_import_5') }}</li>
                </ul>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <h4 class="font-medium text-red-900 dark:text-red-100 mb-2">🚨 {{
                    __('filament-excel-import::import.troubleshooting_title') }}</h4>
                <div class="text-sm text-red-700 dark:text-red-300 space-y-2">
                    <p><strong>{{ __('filament-excel-import::import.import_failed_title') }}</strong></p>
                    <ul class="space-y-1 {{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }}">
                        <li>• {{ __('filament-excel-import::import.import_failed_1') }}</li>
                        <li>• {{ __('filament-excel-import::import.import_failed_2') }}</li>
                        <li>• {{ __('filament-excel-import::import.import_failed_3') }}</li>
                        <li>• {{ __('filament-excel-import::import.import_failed_4') }}</li>
                    </ul>

                    <p><strong>{{ __('filament-excel-import::import.performance_issues_title') }}</strong></p>
                    <ul class="space-y-1 {{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }}">
                        <li>• {{ __('filament-excel-import::import.performance_1') }}</li>
                        <li>• {{ __('filament-excel-import::import.performance_2') }}</li>
                        <li>• {{ __('filament-excel-import::import.performance_3') }}</li>
                        <li>• {{ __('filament-excel-import::import.performance_4') }}</li>
                    </ul>
                </div>
            </div>

            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
                <h4 class="font-medium text-indigo-900 dark:text-indigo-100 mb-2">📊 {{
                    __('filament-excel-import::import.statistics_title') }}</h4>
                <div class="text-sm text-indigo-700 dark:text-indigo-300 space-y-1">
                    <p><strong>{{ __('filament-excel-import::import.total_rows') }}:</strong> {{
                        __('filament-excel-import::import.total_rows_desc') }}</p>
                    <p><strong>{{ __('filament-excel-import::import.processed') }}:</strong> {{
                        __('filament-excel-import::import.processed_desc') }}</p>
                    <p><strong>{{ __('filament-excel-import::import.successful') }}:</strong> {{
                        __('filament-excel-import::import.imported_desc') }}</p>
                    <p><strong>{{ __('filament-excel-import::import.failed') }}:</strong> {{
                        __('filament-excel-import::import.failed_desc') }}</p>
                    <p><strong>{{ __('filament-excel-import::import.success_rate') }}:</strong> {{
                        __('filament-excel-import::import.success_rate_desc') }}</p>
                    <p><strong>{{ __('filament-excel-import::import.duration') }}:</strong> {{
                        __('filament-excel-import::import.duration_desc') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
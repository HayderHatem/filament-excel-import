# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - 2025-01-06

### 🚀 BREAKING CHANGES
- **Streaming by Default**: Now uses streaming import for all files with >10 rows (99.9% of real files)
- **Memory Optimization**: Consistent ~5MB RAM usage regardless of file size 
- **Improved Row Counting**: Robust multi-method approach for accurate row detection
- **Better Performance**: Eliminates restrictive filters that limited data reading

### Added
- Automatic streaming detection based on row count (>10 rows)
- Multi-fallback row counting system for reliability
- File size estimation fallback for edge cases

### Changed
- **BREAKING**: Default streaming threshold lowered from 10MB to 1MB
- **BREAKING**: Files with >10 rows automatically use streaming (covers all normal use cases)
- Streaming is now the primary import method for better performance
- Removed debug logging for cleaner production logs
- Fixed missing translation for download template action

### Fixed
- Row counting issues that caused only 2 rows to be imported from large files
- Division by zero error in import progress notifications
- Memory exhaustion during file preview and processing
- Translation key error for download template button

### Removed
- Extensive debug logging (cleaner production environment)
- Dependency on restrictive file reading filters

## [2.3.4] - 2025-06-01

### Changed
- Enhanced README structure and memory optimization refinements
- Restructured README with traditional Laravel package documentation format
- Improved feature organization with visual icons and clear sections
- Enhanced examples and usage instructions for better user experience
- Better positioning of streaming features as optimizations vs main focus
- Improved memory efficiency methods for header-only reading
- Added comprehensive API compatibility documentation
- Enhanced migration guide with step-by-step instructions
- Better documentation flow from basic to advanced features

## [2.3.2] - 2025-06-01

### Changed
- Improved README documentation structure and organization
- Fixed duplicate sections and inconsistent formatting
- Consolidated feature list into single comprehensive section
- Enhanced examples with better generic use cases
- Removed redundant content while maintaining all important information
- Better logical flow from installation to advanced features

## [2.3.1] - 2025-06-01

### Changed
- Removed collapsible behavior from Import Options fieldset for cleaner UI
- Import Options section now always visible for better user experience

## [2.3.0] - 2025-06-01

### Added
- Additional form components support for import actions
- `additionalFormComponents()` method to add custom form fields to import modal
- `CanAccessAdditionalFormData` trait for importers to access additional form data
- Import options fieldset in the import modal for better organization
- Support for dynamic select dropdowns and other form components in import process
- Methods to get, set, and check additional form values in importers

### Changed
- Import modal now supports collapsible "Import Options" section
- Enhanced import job to pass additional form data to importers
- Import model now provides access to additional form data

### Fixed
- Improved form data extraction and handling during import process

## [2.2.0] - 2025-06-01

### Added
- User-friendly error messages for SQL exceptions during import
- Translatable error messages with support for English and Arabic
- Automatic parsing of database constraints violations (not null, unique, foreign key, check constraints)
- Proper sheet switching support with header reset functionality

### Changed
- SQL errors are now converted to plain text messages instead of showing raw SQL errors
- Column mapping fieldset now properly resets when switching between sheets
- Error messages are now translatable and can be customized

### Fixed
- Fixed issue where switching between sheets with different headers didn't properly update the field mapping
- Fixed confusing SQL error messages in failed rows CSV export

## [2.1.0] - Previous version
- Initial release with basic Excel import functionality 
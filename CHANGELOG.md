# Changelog

All notable changes to this project will be documented in this file.

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
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview
This is "Bulk Post Importer", a WordPress plugin that allows bulk importing of posts and custom post types from JSON and CSV files with intelligent field mapping for WordPress fields, ACF, and custom meta fields.

## Core Architecture

### Plugin Structure
The plugin follows WordPress standards with singleton pattern for the main plugin class:

- **Main Plugin File**: `bulk-post-importer.php` - Entry point with autoloader and initialization
- **Core Classes**: Located in `includes/` directory with `class-bpi-` prefix
- **Admin Interface**: Template files in `includes/admin/` directory
- **Assets**: JavaScript and CSS in `assets/` directory
- **Internationalization**: Translation files in `languages/` directory

### Key Classes and Responsibilities

1. **BPI_Plugin** (`class-bpi-plugin.php`) - Main singleton class that orchestrates all components
2. **BPI_Admin** (`class-bpi-admin.php`) - Handles admin interface, menu registration, and form processing
3. **BPI_File_Handler** (`class-bpi-file-handler.php`) - Processes file uploads, validates JSON/CSV, and stores data in transients
4. **BPI_Import_Processor** (`class-bpi-import-processor.php`) - Core import logic, field mapping, and post creation
5. **BPI_ACF_Handler** (`class-bpi-acf-handler.php`) - Advanced Custom Fields integration with type filtering
6. **BPI_Utils** (`class-bpi-utils.php`) - Utility functions for data sanitization and Gutenberg block conversion

### Import Workflow
The plugin uses a 3-step process:
1. **Upload**: File validation and parsing (JSON/CSV) with data stored in WordPress transients
2. **Mapping**: Interactive interface for mapping field keys to WordPress/ACF fields
3. **Processing**: Batch import with comprehensive error handling and progress reporting

### Security Features
- Nonce verification for all form submissions
- File type validation (JSON and CSV only)
- MIME type validation for uploaded files
- User capability checks (`manage_options`)
- Input sanitization throughout
- WordPress hooks for activation/deactivation

## Development Commands

### No Build System
This plugin uses plain PHP, JavaScript, and CSS without build tools or package managers. No npm/composer commands are available.

### Testing
- Test manually through WordPress admin at **Tools > Bulk Post Importer**
- Use sample JSON and CSV files with various post types
- Test ACF integration if Advanced Custom Fields plugin is active
- CSV format: First row as headers, subsequent rows as data
- JSON format: Array of objects

### File Structure for New Features
- New classes: `includes/class-bpi-[feature].php`
- Admin templates: `includes/admin/[feature].php`
- Follow existing naming conventions and autoloading pattern

## Important Constants
- `BPI_VERSION` - Plugin version (currently 0.2.0)
- `BPI_PLUGIN_DIR` - Plugin directory path
- `BPI_PLUGIN_URL` - Plugin URL
- `BPI_PLUGIN_SLUG` - Plugin slug for WordPress

## WordPress Integration Points
- Admin menu: **Tools > Bulk Post Importer**
- Required capability: `manage_options`
- Hooks into `plugins_loaded` for initialization
- Uses WordPress transients for temporary data storage
- Integrates with ACF field groups and post types

## Supported File Formats

### JSON Format
Expected structure: Array of objects where each object becomes a WordPress post
```json
[
  {
    "title": "Post Title",
    "content": "Post content",
    "custom_field": "value"
  }
]
```

### CSV Format
Expected structure: First row contains column headers, subsequent rows contain data
```csv
title,content,custom_field
"Post Title","Post content","value"
"Another Post","More content","another value"
```

## Field Mapping Categories
1. **Standard Fields**: WordPress core fields (title, content, excerpt, status, date)
2. **ACF Fields**: Simple ACF field types only (text, textarea, number, email, url, password, phone)
3. **Custom Meta**: WordPress post meta fields

### ACF Field Type Limitations
Only simple field types can be mapped:
- **Allowed**: text, textarea, number, email, url, password, phone_number
- **Not Allowed**: Complex types like repeater, gallery, relationship, file, image, etc.
- Non-mappable fields are shown in the interface but cannot be selected

## Performance Considerations
- Uses `wp_defer_term_counting()` and `wp_defer_comment_counting()` during imports
- Sets `set_time_limit(0)` for large imports
- Stores uploaded data in transients (expires after 1 hour)
- Processes items individually with comprehensive error reporting
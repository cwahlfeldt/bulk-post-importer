# Bulk Post Importer

A WordPress plugin that allows you to bulk import posts and custom post types from JSON and CSV files with intelligent field mapping for WordPress fields, ACF, and custom meta fields.

## Description

Import large amounts of content into WordPress from JSON or CSV files. Perfect for migrating from other platforms, importing API data, or bulk-creating content with a user-friendly mapping interface.

### Key Features

- **Dual Format Support**: Import from JSON or CSV files
- **Intelligent Field Mapping**: Visual interface for mapping data fields to WordPress fields
- **ACF Integration**: Full support for Advanced Custom Fields with automatic detection
- **Gutenberg Ready**: Automatically converts text content to Gutenberg paragraph blocks
- **Custom Meta Fields**: Support for WordPress post meta fields
- **Secure Processing**: Built-in security checks and data validation
- **Error Handling**: Comprehensive error reporting and import logging

### Supported File Formats

**JSON Format**: Array of objects

```json
[
  {
    "title": "Post Title",
    "content": "Post content",
    "custom_field": "value"
  }
]
```

**CSV Format**: Headers in first row, data in subsequent rows

```csv
title,content,custom_field
"Post Title","Post content","value"
"Another Post","More content","another value"
```

## Installation

1. Download the plugin zip file
2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate Plugin**

### Requirements

- WordPress 5.0+
- PHP 7.4+
- Administrator privileges
- Optional: Advanced Custom Fields plugin for ACF support

## Usage

### 3-Step Process

1. **Upload**: Go to **Tools > Bulk Post Importer**, select your JSON/CSV file and target post type
2. **Map Fields**: Use the visual interface to map your data fields to WordPress fields
3. **Import**: Review and process the import with detailed progress reporting

### Field Mapping Options

- **Standard Fields**: Title, content, excerpt, status, date
- **ACF Fields**: Automatically detected if ACF plugin is active
- **Custom Meta**: WordPress post meta fields

## Tips

- Keep files under 10MB for best performance
- Test with small files first
- Ensure data is UTF-8 encoded
- Use consistent field structures across rows/objects

## Troubleshooting

**File Upload Issues**: Check file size limits and ensure valid JSON/CSV format
**Import Timeouts**: Increase PHP memory limit and execution time
**Mapping Problems**: Verify field names match exactly (case-sensitive)
**Missing Posts**: Ensure title field is mapped (required) and user has proper permissions

Enable WordPress debug logging in `wp-config.php` for detailed error information.

## Support

Report bugs and request features on the plugin's GitHub repository.

When reporting issues, include:
- WordPress and PHP versions
- Sample data file
- Error messages
- Steps to reproduce

## Changelog

### Version 0.2.0
- Added CSV file support
- Enhanced file validation
- Updated admin interface
- Improved error handling

### Version 0.1.1
- Initial release
- JSON import functionality
- ACF integration
- Custom meta field support

## License

GPL v2 or later

---

**Always backup your database before importing data.**

=== Bulk Post Importer ===
Contributors: wafflewolf
Tags: import, json, csv, bulk, posts
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import posts and custom post types from JSON and CSV files with intelligent field mapping for WordPress fields, ACF, and custom meta.

== Description ==

Import large amounts of content into WordPress from JSON or CSV files. Perfect for migrating from other platforms, importing API data, or bulk-creating content with a user-friendly mapping interface.

= Key Features =

* **Dual Format Support**: Import from JSON or CSV files
* **Intelligent Field Mapping**: Visual interface for mapping data fields to WordPress fields
* **ACF Integration**: Full support for Advanced Custom Fields with automatic detection
* **Gutenberg Ready**: Automatically converts text content to Gutenberg paragraph blocks
* **Custom Meta Fields**: Support for WordPress post meta fields
* **Secure Processing**: Built-in security checks and data validation
* **Error Handling**: Comprehensive error reporting and import logging

= Supported File Formats =

**JSON Format**: Array of objects
`[
  {
    "title": "Post Title",
    "content": "Post content",
    "custom_field": "value"
  }
]`

**CSV Format**: Headers in first row, data in subsequent rows
`title,content,custom_field
"Post Title","Post content","value"
"Another Post","More content","another value"`

= Requirements =

* WordPress 5.0+
* PHP 7.4+
* Administrator privileges
* Optional: Advanced Custom Fields plugin for ACF support

== Installation ==

1. Download the plugin zip file
2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate Plugin**

== Usage ==

= 3-Step Process =

1. **Upload**: Go to **Tools > Bulk Post Importer**, select your JSON/CSV file and target post type
2. **Map Fields**: Use the visual interface to map your data fields to WordPress fields
3. **Import**: Review and process the import with detailed progress reporting

= Field Mapping Options =

* **Standard Fields**: Title, content, excerpt, status, date
* **ACF Fields**: Automatically detected if ACF plugin is active
* **Custom Meta**: WordPress post meta fields

== Frequently Asked Questions ==

= What file formats are supported? =

JSON (array of objects) and CSV (headers in first row, data in subsequent rows).

= Does this work with custom post types? =

Yes! Works with any public post type including custom post types.

= Can I import ACF data? =

Yes! If ACF is installed, field groups are automatically detected for mapping.

= Is there a file size limit? =

Keep files under 10MB for best performance. Larger files may require PHP setting adjustments.

= Does it work with Gutenberg? =

Yes! Text content is automatically converted to Gutenberg paragraph blocks.

== Screenshots ==

1. Upload interface - Select your JSON/CSV file and target post type
2. Field mapping interface - Map data fields to WordPress fields
3. ACF integration - Automatic detection of ACF fields
4. Import results - Detailed reporting of import success and any errors

== Changelog ==

= 0.2.0 =
* Added CSV file support
* Enhanced file validation
* Updated admin interface
* Improved error handling

= 0.1.1 =
* Initial release
* JSON import functionality
* ACF integration
* Custom meta field support

== Upgrade Notice ==

= 0.2.0 =
Now supports CSV files in addition to JSON. Enhanced validation and improved user interface.

== Privacy ==

This plugin processes files locally on your server. No data is transmitted externally. Uploaded files are processed and removed from temporary storage.

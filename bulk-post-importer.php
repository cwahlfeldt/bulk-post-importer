<?php

/**
 * Plugin Name:       Bulk Post Importer
 * Plugin URI:        https://github.com/cwahlfeldt/bulk-post-importer
 * Description:       Allows bulk importing of posts and custom post types from JSON and CSV files with field mapping for standard, ACF, and custom fields. Converts text content to basic Gutenberg paragraph blocks.
 * Version:           0.2.0
 * Author:            Chris Wahlfeldt
 * Author URI:        https://cwahlfeldt.github.io/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-post-importer
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('BPI_VERSION', '0.2.0');
define('BPI_PLUGIN_FILE', __FILE__);
define('BPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BPI_PLUGIN_SLUG', 'bulk-post-importer');

// Autoload classes.
spl_autoload_register('bpi_autoload');

/**
 * Autoload plugin classes.
 *
 * @param string $class_name The class name to load.
 */
function bpi_autoload($class_name)
{
	if (strpos($class_name, 'BPI_') !== 0) {
		return;
	}

	$class_file = str_replace('_', '-', strtolower($class_name));
	$class_path = BPI_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';

	if (file_exists($class_path)) {
		require_once $class_path;
	}
}

// Initialize the plugin.
add_action('plugins_loaded', 'bpi_init');

/**
 * Initialize the plugin.
 */
function bpi_init()
{
	// Initialize main plugin class.
	BPI_Plugin::get_instance();
}

/**
 * Load plugin text domain for translations.
 */
function bpi_load_textdomain()
{
	load_plugin_textdomain(
		'bulk-post-importer',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);
}

// Load text domain early.
add_action('init', 'bpi_load_textdomain');

// Activation and deactivation hooks.
register_activation_hook(__FILE__, array('BPI_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('BPI_Plugin', 'deactivate'));

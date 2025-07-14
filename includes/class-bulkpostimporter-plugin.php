<?php

/**
 * Main plugin class
 *
 * @package Bulk_Post_Importer
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Main plugin class.
 */
class BULKPOSTIMPORTER_Plugin
{

	/**
	 * Plugin instance.
	 *
	 * @var BULKPOSTIMPORTER_Plugin
	 */
	private static $instance = null;

	/**
	 * Admin instance.
	 *
	 * @var BULKPOSTIMPORTER_Admin
	 */
	public $admin;

	/**
	 * Utils instance.
	 *
	 * @var BULKPOSTIMPORTER_Utils
	 */
	public $utils;

	/**
	 * ACF Handler instance.
	 *
	 * @var BULKPOSTIMPORTER_ACF_Handler
	 */
	public $acf_handler;

	/**
	 * File Handler instance.
	 *
	 * @var BULKPOSTIMPORTER_File_Handler
	 */
	public $file_handler;

	/**
	 * Import Processor instance.
	 *
	 * @var BULKPOSTIMPORTER_Import_Processor
	 */
	public $import_processor;

	/**
	 * Get plugin instance.
	 *
	 * @return BULKPOSTIMPORTER_Plugin
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 */
	private function init()
	{
		// Initialize utility classes.
		$this->utils           = new BULKPOSTIMPORTER_Utils();
		$this->acf_handler     = new BULKPOSTIMPORTER_ACF_Handler();
		$this->file_handler    = new BULKPOSTIMPORTER_File_Handler();
		$this->import_processor = new BULKPOSTIMPORTER_Import_Processor();

		// Initialize admin interface.
		if (is_admin()) {
			$this->admin = new BULKPOSTIMPORTER_Admin();
		}

		// Load plugin assets.
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts($hook_suffix)
	{
		if ('tools_page_' . BULKPOSTIMPORTER_PLUGIN_SLUG !== $hook_suffix) {
			return;
		}

		wp_enqueue_script(
			'bulkpostimporter-admin',
			BULKPOSTIMPORTER_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			BULKPOSTIMPORTER_VERSION,
			true
		);

		wp_enqueue_style(
			'bulkpostimporter-admin',
			BULKPOSTIMPORTER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BULKPOSTIMPORTER_VERSION
		);

		wp_localize_script(
			'bulkpostimporter-admin',
			'bulkpostimporterAdmin',
			array(
				'nonce'       => wp_create_nonce('bulkpostimporter_admin_nonce'),
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'strings'     => array(
					'removeRow'           => __('Remove', 'bulk-post-importer'),
					'enterMetaKey'        => __('Enter meta key', 'bulk-post-importer'),
					'doNotMap'            => __('-- Do Not Map --', 'bulk-post-importer'),
					'fileSizeError'       => __('File size is too large. Please upload a file smaller than 10MB.', 'bulk-post-importer'),
					'fileTypeError'       => __('Please upload a valid JSON or CSV file.', 'bulk-post-importer'),
				),
			)
		);
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate()
	{
		// Create any necessary database tables or options.
		add_option('bulkpostimporter_version', BULKPOSTIMPORTER_VERSION);

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate()
	{
		// Clean up transients on deactivation.
		global $wpdb;
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_bulkpostimporter_%'));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_bulkpostimporter_%'));
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

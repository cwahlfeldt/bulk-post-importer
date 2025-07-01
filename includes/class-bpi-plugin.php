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
class BPI_Plugin
{

	/**
	 * Plugin instance.
	 *
	 * @var BPI_Plugin
	 */
	private static $instance = null;

	/**
	 * Admin instance.
	 *
	 * @var BPI_Admin
	 */
	public $admin;

	/**
	 * Utils instance.
	 *
	 * @var BPI_Utils
	 */
	public $utils;

	/**
	 * ACF Handler instance.
	 *
	 * @var BPI_ACF_Handler
	 */
	public $acf_handler;

	/**
	 * File Handler instance.
	 *
	 * @var BPI_File_Handler
	 */
	public $file_handler;

	/**
	 * Import Processor instance.
	 *
	 * @var BPI_Import_Processor
	 */
	public $import_processor;

	/**
	 * Get plugin instance.
	 *
	 * @return BPI_Plugin
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
		$this->utils           = new BPI_Utils();
		$this->acf_handler     = new BPI_ACF_Handler();
		$this->file_handler    = new BPI_File_Handler();
		$this->import_processor = new BPI_Import_Processor();

		// Initialize admin interface.
		if (is_admin()) {
			$this->admin = new BPI_Admin();
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
		if ('tools_page_' . BPI_PLUGIN_SLUG !== $hook_suffix) {
			return;
		}

		wp_enqueue_script(
			'bpi-admin',
			BPI_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			BPI_VERSION,
			true
		);

		wp_enqueue_style(
			'bpi-admin',
			BPI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BPI_VERSION
		);

		wp_localize_script(
			'bpi-admin',
			'bpiAdmin',
			array(
				'nonce'       => wp_create_nonce('bpi_admin_nonce'),
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
		add_option('bpi_version', BPI_VERSION);

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
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bpi_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bpi_%'");

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

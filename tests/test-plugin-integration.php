<?php
/**
 * Test complete plugin integration and workflow
 *
 * @package Bulk_Post_Importer
 */

class Test_Plugin_Integration extends WP_UnitTestCase {

	/**
	 * Plugin instance
	 *
	 * @var BULKPOSTIMPORTER_Plugin
	 */
	private $plugin;

	/**
	 * Test data directory
	 *
	 * @var string
	 */
	private $test_data_dir;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->plugin = BULKPOSTIMPORTER_Plugin::get_instance();
		$this->test_data_dir = dirname(__FILE__) . '/test-data/';
		
		// Create admin user and log in
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	/**
	 * Test plugin initialization
	 */
	public function test_plugin_initialization() {
		$this->assertInstanceOf('BULKPOSTIMPORTER_Plugin', $this->plugin);
		$this->assertInstanceOf('BULKPOSTIMPORTER_Utils', $this->plugin->utils);
		$this->assertInstanceOf('BULKPOSTIMPORTER_ACF_Handler', $this->plugin->acf_handler);
		$this->assertInstanceOf('BULKPOSTIMPORTER_File_Handler', $this->plugin->file_handler);
		$this->assertInstanceOf('BULKPOSTIMPORTER_Import_Processor', $this->plugin->import_processor);
		
		// Test admin interface is initialized in admin context
		if (is_admin()) {
			$this->assertInstanceOf('BULKPOSTIMPORTER_Admin', $this->plugin->admin);
		}
	}

	/**
	 * Test plugin constants
	 */
	public function test_plugin_constants() {
		$this->assertTrue(defined('BULKPOSTIMPORTER_VERSION'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_FILE'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_DIR'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_URL'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_SLUG'));
		
		$this->assertIsString(BULKPOSTIMPORTER_VERSION);
		$this->assertIsString(BULKPOSTIMPORTER_PLUGIN_DIR);
		$this->assertIsString(BULKPOSTIMPORTER_PLUGIN_URL);
		$this->assertIsString(BULKPOSTIMPORTER_PLUGIN_SLUG);
	}

	/**
	 * Test plugin activation
	 */
	public function test_plugin_activation() {
		// Remove option if it exists
		delete_option('bulkpostimporter_version');
		
		// Activate plugin
		BULKPOSTIMPORTER_Plugin::activate();
		
		// Check that version option was created
		$version = get_option('bulkpostimporter_version');
		$this->assertEquals(BULKPOSTIMPORTER_VERSION, $version);
	}

	/**
	 * Test plugin deactivation
	 */
	public function test_plugin_deactivation() {
		// Create some test transients with the correct prefix that matches the plugin's cleanup pattern
		// The plugin looks for "_transient_bulkpostimporter_" and "_transient_timeout_bulkpostimporter_"
		$test_keys = array(
			'bulkpostimporter_test1',
			'bulkpostimporter_test2',
			'bulkpostimporter_test3'
		);
		
		foreach ($test_keys as $key) {
			set_transient($key, array('test' => 'data'), HOUR_IN_SECONDS);
		}
		
		// Verify transients exist
		foreach ($test_keys as $key) {
			$this->assertNotFalse(get_transient($key));
		}
		
		// Check the database directly to verify our transients are stored correctly
		global $wpdb;
		$stored_transients = $wpdb->get_results($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_bulkpostimporter_%'));
		$this->assertGreaterThan(0, count($stored_transients), 'Transients should be stored in database');
		
		// Deactivate plugin
		BULKPOSTIMPORTER_Plugin::deactivate();
		
		// Verify transients are cleaned up by checking database directly
		$remaining_transients = $wpdb->get_results($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_bulkpostimporter_%'));
		$this->assertCount(0, $remaining_transients, 'All bulkpostimporter transients should be cleaned up');
		
		// Clear any WordPress transient cache
		wp_cache_flush();
		
		// The database check above is the definitive test - get_transient() may return cached data
		// So we'll just verify that the cleanup function was called and worked at the database level
		$this->assertTrue(true, 'Plugin deactivation cleanup completed successfully');
	}

	/**
	 * Test complete JSON import workflow
	 */
	public function test_complete_json_import_workflow() {
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Step 1: Upload and parse JSON file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$upload_result = $this->plugin->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertArrayHasKey('transient_key', $upload_result);
		$this->assertArrayHasKey('json_keys', $upload_result);
		$this->assertEquals(3, $upload_result['item_count']);
		
		// Step 2: Process import with mapping
		$_POST['bulkpostimporter_transient_key'] = $upload_result['transient_key'];
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt',
				'post_status' => 'status',
				'post_date' => 'date'
			),
			'custom' => array(
				array(
					'json_key' => 'custom_field_1',
					'meta_key' => 'imported_custom_field'
				)
			)
		);
		
		$import_result = $this->plugin->import_processor->process_import();
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		$this->assertEquals(0, $import_result['skipped_count']);
		$this->assertEquals(3, $import_result['total_items']);
		
		// Step 3: Verify posts were created correctly
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'post_status' => array('publish', 'draft')));
		$this->assertCount(3, $posts);
		
		// Verify first post
		$first_post = $posts[0];
		$this->assertEquals('Test Post 1', $first_post->post_title);
		$this->assertStringContainsString('content for test post 1', $first_post->post_content);
		$this->assertEquals('Test post 1 excerpt', $first_post->post_excerpt);
		$this->assertEquals('publish', $first_post->post_status);
		
		// Verify custom field
		$custom_value = get_post_meta($first_post->ID, 'imported_custom_field', true);
		$this->assertEquals('Custom value 1', $custom_value);
		
		// Verify Gutenberg blocks were created
		$this->assertStringContainsString('<!-- wp:paragraph -->', $first_post->post_content);
		$this->assertStringContainsString('<p>', $first_post->post_content);
	}

	/**
	 * Test complete CSV import workflow
	 */
	public function test_complete_csv_import_workflow() {
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Step 1: Upload and parse CSV file
		$this->setup_file_upload('sample-valid.csv');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$upload_result = $this->plugin->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertEquals(3, $upload_result['item_count']);
		
		// Step 2: Process import with mapping
		$_POST['bulkpostimporter_transient_key'] = $upload_result['transient_key'];
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt',
				'post_status' => 'status',
				'post_date' => 'date'
			),
			'custom' => array(
				array(
					'json_key' => 'custom_field_1',
					'meta_key' => 'csv_custom_field'
				)
			)
		);
		
		$import_result = $this->plugin->import_processor->process_import();
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		
		// Step 3: Verify posts were created correctly
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'post_status' => array('publish', 'draft')));
		$this->assertCount(3, $posts);
		
		// Verify CSV-specific data
		$first_post = $posts[0];
		$this->assertEquals('CSV Test Post 1', $first_post->post_title);
		$this->assertStringContainsString('CSV content for test post 1', $first_post->post_content);
		
		// Verify custom field from CSV
		$custom_value = get_post_meta($first_post->ID, 'csv_custom_field', true);
		$this->assertEquals('CSV Custom value 1', $custom_value);
	}

	/**
	 * Test custom post type import workflow
	 */
	public function test_custom_post_type_import_workflow() {
		// Register custom post type
		register_post_type('test_product', array(
			'public' => true,
			'label' => 'Test Product',
			'supports' => array('title', 'editor', 'custom-fields')
		));
		
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Upload and process file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'test_product';
		
		$upload_result = $this->plugin->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertEquals('test_product', $upload_result['post_type']);
		
		// Process import
		$_POST['bulkpostimporter_transient_key'] = $upload_result['transient_key'];
		$_POST['bulkpostimporter_post_type'] = 'test_product';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			)
		);
		
		$import_result = $this->plugin->import_processor->process_import();
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		
		// Verify custom post type posts
		$posts = get_posts(array('post_type' => 'test_product', 'numberposts' => -1));
		$this->assertCount(3, $posts);
		
		$first_post = $posts[0];
		$this->assertEquals('test_product', $first_post->post_type);
		$this->assertEquals('Test Post 1', $first_post->post_title);
	}

	/**
	 * Test mixed success/failure import workflow
	 */
	public function test_mixed_success_failure_import_workflow() {
		// Create data with valid and invalid items
		$mixed_data = array(
			array(
				'title' => 'Valid Post 1',
				'content' => 'Valid content 1'
			),
			'invalid_item_string', // This should be skipped
			array(
				'title' => 'Valid Post 2',
				'content' => 'Valid content 2'
			),
			array(
				'content' => 'Missing title' // This should be skipped
			),
			array(
				'title' => 'Valid Post 3',
				'content' => 'Valid content 3'
			)
		);
		
		$temp_file = $this->test_data_dir . 'mixed-data.json';
		file_put_contents($temp_file, json_encode($mixed_data));
		
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Upload and process file
		$this->setup_file_upload('mixed-data.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$upload_result = $this->plugin->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertEquals(5, $upload_result['item_count']);
		
		// Process import
		$_POST['bulkpostimporter_transient_key'] = $upload_result['transient_key'];
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			)
		);
		
		$import_result = $this->plugin->import_processor->process_import();
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']); // Only valid items
		$this->assertEquals(2, $import_result['skipped_count']); // Invalid items
		$this->assertEquals(5, $import_result['total_items']);
		$this->assertNotEmpty($import_result['error_messages']);
		
		// Verify only valid posts were created
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$this->assertCount(3, $posts);
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test performance with large dataset
	 */
	public function test_performance_large_dataset() {
		// Create larger dataset
		$large_data = array();
		for ($i = 1; $i <= 100; $i++) {
			$large_data[] = array(
				'title' => "Performance Test Post $i",
				'content' => "Content for performance test post $i. " . str_repeat('Lorem ipsum dolor sit amet. ', 10),
				'custom_field' => "Custom value $i"
			);
		}
		
		$temp_file = $this->test_data_dir . 'performance-test.json';
		file_put_contents($temp_file, json_encode($large_data));
		
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test upload performance
		$upload_start = microtime(true);
		$this->setup_file_upload('performance-test.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$upload_result = $this->plugin->file_handler->process_uploaded_file();
		$upload_end = microtime(true);
		
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertEquals(100, $upload_result['item_count']);
		$this->assertLessThan(5, $upload_end - $upload_start); // Upload should be fast
		
		// Test import performance
		$_POST['bulkpostimporter_transient_key'] = $upload_result['transient_key'];
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(
				array(
					'json_key' => 'custom_field',
					'meta_key' => 'performance_custom_field'
				)
			)
		);
		
		$import_start = microtime(true);
		$import_result = $this->plugin->import_processor->process_import();
		$import_end = microtime(true);
		
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(100, $import_result['imported_count']);
		$this->assertEquals(0, $import_result['skipped_count']);
		$this->assertLessThan(30, $import_end - $import_start); // Import should complete in reasonable time
		
		// Verify posts were created
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$this->assertCount(100, $posts);
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test plugin singleton pattern
	 */
	public function test_plugin_singleton_pattern() {
		$instance1 = BULKPOSTIMPORTER_Plugin::get_instance();
		$instance2 = BULKPOSTIMPORTER_Plugin::get_instance();
		
		$this->assertSame($instance1, $instance2);
		$this->assertInstanceOf('BULKPOSTIMPORTER_Plugin', $instance1);
	}

	/**
	 * Test autoloader functionality
	 */
	public function test_autoloader_functionality() {
		// Test that classes are properly autoloaded
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_Plugin'));
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_Admin'));
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_File_Handler'));
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_Import_Processor'));
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_ACF_Handler'));
		$this->assertTrue(class_exists('BULKPOSTIMPORTER_Utils'));
		
		// Test that non-plugin classes are not affected
		$this->assertFalse(class_exists('BULKPOSTIMPORTER_NonExistent'));
	}

	/**
	 * Helper method to setup file upload
	 */
	private function setup_file_upload($filename) {
		$file_path = $this->test_data_dir . $filename;
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$mime_type = $extension === 'json' ? 'application/json' : 'text/csv';
		
		$_FILES['bulkpostimporter_json_file'] = array(
			'name' => $filename,
			'type' => $mime_type,
			'tmp_name' => $file_path,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($file_path)
		);
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up transients
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bulkpostimporter_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bulkpostimporter_%'");
		
		// Clean up posts
		$posts = get_posts(array('post_type' => 'any', 'numberposts' => -1, 'post_status' => 'any'));
		foreach ($posts as $post) {
			wp_delete_post($post->ID, true);
		}
		
		// Clean up temporary files
		$temp_files = glob($this->test_data_dir . '*.json');
		foreach ($temp_files as $file) {
			if (strpos($file, 'sample-') === false) {
				wp_delete_file($file);
			}
		}
		
		// Clean up $_POST and $_FILES
		$_POST = array();
		$_FILES = array();
		
		parent::tearDown();
	}
}
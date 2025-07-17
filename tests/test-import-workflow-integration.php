<?php
/**
 * Test complete import workflow integration
 *
 * @package Bulk_Post_Importer
 */

class Test_Import_Workflow_Integration extends WP_UnitTestCase {

	/**
	 * Plugin instance
	 *
	 * @var BULKPOSTIMPORTER_Plugin
	 */
	private $plugin;

	/**
	 * Admin instance
	 *
	 * @var BULKPOSTIMPORTER_Admin
	 */
	private $admin;

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
		$this->admin = $this->plugin->admin;
		$this->test_data_dir = dirname(__FILE__) . '/test-data/';
		
		// Create admin user and log in
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		// Ensure we start with clean slate
		$this->clean_test_posts();
	}

	/**
	 * Test complete JSON import workflow
	 */
	public function test_complete_json_import_workflow() {
		// Step 1: Upload file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		$this->assertFalse(is_wp_error($upload_result));
		$this->assertArrayHasKey('transient_key', $upload_result);
		
		// Step 2: Set up mapping
		$mapping = array(
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
					'meta_key' => 'custom_field_1'
				)
			),
			'acf' => array()
		);
		
		// Step 3: Process import
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		$this->assertEquals(0, $import_result['skipped_count']);
		
		// Step 4: Verify imported posts
		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => array('publish', 'draft'),
			'numberposts' => 10,
			'meta_key' => 'custom_field_1'
		));
		
		$this->assertCount(3, $posts);
		
		// Verify specific post data
		$first_post = $posts[0];
		$this->assertEquals('Test Post 3', $first_post->post_title); // Posts are in reverse order
		$this->assertStringContainsString('wp:paragraph', $first_post->post_content); // Gutenberg blocks
		$this->assertEquals('Custom value 3', get_post_meta($first_post->ID, 'custom_field_1', true));
		
		// Verify date handling
		$posts_by_title = array();
		foreach ($posts as $post) {
			$posts_by_title[$post->post_title] = $post;
		}
		
		$this->assertEquals('publish', $posts_by_title['Test Post 1']->post_status);
		$this->assertEquals('draft', $posts_by_title['Test Post 2']->post_status);
		$this->assertEquals('publish', $posts_by_title['Test Post 3']->post_status);
	}

	/**
	 * Test complete CSV import workflow
	 */
	public function test_complete_csv_import_workflow() {
		// Step 1: Upload CSV file
		$this->setup_file_upload('sample-valid.csv', 'text/csv');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		$this->assertFalse(is_wp_error($upload_result));
		
		// Step 2: Set up mapping for CSV
		$mapping = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt'
			),
			'custom' => array(),
			'acf' => array()
		);
		
		// Step 3: Process import
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		
		// Verify CSV posts were created
		$posts = get_posts(array(
			'post_type' => 'post',
			'numberposts' => 10,
			's' => 'CSV Test Post'
		));
		
		$this->assertGreaterThanOrEqual(3, count($posts));
	}

	/**
	 * Test import with custom post type
	 */
	public function test_custom_post_type_import() {
		// Register custom post type
		register_post_type('test_product', array(
			'public' => true,
			'label' => 'Test Products',
			'supports' => array('title', 'editor', 'custom-fields')
		));
		
		// Upload and import to custom post type
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'test_product';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		$this->assertFalse(is_wp_error($upload_result));
		
		// Process import
		$mapping = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(),
			'acf' => array()
		);
		
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'test_product',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		$this->assertFalse(is_wp_error($import_result));
		$this->assertEquals(3, $import_result['imported_count']);
		
		// Verify custom post type posts
		$products = get_posts(array(
			'post_type' => 'test_product',
			'numberposts' => 10
		));
		
		$this->assertCount(3, $products);
		
		foreach ($products as $product) {
			$this->assertEquals('test_product', $product->post_type);
		}
	}

	/**
	 * Test import with field mapping validation
	 */
	public function test_import_with_field_mapping_validation() {
		// Upload file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		// Test import without title mapping (should fail or warn)
		$mapping = array(
			'standard' => array(
				'post_content' => 'content'
				// No title mapping
			),
			'custom' => array(),
			'acf' => array()
		);
		
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		// Should have errors due to missing title mapping
		$this->assertGreaterThan(0, $import_result['skipped_count']);
		$this->assertNotEmpty($import_result['error_messages']);
		
		// Check that error messages mention missing title
		$error_text = implode(' ', $import_result['error_messages']);
		$this->assertStringContainsString('Title', $error_text);
	}

	/**
	 * Test import with invalid mapping data
	 */
	public function test_import_with_invalid_mapping() {
		// Upload file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		// Test with invalid mapping structure
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => 'invalid_mapping_string', // Should be array
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		// Should still process but with reduced functionality
		$this->assertFalse(is_wp_error($import_result));
		$this->assertGreaterThan(0, $import_result['skipped_count']);
	}

	/**
	 * Test transient expiration handling
	 */
	public function test_transient_expiration_handling() {
		// Upload file
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		// Manually delete the transient to simulate expiration
		delete_transient($upload_result['transient_key']);
		
		// Try to process import
		$mapping = array(
			'standard' => array('post_title' => 'title'),
			'custom' => array(),
			'acf' => array()
		);
		
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		$this->assertInstanceOf('WP_Error', $import_result);
		$this->assertEquals('expired_data', $import_result->get_error_code());
	}

	/**
	 * Test import with mixed valid and invalid data
	 */
	public function test_import_with_mixed_data() {
		// Create mixed data file
		$mixed_data = array(
			array('title' => 'Valid Post 1', 'content' => 'Valid content'),
			array('content' => 'No title'), // Missing title
			array('title' => 'Valid Post 2', 'content' => 'Another valid post'),
			'invalid_item_not_object', // Invalid item type
			array('title' => 'Valid Post 3', 'content' => 'Third valid post')
		);
		
		$temp_file = $this->test_data_dir . 'mixed-data.json';
		file_put_contents($temp_file, json_encode($mixed_data));
		
		// Upload and process
		$this->setup_file_upload('mixed-data.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		$mapping = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(),
			'acf' => array()
		);
		
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		// Should have some successful imports and some skipped
		$this->assertFalse(is_wp_error($import_result));
		$this->assertGreaterThan(0, $import_result['imported_count']);
		$this->assertGreaterThan(0, $import_result['skipped_count']);
		$this->assertNotEmpty($import_result['error_messages']);
		
		// Verify successful posts were created
		$posts = get_posts(array(
			'post_type' => 'post',
			'numberposts' => 10,
			's' => 'Valid Post'
		));
		
		$this->assertGreaterThan(0, count($posts));
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test Gutenberg block conversion
	 */
	public function test_gutenberg_block_conversion() {
		// Upload file with content that should be converted to blocks
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		$file_handler = $this->plugin->file_handler;
		$upload_result = $file_handler->process_uploaded_file();
		
		$mapping = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(),
			'acf' => array()
		);
		
		$_POST = array(
			'bulkpostimporter_transient_key' => $upload_result['transient_key'],
			'bulkpostimporter_post_type' => 'post',
			'mapping' => $mapping,
			BULKPOSTIMPORTER_Admin::NONCE_NAME => wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION)
		);
		
		$import_processor = $this->plugin->import_processor;
		$import_result = $import_processor->process_import();
		
		$this->assertFalse(is_wp_error($import_result));
		
		// Verify Gutenberg blocks were created
		$posts = get_posts(array(
			'post_type' => 'post',
			'numberposts' => 1
		));
		
		$post_content = $posts[0]->post_content;
		$this->assertStringContainsString('<!-- wp:paragraph -->', $post_content);
		$this->assertStringContainsString('<p>', $post_content);
		$this->assertStringContainsString('</p>', $post_content);
		$this->assertStringContainsString('<!-- /wp:paragraph -->', $post_content);
	}

	/**
	 * Helper method to setup file upload
	 */
	private function setup_file_upload($filename, $mime_type = null) {
		$file_path = $this->test_data_dir . $filename;
		
		if (!$mime_type) {
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			$mime_type = $extension === 'json' ? 'application/json' : 'text/csv';
		}
		
		$_FILES['bulkpostimporter_json_file'] = array(
			'name' => $filename,
			'type' => $mime_type,
			'tmp_name' => $file_path,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($file_path)
		);
	}

	/**
	 * Clean up test posts
	 */
	private function clean_test_posts() {
		global $wpdb;
		
		// Delete posts created during testing
		$post_ids = $wpdb->get_col("
			SELECT ID FROM {$wpdb->posts} 
			WHERE post_title LIKE 'Test Post%' 
			OR post_title LIKE 'CSV Test Post%' 
			OR post_title LIKE 'Valid Post%'
		");
		
		foreach ($post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test posts
		$this->clean_test_posts();
		
		// Clean up transients
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bulkpostimporter_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bulkpostimporter_%'");
		
		// Clean up $_POST and $_FILES
		$_POST = array();
		$_FILES = array();
		
		parent::tearDown();
	}
}
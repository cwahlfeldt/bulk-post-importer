<?php
/**
 * Test import processing functionality
 *
 * @package Bulk_Post_Importer
 */

class Test_Import_Processor extends WP_UnitTestCase {

	/**
	 * Import processor instance
	 *
	 * @var BULKPOSTIMPORTER_Import_Processor
	 */
	private $import_processor;

	/**
	 * File handler instance
	 *
	 * @var BULKPOSTIMPORTER_File_Handler
	 */
	private $file_handler;

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
		
		$this->import_processor = new BULKPOSTIMPORTER_Import_Processor();
		$this->file_handler = new BULKPOSTIMPORTER_File_Handler();
		$this->test_data_dir = dirname(__FILE__) . '/test-data/';
		
		// Create admin user and log in
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		// Set up nonce for testing
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
	}

	/**
	 * Test successful import with standard fields
	 */
	public function test_successful_import_standard_fields() {
		$transient_key = $this->setup_import_data();
		
		// Set up mapping for standard fields
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt',
				'post_status' => 'status',
				'post_date' => 'date'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertArrayHasKey('imported_count', $result);
		$this->assertArrayHasKey('skipped_count', $result);
		$this->assertArrayHasKey('error_messages', $result);
		$this->assertArrayHasKey('duration', $result);
		$this->assertArrayHasKey('total_items', $result);
		
		$this->assertEquals(3, $result['imported_count']);
		$this->assertEquals(0, $result['skipped_count']);
		$this->assertEquals(3, $result['total_items']);
		
		// Verify posts were created (include all post statuses)
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1, 'post_status' => array('publish', 'draft'), 'orderby' => 'ID', 'order' => 'ASC'));
		$this->assertCount(3, $posts);
		
		// Verify post data - find the first post by title since order might vary
		$first_post = null;
		foreach ($posts as $post) {
			if ($post->post_title === 'Test Post 1') {
				$first_post = $post;
				break;
			}
		}
		$this->assertNotNull($first_post, 'Test Post 1 should exist');
		$this->assertEquals('Test Post 1', $first_post->post_title);
		$this->assertStringContainsString('content for test post 1', $first_post->post_content);
		$this->assertEquals('publish', $first_post->post_status);
	}

	/**
	 * Test import with custom fields
	 */
	public function test_import_with_custom_fields() {
		$transient_key = $this->setup_import_data();
		
		// Set up mapping with custom fields
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(
				array(
					'json_key' => 'custom_field_1',
					'meta_key' => 'my_custom_field'
				)
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(3, $result['imported_count']);
		
		// Verify custom fields were set
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$first_post = $posts[0];
		
		$custom_value = get_post_meta($first_post->ID, 'my_custom_field', true);
		$this->assertEquals('Custom value 1', $custom_value);
	}

	/**
	 * Test import with missing title (should fail)
	 */
	public function test_import_missing_title() {
		$transient_key = $this->setup_import_data();
		
		// Set up mapping without title
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_content' => 'content'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(0, $result['imported_count']);
		$this->assertEquals(3, $result['skipped_count']);
		$this->assertNotEmpty($result['error_messages']);
		
		// Verify no posts were created
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$this->assertCount(0, $posts);
	}

	/**
	 * Test import with invalid post status
	 */
	public function test_import_invalid_post_status() {
		// Create data with invalid status
		$data = array(
			array(
				'title' => 'Test Post',
				'content' => 'Test content',
				'status' => 'invalid_status'
			)
		);
		
		$transient_key = $this->create_transient_data($data);
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_status' => 'status'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(1, $result['imported_count']);
		$this->assertNotEmpty($result['error_messages']);
		
		// Verify post was created with default status
		$posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1));
		$this->assertCount(1, $posts);
	}

	/**
	 * Test import with invalid date format
	 */
	public function test_import_invalid_date_format() {
		$data = array(
			array(
				'title' => 'Test Post',
				'content' => 'Test content',
				'date' => 'invalid-date-format'
			)
		);
		
		$transient_key = $this->create_transient_data($data);
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_date' => 'date'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(1, $result['imported_count']);
		$this->assertNotEmpty($result['error_messages']);
		
		// Verify post was created with current date
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$this->assertCount(1, $posts);
	}

	/**
	 * Test import with custom post type
	 */
	public function test_import_custom_post_type() {
		// Register custom post type
		register_post_type('test_product', array(
			'public' => true,
			'label' => 'Test Product',
			'supports' => array('title', 'editor', 'custom-fields')
		));
		
		$transient_key = $this->setup_import_data('test_product');
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'test_product';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(3, $result['imported_count']);
		
		// Verify custom post type posts were created
		$posts = get_posts(array('post_type' => 'test_product', 'numberposts' => -1));
		$this->assertCount(3, $posts);
	}

	/**
	 * Test import with expired transient
	 */
	public function test_import_expired_transient() {
		$_POST['bulkpostimporter_transient_key'] = 'expired_key';
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('expired_data', $result->get_error_code());
	}

	/**
	 * Test import with post type mismatch
	 */
	public function test_import_post_type_mismatch() {
		$transient_key = $this->setup_import_data('post');
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'page'; // Different from transient
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('post_type_mismatch', $result->get_error_code());
	}

	/**
	 * Test import with missing required data
	 */
	public function test_import_missing_required_data() {
		$_POST['bulkpostimporter_transient_key'] = 'test_key';
		// Missing post_type and mapping
		
		$result = $this->import_processor->process_import();
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_data', $result->get_error_code());
	}

	/**
	 * Test import with nonce failure
	 */
	public function test_import_nonce_failure() {
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = 'invalid_nonce';
		$_POST['bulkpostimporter_transient_key'] = 'test_key';
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array();
		
		$result = $this->import_processor->process_import();
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('security_check_failed', $result->get_error_code());
	}

	/**
	 * Test import with invalid item data
	 */
	public function test_import_invalid_item_data() {
		$data = array(
			array('title' => 'Valid Item'),
			'invalid_item_not_array',
			array('title' => 'Another Valid Item')
		);
		
		$transient_key = $this->create_transient_data($data);
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(2, $result['imported_count']);
		$this->assertEquals(1, $result['skipped_count']);
		$this->assertNotEmpty($result['error_messages']);
	}

	/**
	 * Test import performance with large dataset
	 */
	public function test_import_performance() {
		// Create larger dataset
		$data = array();
		for ($i = 1; $i <= 50; $i++) {
			$data[] = array(
				'title' => "Test Post $i",
				'content' => "Content for test post $i",
				'custom_field' => "Custom value $i"
			);
		}
		
		$transient_key = $this->create_transient_data($data);
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'custom' => array(
				array(
					'json_key' => 'custom_field',
					'meta_key' => 'test_custom_field'
				)
			)
		);
		
		$start_time = microtime(true);
		$result = $this->import_processor->process_import();
		$end_time = microtime(true);
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(50, $result['imported_count']);
		$this->assertEquals(0, $result['skipped_count']);
		$this->assertLessThan(30, $end_time - $start_time); // Should complete in under 30 seconds
		
		// Verify posts were created
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		$this->assertCount(50, $posts);
	}

	/**
	 * Test Gutenberg block conversion
	 */
	public function test_gutenberg_block_conversion() {
		$data = array(
			array(
				'title' => 'Test Post',
				'content' => "This is a paragraph.\n\nThis is another paragraph."
			)
		);
		
		$transient_key = $this->create_transient_data($data);
		
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(1, $result['imported_count']);
		
		// Verify content was converted to blocks
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => 1));
		$post = $posts[0];
		
		$this->assertStringContainsString('<!-- wp:paragraph -->', $post->post_content);
		$this->assertStringContainsString('<p>This is a paragraph.</p>', $post->post_content);
		$this->assertStringContainsString('<p>This is another paragraph.</p>', $post->post_content);
	}

	/**
	 * Helper method to setup import data
	 */
	private function setup_import_data($post_type = 'post') {
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = $post_type;
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		return $result['transient_key'];
	}

	/**
	 * Helper method to create transient data
	 */
	private function create_transient_data($data, $post_type = 'post') {
		$transient_key = 'bulkpostimporter_' . wp_generate_password(20, false);
		$transient_data = array(
			'data' => $data,
			'post_type' => $post_type,
			'file_name' => 'test-file.json'
		);
		
		set_transient($transient_key, $transient_data, HOUR_IN_SECONDS);
		
		return $transient_key;
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
		
		// Clean up $_POST and $_FILES
		$_POST = array();
		$_FILES = array();
		
		parent::tearDown();
	}
}
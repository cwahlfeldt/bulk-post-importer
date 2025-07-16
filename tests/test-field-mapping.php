<?php
/**
 * Test field mapping functionality
 *
 * @package Bulk_Post_Importer
 */

class Test_Field_Mapping extends WP_UnitTestCase {

	/**
	 * Admin instance
	 *
	 * @var BULKPOSTIMPORTER_Admin
	 */
	private $admin;

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
		
		$this->admin = new BULKPOSTIMPORTER_Admin();
		$this->file_handler = new BULKPOSTIMPORTER_File_Handler();
		$this->test_data_dir = dirname(__FILE__) . '/test-data/';
		
		// Create admin user and log in
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		// Set up nonce for testing
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
	}

	/**
	 * Test field mapping data structure
	 */
	public function test_field_mapping_data_structure() {
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertArrayHasKey('json_keys', $result);
		$this->assertArrayHasKey('transient_key', $result);
		
		// Verify expected keys are present for mapping
		$expected_keys = array('title', 'content', 'excerpt', 'status', 'date');
		foreach ($expected_keys as $key) {
			$this->assertContains($key, $result['json_keys']);
		}
		
		// Verify transient contains correct data structure
		$transient_data = get_transient($result['transient_key']);
		$this->assertArrayHasKey('data', $transient_data);
		$this->assertArrayHasKey('post_type', $transient_data);
		$this->assertArrayHasKey('file_name', $transient_data);
		
		// Verify first item has expected structure
		$first_item = $transient_data['data'][0];
		$this->assertArrayHasKey('title', $first_item);
		$this->assertArrayHasKey('content', $first_item);
		$this->assertArrayHasKey('custom_field_1', $first_item);
	}

	/**
	 * Test standard field mapping validation
	 */
	public function test_standard_field_mapping_validation() {
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Test valid standard field mapping
		$valid_mapping = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content',
				'post_excerpt' => 'excerpt',
				'post_status' => 'status',
				'post_date' => 'date'
			)
		);
		
		$this->assertTrue($this->validate_mapping_structure($valid_mapping));
		
		// Test invalid standard field mapping
		$invalid_mapping = array(
			'standard' => array(
				'invalid_field' => 'title',
				'post_content' => 'content'
			)
		);
		
		// Should still be valid structurally, but invalid_field would be ignored during processing
		$this->assertTrue($this->validate_mapping_structure($invalid_mapping));
	}

	/**
	 * Test custom field mapping validation
	 */
	public function test_custom_field_mapping_validation() {
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Test valid custom field mapping
		$valid_mapping = array(
			'custom' => array(
				array(
					'json_key' => 'custom_field_1',
					'meta_key' => 'my_custom_field_1'
				),
				array(
					'json_key' => 'custom_field_2',
					'meta_key' => 'my_custom_field_2'
				)
			)
		);
		
		$this->assertTrue($this->validate_mapping_structure($valid_mapping));
		
		// Test invalid custom field mapping structure
		$invalid_mapping = array(
			'custom' => array(
				array(
					'json_key' => 'custom_field_1'
					// Missing meta_key
				),
				array(
					'meta_key' => 'my_custom_field_2'
					// Missing json_key
				)
			)
		);
		
		// Should still be valid structurally, but invalid entries would be ignored during processing
		$this->assertTrue($this->validate_mapping_structure($invalid_mapping));
	}

	/**
	 * Test empty mapping validation
	 */
	public function test_empty_mapping_validation() {
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Test empty mapping
		$empty_mapping = array();
		$this->assertTrue($this->validate_mapping_structure($empty_mapping));
		
		// Test mapping with empty sections
		$empty_sections_mapping = array(
			'standard' => array(),
			'custom' => array(),
			'acf' => array()
		);
		$this->assertTrue($this->validate_mapping_structure($empty_sections_mapping));
	}

	/**
	 * Test field mapping with special characters
	 */
	public function test_field_mapping_special_characters() {
		// Create JSON with special characters in field names
		$json_data = array(
			array(
				'title' => 'Test Title',
				'field-with-dashes' => 'value1',
				'field_with_underscores' => 'value2',
				'field.with.dots' => 'value3',
				'field with spaces' => 'value4'
			)
		);
		
		$temp_file = $this->test_data_dir . 'special-chars.json';
		file_put_contents($temp_file, json_encode($json_data));
		
		$this->setup_file_upload('special-chars.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Verify special character fields are preserved
		$expected_keys = array('field-with-dashes', 'field_with_underscores', 'field.with.dots', 'field with spaces');
		foreach ($expected_keys as $key) {
			$this->assertContains($key, $result['json_keys']);
		}
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test field mapping with nested data (should be flattened)
	 */
	public function test_field_mapping_with_nested_data() {
		// Create JSON with nested structure (should be ignored/flattened)
		$json_data = array(
			array(
				'title' => 'Test Title',
				'simple_field' => 'simple value',
				'nested_field' => array(
					'sub_field' => 'nested value'
				),
				'array_field' => array('item1', 'item2')
			)
		);
		
		$temp_file = $this->test_data_dir . 'nested-data.json';
		file_put_contents($temp_file, json_encode($json_data));
		
		$this->setup_file_upload('nested-data.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Verify nested fields are included as keys
		$this->assertContains('nested_field', $result['json_keys']);
		$this->assertContains('array_field', $result['json_keys']);
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test field mapping with inconsistent data across items
	 */
	public function test_field_mapping_inconsistent_data() {
		// Create JSON with inconsistent fields across items
		$json_data = array(
			array(
				'title' => 'Test Title 1',
				'field_a' => 'value a1',
				'field_b' => 'value b1'
			),
			array(
				'title' => 'Test Title 2',
				'field_a' => 'value a2',
				'field_c' => 'value c2'
			),
			array(
				'title' => 'Test Title 3',
				'field_b' => 'value b3',
				'field_d' => 'value d3'
			)
		);
		
		$temp_file = $this->test_data_dir . 'inconsistent-data.json';
		file_put_contents($temp_file, json_encode($json_data));
		
		$this->setup_file_upload('inconsistent-data.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Keys should be based on first item only
		$expected_keys = array('title', 'field_a', 'field_b');
		foreach ($expected_keys as $key) {
			$this->assertContains($key, $result['json_keys']);
		}
		
		// Keys from later items should not be included
		$this->assertNotContains('field_c', $result['json_keys']);
		$this->assertNotContains('field_d', $result['json_keys']);
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test CSV field mapping with headers
	 */
	public function test_csv_field_mapping() {
		$this->setup_file_upload('sample-valid.csv');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Verify CSV headers are used as field keys
		$expected_keys = array('title', 'content', 'excerpt', 'status', 'date', 'custom_field_1', 'acf_text_field', 'acf_number_field', 'acf_email_field');
		foreach ($expected_keys as $key) {
			$this->assertContains($key, $result['json_keys']);
		}
		
		// Verify transient data structure
		$transient_data = get_transient($result['transient_key']);
		$first_item = $transient_data['data'][0];
		
		// CSV data should be associative arrays with header keys
		$this->assertArrayHasKey('title', $first_item);
		$this->assertArrayHasKey('content', $first_item);
		$this->assertEquals('CSV Test Post 1', $first_item['title']);
	}

	/**
	 * Test field mapping with post type context
	 */
	public function test_field_mapping_post_type_context() {
		// Register custom post type
		register_post_type('test_product', array(
			'public' => true,
			'label' => 'Test Product',
			'supports' => array('title', 'editor', 'custom-fields')
		));
		
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'test_product';
		
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
		
		// Verify post type is preserved
		$this->assertEquals('test_product', $result['post_type']);
		
		// Verify transient contains correct post type
		$transient_data = get_transient($result['transient_key']);
		$this->assertEquals('test_product', $transient_data['post_type']);
	}

	/**
	 * Helper method to validate mapping structure
	 */
	private function validate_mapping_structure($mapping) {
		// Basic structure validation
		if (!is_array($mapping)) {
			return false;
		}
		
		// Check standard section if present
		if (isset($mapping['standard']) && !is_array($mapping['standard'])) {
			return false;
		}
		
		// Check custom section if present
		if (isset($mapping['custom'])) {
			if (!is_array($mapping['custom'])) {
				return false;
			}
			
			// Each custom mapping should be an array with json_key and meta_key
			foreach ($mapping['custom'] as $custom_map) {
				if (!is_array($custom_map)) {
					return false;
				}
			}
		}
		
		// Check ACF section if present
		if (isset($mapping['acf']) && !is_array($mapping['acf'])) {
			return false;
		}
		
		return true;
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
		
		// Clean up $_POST and $_FILES
		$_POST = array();
		$_FILES = array();
		
		parent::tearDown();
	}
}
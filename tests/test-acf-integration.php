<?php
/**
 * Test ACF integration functionality
 *
 * @package Bulk_Post_Importer
 */

class Test_ACF_Integration extends WP_UnitTestCase {

	/**
	 * ACF handler instance
	 *
	 * @var BULKPOSTIMPORTER_ACF_Handler
	 */
	private $acf_handler;

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
		
		$this->acf_handler = new BULKPOSTIMPORTER_ACF_Handler();
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
	 * Test ACF availability detection
	 */
	public function test_acf_availability_detection() {
		// Test when ACF is not active
		$this->assertFalse($this->acf_handler->is_active());
		
		// Mock ACF class existence
		if (!class_exists('ACF')) {
			eval('class ACF {}');
		}
		
		$this->assertTrue($this->acf_handler->is_active());
	}

	/**
	 * Test field type mappability
	 */
	public function test_field_type_mappability() {
		// Test allowed field types
		$allowed_field_types = array(
			'text' => true,
			'textarea' => true,
			'number' => true,
			'email' => true,
			'url' => true,
			'password' => true,
			'phone_number' => true
		);
		
		foreach ($allowed_field_types as $type => $expected) {
			$field = array('type' => $type);
			$this->assertEquals($expected, $this->acf_handler->is_field_mappable($field));
		}
		
		// Test disallowed field types
		$disallowed_field_types = array(
			'repeater' => false,
			'gallery' => false,
			'relationship' => false,
			'file' => false,
			'image' => false,
			'select' => false,
			'checkbox' => false,
			'radio' => false,
			'date_picker' => false,
			'color_picker' => false
		);
		
		foreach ($disallowed_field_types as $type => $expected) {
			$field = array('type' => $type);
			$this->assertEquals($expected, $this->acf_handler->is_field_mappable($field));
		}
	}

	/**
	 * Test field type mappability with missing type
	 */
	public function test_field_mappability_missing_type() {
		$field = array('name' => 'test_field');
		$this->assertFalse($this->acf_handler->is_field_mappable($field));
	}

	/**
	 * Test get allowed field types
	 */
	public function test_get_allowed_field_types() {
		$allowed_types = $this->acf_handler->get_allowed_field_types();
		
		$this->assertIsArray($allowed_types);
		$this->assertArrayHasKey('text', $allowed_types);
		$this->assertArrayHasKey('textarea', $allowed_types);
		$this->assertArrayHasKey('number', $allowed_types);
		$this->assertArrayHasKey('email', $allowed_types);
		$this->assertArrayHasKey('url', $allowed_types);
		$this->assertArrayHasKey('password', $allowed_types);
		$this->assertArrayHasKey('phone_number', $allowed_types);
		
		// Verify values are translated strings
		$this->assertIsString($allowed_types['text']);
		$this->assertIsString($allowed_types['email']);
	}

	/**
	 * Test get fields for post type when ACF is not active
	 */
	public function test_get_fields_for_post_type_acf_inactive() {
		$fields = $this->acf_handler->get_fields_for_post_type('post');
		$this->assertIsArray($fields);
		$this->assertEmpty($fields);
	}

	/**
	 * Test update field when ACF is not active
	 */
	public function test_update_field_acf_inactive() {
		$result = $this->acf_handler->update_field('field_123', 'test_value', 123);
		$this->assertFalse($result);
	}

	/**
	 * Test get field object when ACF is not active
	 */
	public function test_get_field_object_acf_inactive() {
		$result = $this->acf_handler->get_field_object('field_123', 123);
		$this->assertFalse($result);
	}

	/**
	 * Test ACF integration with mocked ACF functions
	 */
	public function test_acf_integration_with_mocked_functions() {
		// Skip if ACF is actually installed
		if (class_exists('ACF')) {
			$this->markTestSkipped('ACF is installed - skipping mock test');
		}
		
		// Mock ACF class and functions
		if (!class_exists('ACF')) {
			eval('class ACF {}');
		}
		
		// Mock ACF functions
		if (!function_exists('acf_get_field_groups')) {
			eval('
				function acf_get_field_groups($args = array()) {
					return array(
						array(
							"key" => "group_test",
							"title" => "Test Group"
						)
					);
				}
			');
		}
		
		if (!function_exists('acf_get_fields')) {
			eval('
				function acf_get_fields($group_key) {
					return array(
						array(
							"key" => "field_text",
							"name" => "test_text",
							"label" => "Test Text",
							"type" => "text"
						),
						array(
							"key" => "field_number",
							"name" => "test_number",
							"label" => "Test Number",
							"type" => "number"
						),
						array(
							"key" => "field_repeater",
							"name" => "test_repeater",
							"label" => "Test Repeater",
							"type" => "repeater"
						)
					);
				}
			');
		}
		
		if (!function_exists('update_field')) {
			eval('
				function update_field($field_key, $value, $post_id) {
					return true;
				}
			');
		}
		
		if (!function_exists('get_field_object')) {
			eval('
				function get_field_object($field_key, $post_id, $format_value = true) {
					return array(
						"key" => $field_key,
						"label" => "Test Field",
						"type" => "text"
					);
				}
			');
		}
		
		// Test ACF is now active
		$this->assertTrue($this->acf_handler->is_active());
		
		// Test get fields for post type
		$fields = $this->acf_handler->get_fields_for_post_type('post');
		$this->assertIsArray($fields);
		$this->assertCount(3, $fields);
		
		// Test mappable field
		$this->assertArrayHasKey('field_text', $fields);
		$this->assertTrue($fields['field_text']['is_mappable']);
		
		// Test non-mappable field
		$this->assertArrayHasKey('field_repeater', $fields);
		$this->assertFalse($fields['field_repeater']['is_mappable']);
		
		// Test update field
		$result = $this->acf_handler->update_field('field_text', 'test_value', 123);
		$this->assertTrue($result);
		
		// Test get field object
		$field_object = $this->acf_handler->get_field_object('field_text', 123);
		$this->assertIsArray($field_object);
		$this->assertArrayHasKey('key', $field_object);
		$this->assertEquals('field_text', $field_object['key']);
	}

	/**
	 * Test import with ACF fields using mocked functions
	 */
	public function test_import_with_acf_fields_mocked() {
		// Skip if ACF is actually installed
		if (class_exists('ACF')) {
			$this->markTestSkipped('ACF is installed - skipping mock test');
		}
		
		// Mock ACF class and functions
		if (!class_exists('ACF')) {
			eval('class ACF {}');
		}
		
		if (!function_exists('update_field')) {
			eval('
				function update_field($field_key, $value, $post_id) {
					return update_post_meta($post_id, "_" . $field_key, $value);
				}
			');
		}
		
		if (!function_exists('get_field_object')) {
			eval('
				function get_field_object($field_key, $post_id, $format_value = true) {
					return array(
						"key" => $field_key,
						"label" => "Test Field",
						"type" => "text"
					);
				}
			');
		}
		
		// Create test data
		$data = array(
			array(
				'title' => 'Test Post with ACF',
				'content' => 'Test content',
				'acf_text_field' => 'ACF text value',
				'acf_number_field' => '123'
			)
		);
		
		$transient_key = $this->create_transient_data($data);
		
		// Set up mapping with ACF fields
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'acf' => array(
				'field_text' => 'acf_text_field',
				'field_number' => 'acf_number_field'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(1, $result['imported_count']);
		
		// Verify post was created
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => 1));
		$this->assertCount(1, $posts);
		
		$post = $posts[0];
		$this->assertEquals('Test Post with ACF', $post->post_title);
		
		// Verify ACF fields were set (using meta since we mocked update_field)
		$text_value = get_post_meta($post->ID, '_field_text', true);
		$number_value = get_post_meta($post->ID, '_field_number', true);
		
		$this->assertEquals('ACF text value', $text_value);
		$this->assertEquals('123', $number_value);
	}

	/**
	 * Test import with invalid ACF field mapping
	 */
	public function test_import_with_invalid_acf_field_mapping() {
		// Skip if ACF is actually installed
		if (class_exists('ACF')) {
			$this->markTestSkipped('ACF is installed - skipping mock test');
		}
		
		// Mock ACF class and functions
		if (!class_exists('ACF')) {
			eval('class ACF {}');
		}
		
		if (!function_exists('get_field_object')) {
			eval('
				function get_field_object($field_key, $post_id, $format_value = true) {
					// Return false for invalid fields
					if ($field_key === "invalid_field") {
						return false;
					}
					return array(
						"key" => $field_key,
						"label" => "Test Field",
						"type" => "text"
					);
				}
			');
		}
		
		// Create test data
		$data = array(
			array(
				'title' => 'Test Post',
				'content' => 'Test content',
				'valid_field' => 'valid value',
				'invalid_field' => 'invalid value'
			)
		);
		
		$transient_key = $this->create_transient_data($data);
		
		// Set up mapping with invalid ACF field
		$_POST['bulkpostimporter_transient_key'] = $transient_key;
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array(
			'standard' => array(
				'post_title' => 'title',
				'post_content' => 'content'
			),
			'acf' => array(
				'valid_field_key' => 'valid_field',
				'invalid_field' => 'invalid_field'
			)
		);
		
		$result = $this->import_processor->process_import();
		
		$this->assertFalse(is_wp_error($result));
		$this->assertEquals(1, $result['imported_count']);
		
		// Post should still be created, invalid ACF fields should be ignored
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => 1));
		$this->assertCount(1, $posts);
	}

	/**
	 * Test field mappability with edge cases
	 */
	public function test_field_mappability_edge_cases() {
		// Test with empty field
		$this->assertFalse($this->acf_handler->is_field_mappable(array()));
		
		// Test with null type
		$field = array('type' => null);
		$this->assertFalse($this->acf_handler->is_field_mappable($field));
		
		// Test with empty type
		$field = array('type' => '');
		$this->assertFalse($this->acf_handler->is_field_mappable($field));
		
		// Test with numeric type
		$field = array('type' => 123);
		$this->assertFalse($this->acf_handler->is_field_mappable($field));
		
		// Test with boolean type
		$field = array('type' => true);
		$this->assertFalse($this->acf_handler->is_field_mappable($field));
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
		
		// Clean up $_POST
		$_POST = array();
		
		parent::tearDown();
	}
}
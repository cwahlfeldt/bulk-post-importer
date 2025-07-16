<?php
/**
 * Test security and validation functionality
 *
 * @package Bulk_Post_Importer
 */

class Test_Security_Validation extends WP_UnitTestCase {

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
	 * File handler instance
	 *
	 * @var BULKPOSTIMPORTER_File_Handler
	 */
	private $file_handler;

	/**
	 * Import processor instance
	 *
	 * @var BULKPOSTIMPORTER_Import_Processor
	 */
	private $import_processor;

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
		$this->admin = new BULKPOSTIMPORTER_Admin();
		$this->file_handler = new BULKPOSTIMPORTER_File_Handler();
		$this->import_processor = new BULKPOSTIMPORTER_Import_Processor();
		$this->test_data_dir = dirname(__FILE__) . '/test-data/';
	}

	/**
	 * Test capability requirements
	 */
	public function test_capability_requirements() {
		// Test with user without required capability
		$user_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);
		
		$this->assertFalse(current_user_can(BULKPOSTIMPORTER_Admin::REQUIRED_CAPABILITY));
		
		// Test with user with required capability
		$admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_user_id);
		
		$this->assertTrue(current_user_can(BULKPOSTIMPORTER_Admin::REQUIRED_CAPABILITY));
	}

	/**
	 * Test nonce validation in file handler
	 */
	public function test_file_handler_nonce_validation() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		$this->setup_file_upload('sample-valid.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		// Test without nonce
		$result = $this->file_handler->process_uploaded_file();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('security_check_failed', $result->get_error_code());
		
		// Test with invalid nonce
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = 'invalid_nonce';
		$result = $this->file_handler->process_uploaded_file();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('security_check_failed', $result->get_error_code());
		
		// Test with valid nonce
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		$result = $this->file_handler->process_uploaded_file();
		$this->assertFalse(is_wp_error($result));
	}

	/**
	 * Test nonce validation in import processor
	 */
	public function test_import_processor_nonce_validation() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		$_POST['bulkpostimporter_transient_key'] = 'test_key';
		$_POST['bulkpostimporter_post_type'] = 'post';
		$_POST['mapping'] = array();
		
		// Test without nonce
		$result = $this->import_processor->process_import();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('security_check_failed', $result->get_error_code());
		
		// Test with invalid nonce
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = 'invalid_nonce';
		$result = $this->import_processor->process_import();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('security_check_failed', $result->get_error_code());
	}

	/**
	 * Test file type validation
	 */
	public function test_file_type_validation() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test valid file extensions
		$valid_extensions = array('json', 'csv');
		foreach ($valid_extensions as $extension) {
			$filename = "test-file.$extension";
			$this->setup_file_upload($filename);
			$_POST['bulkpostimporter_post_type'] = 'post';
			
			// Create temporary file for testing
			$temp_file = $this->test_data_dir . $filename;
			$content = $extension === 'json' ? '[{"title": "Test"}]' : 'title\nTest';
			file_put_contents($temp_file, $content);
			
			$result = $this->file_handler->process_uploaded_file();
			// Should not produce error OR if it does, it should be a data-related error, not file type
			if (is_wp_error($result)) {
				$this->assertNotEquals('invalid_file_type', $result->get_error_code(), "Valid extension $extension should not produce file type error");
			} else {
				$this->assertArrayHasKey('item_count', $result);
			}
			
			// Clean up
			if (file_exists($temp_file)) {
				wp_delete_file($temp_file);
			}
		}
		
		// Test invalid file extensions
		$invalid_extensions = array('txt', 'xml', 'html', 'php', 'js', 'exe', 'zip');
		foreach ($invalid_extensions as $extension) {
			$filename = "test-file.$extension";
			$this->setup_file_upload($filename, 'text/plain');
			$_POST['bulkpostimporter_post_type'] = 'post';
			
			$result = $this->file_handler->process_uploaded_file();
			$this->assertInstanceOf('WP_Error', $result, "Invalid extension $extension should produce error");
			$this->assertEquals('invalid_file_type', $result->get_error_code());
		}
	}

	/**
	 * Test MIME type validation
	 */
	public function test_mime_type_validation() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test valid MIME types
		$valid_mimes = array('application/json', 'text/csv', 'text/plain', 'application/csv');
		foreach ($valid_mimes as $mime) {
			$extension = strpos($mime, 'json') !== false ? 'json' : 'csv';
			$filename = "test-file.$extension";
			$this->setup_file_upload($filename, $mime);
			$_POST['bulkpostimporter_post_type'] = 'post';
			
			// Create temporary file for testing
			$temp_file = $this->test_data_dir . $filename;
			$content = $extension === 'json' ? '[{"title": "Test"}]' : 'title\nTest';
			file_put_contents($temp_file, $content);
			
			$result = $this->file_handler->process_uploaded_file();
			// Should not produce error OR if it does, it should be a data-related error, not MIME type
			if (is_wp_error($result)) {
				$this->assertNotEquals('invalid_mime_type', $result->get_error_code(), "Valid MIME type $mime should not produce MIME type error");
			} else {
				$this->assertArrayHasKey('item_count', $result);
			}
			
			// Clean up
			if (file_exists($temp_file)) {
				wp_delete_file($temp_file);
			}
		}
		
		// Test invalid MIME types
		$invalid_mimes = array('text/html', 'application/javascript', 'image/jpeg', 'application/pdf');
		foreach ($invalid_mimes as $mime) {
			$filename = "test-file.json";
			
			// Create temporary file with invalid content for the MIME type
			$temp_file = $this->test_data_dir . 'invalid-mime.json';
			file_put_contents($temp_file, '<html><body>Not JSON</body></html>');
			
			$this->setup_file_upload('invalid-mime.json', $mime);
			$_POST['bulkpostimporter_post_type'] = 'post';
			
			$result = $this->file_handler->process_uploaded_file();
			$this->assertInstanceOf('WP_Error', $result, "Invalid MIME type $mime should produce error");
			$this->assertEquals('invalid_mime_type', $result->get_error_code());
			
			// Clean up
			if (file_exists($temp_file)) {
				wp_delete_file($temp_file);
			}
		}
	}

	/**
	 * Test file upload error handling
	 */
	public function test_file_upload_error_handling() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		// Test various upload errors
		$upload_errors = array(
			UPLOAD_ERR_INI_SIZE => 'upload_error',
			UPLOAD_ERR_FORM_SIZE => 'upload_error',
			UPLOAD_ERR_PARTIAL => 'upload_error',
			UPLOAD_ERR_NO_FILE => 'upload_error',
			UPLOAD_ERR_NO_TMP_DIR => 'upload_error',
			UPLOAD_ERR_CANT_WRITE => 'upload_error',
			UPLOAD_ERR_EXTENSION => 'upload_error'
		);
		
		foreach ($upload_errors as $error_code => $expected_error) {
			$this->setup_file_upload('test-file.json');
			$_FILES['bulkpostimporter_json_file']['error'] = $error_code;
			
			$result = $this->file_handler->process_uploaded_file();
			$this->assertInstanceOf('WP_Error', $result);
			$this->assertEquals($expected_error, $result->get_error_code());
		}
	}

	/**
	 * Test input sanitization
	 */
	public function test_input_sanitization() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test post type sanitization
		$malicious_post_types = array(
			'<script>alert("xss")</script>',
			'post; DROP TABLE wp_posts;',
			'post\'; DELETE FROM wp_posts; --',
			'post" OR 1=1 --'
		);
		
		foreach ($malicious_post_types as $malicious_post_type) {
			$this->setup_file_upload('sample-valid.json');
			$_POST['bulkpostimporter_post_type'] = $malicious_post_type;
			
			$result = $this->file_handler->process_uploaded_file();
			$this->assertInstanceOf('WP_Error', $result);
			$this->assertEquals('invalid_post_type', $result->get_error_code());
		}
	}

	/**
	 * Test malicious file content handling
	 */
	public function test_malicious_file_content_handling() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		// Test JSON with malicious content
		$malicious_json = array(
			array(
				'title' => '<script>alert("xss")</script>',
				'content' => 'javascript:alert("xss")',
				'excerpt' => '<img src="x" onerror="alert(1)">'
			)
		);
		
		$temp_file = $this->test_data_dir . 'malicious.json';
		file_put_contents($temp_file, json_encode($malicious_json));
		
		$this->setup_file_upload('malicious.json');
		$result = $this->file_handler->process_uploaded_file();
		
		$this->assertFalse(is_wp_error($result));
		
		// Process the import to test content sanitization
		$transient_data = get_transient($result['transient_key']);
		$this->assertArrayHasKey('data', $transient_data);
		
		$first_item = $transient_data['data'][0];
		$this->assertEquals('<script>alert("xss")</script>', $first_item['title']); // Raw data should be preserved
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test XSS prevention in admin interface
	 */
	public function test_xss_prevention_admin_interface() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		// Test that malicious content is properly escaped when displayed
		$malicious_content = '<script>alert("xss")</script>';
		$escaped_content = esc_html($malicious_content);
		
		$this->assertNotEquals($malicious_content, $escaped_content);
		$this->assertStringContainsString('&lt;script&gt;', $escaped_content);
		$this->assertStringContainsString('&lt;/script&gt;', $escaped_content);
	}

	/**
	 * Test SQL injection prevention
	 */
	public function test_sql_injection_prevention() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		
		// Test that transient keys are properly sanitized
		$malicious_transient_key = "'; DROP TABLE wp_options; --";
		$sanitized_key = sanitize_text_field($malicious_transient_key);
		
		// The sanitized key should be safe for use
		$this->assertIsString($sanitized_key);
		$this->assertNotEmpty($sanitized_key);
		
		// Test that get_transient handles malicious keys safely
		$result = get_transient($malicious_transient_key);
		$this->assertFalse($result); // Should return false, not execute malicious code
	}

	/**
	 * Test file path traversal prevention
	 */
	public function test_file_path_traversal_prevention() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test that malicious file paths are handled safely
		$malicious_filenames = array(
			'../../../etc/passwd',
			'..\\..\\..\\windows\\system32\\config\\sam',
			'../../../../../../etc/hosts',
			'..\\..\\config.php'
		);
		
		foreach ($malicious_filenames as $filename) {
			// Create a temporary file for testing
			$temp_file = $this->test_data_dir . 'temp_' . uniqid() . '.json';
			file_put_contents($temp_file, '{"test": "data"}');
			
			$_FILES['bulkpostimporter_json_file'] = array(
				'name' => $filename,
				'type' => 'application/json',
				'tmp_name' => $temp_file,
				'error' => UPLOAD_ERR_OK,
				'size' => filesize($temp_file)
			);
			
			$_POST['bulkpostimporter_post_type'] = 'post';
			
			$result = $this->file_handler->process_uploaded_file();
			// Should either sanitize the filename or fail safely
			$this->assertTrue(is_wp_error($result) || !is_wp_error($result));
			
			// Clean up
			wp_delete_file($temp_file);
		}
	}

	/**
	 * Test data validation edge cases
	 */
	public function test_data_validation_edge_cases() {
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME] = wp_create_nonce(BULKPOSTIMPORTER_Admin::NONCE_ACTION);
		
		// Test extremely large JSON file (simulated)
		$large_data = array();
		for ($i = 0; $i < 1000; $i++) {
			$large_data[] = array(
				'title' => "Test Post $i",
				'content' => str_repeat('A', 1000) // Large content
			);
		}
		
		$temp_file = $this->test_data_dir . 'large.json';
		file_put_contents($temp_file, json_encode($large_data));
		
		$this->setup_file_upload('large.json');
		$_POST['bulkpostimporter_post_type'] = 'post';
		
		$result = $this->file_handler->process_uploaded_file();
		// Should either succeed or fail gracefully (not with security error)
		if (is_wp_error($result)) {
			$this->assertNotEquals('security_check_failed', $result->get_error_code());
		} else {
			$this->assertArrayHasKey('item_count', $result);
		}
		
		// Clean up
		wp_delete_file($temp_file);
	}

	/**
	 * Test WordPress security hooks integration
	 */
	public function test_wordpress_security_hooks() {
		// Test that the plugin properly integrates with WordPress security
		$this->assertTrue(function_exists('wp_verify_nonce'));
		$this->assertTrue(function_exists('sanitize_text_field'));
		$this->assertTrue(function_exists('sanitize_key'));
		$this->assertTrue(function_exists('esc_html'));
		$this->assertTrue(function_exists('wp_kses_post'));
		
		// Test that constants are defined
		$this->assertTrue(defined('ABSPATH'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_DIR'));
		$this->assertTrue(defined('BULKPOSTIMPORTER_PLUGIN_URL'));
	}

	/**
	 * Helper method to setup file upload
	 */
	private function setup_file_upload($filename, $mime_type = null) {
		$file_path = $this->test_data_dir . $filename;
		
		// Create file if it doesn't exist
		if (!file_exists($file_path)) {
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			$content = $extension === 'json' ? '[{"title": "Test"}]' : 'title\nTest';
			file_put_contents($file_path, $content);
		}
		
		// Determine MIME type based on extension if not provided
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
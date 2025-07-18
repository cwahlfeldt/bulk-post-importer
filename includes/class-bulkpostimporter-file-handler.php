<?php

/**
 * File handling functionality
 *
 * @package Bulk_Post_Importer
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Handles file upload and processing.
 */
class BULKPOSTIMPORTER_File_Handler
{

	/**
	 * Process uploaded file (JSON or CSV).
	 *
	 * @return array|WP_Error Processed file data or error.
	 */
	public function process_uploaded_file()
	{
		// Verify nonce for additional security.
		if (!isset($_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME]) || !wp_verify_nonce(sanitize_key($_POST[BULKPOSTIMPORTER_Admin::NONCE_NAME]), BULKPOSTIMPORTER_Admin::NONCE_ACTION)) {
			return new WP_Error('security_check_failed', __('Security check failed.', 'bulk-post-importer'));
		}

		// Validate file upload.
		$validation_result = $this->validate_file_upload();
		if (is_wp_error($validation_result)) {
			return $validation_result;
		}

		// Process the file.
		$file_path = isset($_FILES['bulkpostimporter_json_file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['bulkpostimporter_json_file']['tmp_name'])) : '';
		$file_name = isset($_FILES['bulkpostimporter_json_file']['name']) ? sanitize_file_name($_FILES['bulkpostimporter_json_file']['name']) : '';
		$post_type = isset($_POST['bulkpostimporter_post_type']) ? sanitize_key($_POST['bulkpostimporter_post_type']) : 'post';

		// Validate post type.
		if (! post_type_exists($post_type)) {
			return new WP_Error('invalid_post_type', __('Invalid post type selected.', 'bulk-post-importer'));
		}

		// Determine file type and parse accordingly.
		$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		
		if ('json' === $file_extension) {
			$data = $this->read_and_decode_json($file_path);
		} elseif ('csv' === $file_extension) {
			$data = $this->read_and_parse_csv($file_path);
		} else {
			return new WP_Error('unsupported_file_type', __('Unsupported file type. Please upload a JSON or CSV file.', 'bulk-post-importer'));
		}

		if (is_wp_error($data)) {
			return $data;
		}

		// Get keys from first item.
		$first_item = reset($data);
		$field_keys = array_keys($first_item);

		// Store data in transient.
		$transient_key = BULKPOSTIMPORTER_Plugin::get_instance()->utils->generate_transient_key();
		$transient_data = array(
			'data'      => $data,
			'post_type' => $post_type,
			'file_name' => $file_name,
		);

		set_transient($transient_key, $transient_data, HOUR_IN_SECONDS);

		return array(
			'json_keys'     => $field_keys,
			'post_type'     => $post_type,
			'transient_key' => $transient_key,
			'item_count'    => count($data),
			'file_name'     => $file_name,
		);
	}

	/**
	 * Validate file upload.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_file_upload()
	{
		if (!isset($_FILES['bulkpostimporter_json_file']) || !isset($_FILES['bulkpostimporter_json_file']['tmp_name']) || empty($_FILES['bulkpostimporter_json_file']['tmp_name'])) {
			return new WP_Error('no_file', __('No file was uploaded.', 'bulk-post-importer'));
		}

		if (!isset($_FILES['bulkpostimporter_json_file']['error']) || UPLOAD_ERR_OK !== $_FILES['bulkpostimporter_json_file']['error']) {
			$error_code = isset($_FILES['bulkpostimporter_json_file']['error']) ? intval($_FILES['bulkpostimporter_json_file']['error']) : UPLOAD_ERR_NO_FILE;
			$error_message = BULKPOSTIMPORTER_Plugin::get_instance()->utils->get_upload_error_message($error_code);
			// translators: %s is the error message from the file upload
			return new WP_Error('upload_error', sprintf(__('File upload error: %s', 'bulk-post-importer'), $error_message));
		}

		$file_tmp_path = isset($_FILES['bulkpostimporter_json_file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['bulkpostimporter_json_file']['tmp_name'])) : '';
		$file_name = isset($_FILES['bulkpostimporter_json_file']['name']) ? sanitize_file_name($_FILES['bulkpostimporter_json_file']['name']) : '';

		// Check file type.
		$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		$file_type = mime_content_type($file_tmp_path);
		
		$valid_extensions = array('json', 'csv');
		$valid_mime_types = array('application/json', 'text/csv', 'text/plain', 'application/csv');
		
		if (! in_array($file_extension, $valid_extensions, true)) {
			return new WP_Error(
				'invalid_file_type',
				// translators: %s is the detected file type
				sprintf(__('Invalid file type. Please upload a .json or .csv file (detected type: %s).', 'bulk-post-importer'), esc_html($file_type))
			);
		}
		
		// Additional MIME type validation for security
		if (! in_array($file_type, $valid_mime_types, true)) {
			return new WP_Error(
				'invalid_mime_type',
				// translators: %s is the detected MIME type
				sprintf(__('Invalid MIME type. Please upload a valid JSON or CSV file (detected type: %s).', 'bulk-post-importer'), esc_html($file_type))
			);
		}

		return true;
	}

	/**
	 * Read and decode JSON file.
	 *
	 * @param string $file_path The file path.
	 * @return array|WP_Error Decoded data or error.
	 */
	private function read_and_decode_json($file_path)
	{
		global $wp_filesystem;
		
		// Initialize WP_Filesystem
		if (!function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		if (!WP_Filesystem()) {
			return new WP_Error('filesystem_error', __('Could not initialize WordPress filesystem.', 'bulk-post-importer'));
		}

		$json_content = $wp_filesystem->get_contents($file_path);
		if (false === $json_content) {
			return new WP_Error('file_read_error', __('Could not read the uploaded file.', 'bulk-post-importer'));
		}

		// Remove BOM if present.
		$json_content = preg_replace('/^\xEF\xBB\xBF/', '', $json_content);

		$data = json_decode($json_content, true);

		// Check for JSON decoding errors.
		if (JSON_ERROR_NONE !== json_last_error()) {
			return new WP_Error(
				'json_decode_error',
				// translators: %s is the JSON error message
				sprintf(__('JSON Decode Error: %s. Please ensure the file is valid UTF-8 encoded JSON.', 'bulk-post-importer'), json_last_error_msg())
			);
		}

		// Validate JSON structure.
		$validation_result = $this->validate_json_structure($data);
		if (is_wp_error($validation_result)) {
			return $validation_result;
		}

		return $data;
	}

	/**
	 * Validate JSON structure.
	 *
	 * @param mixed $data The decoded JSON data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_json_structure($data)
	{
		if (! is_array($data)) {
			return new WP_Error('invalid_structure', __('JSON file structure error: Root element must be an array [...].', 'bulk-post-importer'));
		}

		if (empty($data)) {
			return new WP_Error('empty_array', __('The JSON file appears to contain an empty array.', 'bulk-post-importer'));
		}

		if (! is_array(reset($data))) {
			return new WP_Error('invalid_items', __('JSON file structure error: The array should contain objects {...}.', 'bulk-post-importer'));
		}

		return true;
	}

	/**
	 * Read and parse CSV file.
	 *
	 * @param string $file_path The file path.
	 * @return array|WP_Error Parsed data or error.
	 */
	private function read_and_parse_csv($file_path)
	{
		global $wp_filesystem;
		
		// Initialize WP_Filesystem
		if (!function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		if (!WP_Filesystem()) {
			return new WP_Error('filesystem_error', __('Could not initialize WordPress filesystem.', 'bulk-post-importer'));
		}

		if (!$wp_filesystem->exists($file_path) || !$wp_filesystem->is_readable($file_path)) {
			return new WP_Error('file_read_error', __('Could not read the uploaded CSV file.', 'bulk-post-importer'));
		}

		$csv_content = $wp_filesystem->get_contents($file_path);
		if (false === $csv_content) {
			return new WP_Error('file_open_error', __('Could not open the uploaded CSV file.', 'bulk-post-importer'));
		}

		$csv_data = array();
		$headers = array();
		$headers_found = false;
		
		// Split content into lines and parse each line
		$lines = explode("\n", $csv_content);
		
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}
			
			// Parse CSV row
			$row = str_getcsv($line, ',');
			
			// Skip empty rows
			if (empty(array_filter($row))) {
				continue;
			}

			if (!$headers_found) {
				// First non-empty row contains headers
				$headers = array_map('trim', $row);
				
				// Validate headers
				if (empty($headers) || count(array_filter($headers)) === 0) {
					return new WP_Error('invalid_csv_headers', __('CSV file must have header row with column names.', 'bulk-post-importer'));
				}
				
				$headers_found = true;
				continue;
			}

			// Data rows - convert to associative array
			$row_data = array();
			foreach ($row as $index => $value) {
				$header = isset($headers[$index]) ? $headers[$index] : "column_$index";
				$row_data[$header] = trim($value);
			}
			
			$csv_data[] = $row_data;
		}

		// Validate CSV structure
		if (empty($csv_data)) {
			return new WP_Error('empty_csv', __('The CSV file appears to contain no data rows.', 'bulk-post-importer'));
		}

		return $csv_data;
	}
}

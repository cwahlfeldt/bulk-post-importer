<?php

/**
 * ACF field handling functionality
 *
 * @package Bulk_Post_Importer
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Handles Advanced Custom Fields integration.
 */
class BULKPOSTIMPORTER_ACF_Handler
{

	/**
	 * Check if ACF is active.
	 *
	 * @return bool True if ACF is active.
	 */
	public function is_active()
	{
		return class_exists('ACF');
	}

	/**
	 * Get ACF fields for a post type.
	 *
	 * @param string $post_type The post type slug.
	 * @return array Array of ACF field objects with mappable status.
	 */
	public function get_fields_for_post_type($post_type)
	{
		if (! $this->is_active()) {
			return array();
		}

		if (! function_exists('acf_get_field_groups') || ! function_exists('acf_get_fields')) {
			return array();
		}

		$acf_fields = array();
		$field_groups = acf_get_field_groups(array('post_type' => $post_type));

		if (empty($field_groups)) {
			return array();
		}

		foreach ($field_groups as $group) {
			if (! isset($group['key'])) {
				continue;
			}

			$fields_in_group = acf_get_fields($group['key']);

			if (empty($fields_in_group)) {
				continue;
			}

			foreach ($fields_in_group as $field) {
				if (! isset($field['key'])) {
					continue;
				}

				// Add mappable status to field
				$field['is_mappable'] = $this->is_field_mappable($field);
				$acf_fields[$field['key']] = $field;
			}
		}

		return $acf_fields;
	}

	/**
	 * Check if an ACF field type can be mapped from simple text/number data.
	 *
	 * @param array $field The ACF field array.
	 * @return bool True if the field can be mapped.
	 */
	public function is_field_mappable($field)
	{
		if (! isset($field['type'])) {
			return false;
		}

		$allowed_field_types = array(
			'text',
			'textarea',
			'number',
			'email',
			'url',
			'password',
			'phone_number', // ACF Phone Number field addon
		);

		return in_array($field['type'], $allowed_field_types, true);
	}

	/**
	 * Get allowed field types for import mapping.
	 *
	 * @return array Array of allowed field types.
	 */
	public function get_allowed_field_types()
	{
		return array(
			'text'         => __('Text', 'bulk-post-importer'),
			'textarea'     => __('Textarea', 'bulk-post-importer'),
			'number'       => __('Number', 'bulk-post-importer'),
			'email'        => __('Email', 'bulk-post-importer'),
			'url'          => __('URL', 'bulk-post-importer'),
			'password'     => __('Password', 'bulk-post-importer'),
			'phone_number' => __('Phone Number', 'bulk-post-importer'),
		);
	}

	/**
	 * Update ACF field value for a post.
	 *
	 * @param string $field_key The ACF field key.
	 * @param mixed  $value     The field value.
	 * @param int    $post_id   The post ID.
	 * @return bool True on success, false on failure.
	 */
	public function update_field($field_key, $value, $post_id)
	{
		if (! $this->is_active() || ! function_exists('update_field')) {
			return false;
		}

		return update_field($field_key, $value, $post_id);
	}

	/**
	 * Get field object by key.
	 *
	 * @param string $field_key The field key.
	 * @param int    $post_id   The post ID.
	 * @return array|false Field object or false on failure.
	 */
	public function get_field_object($field_key, $post_id)
	{
		if (! $this->is_active() || ! function_exists('get_field_object')) {
			return false;
		}

		return get_field_object($field_key, $post_id, false);
	}
}

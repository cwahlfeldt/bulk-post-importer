<?php

/**
 * Mapping form template
 *
 * @package Bulk_Post_Importer
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

$standard_fields = BULKPOSTIMPORTER_Plugin::get_instance()->utils->get_standard_fields();
$json_key_options = '<option value="">' . esc_html__('-- Do Not Map --', 'bulk-post-importer') . '</option>';

foreach ($json_keys as $key) {
	$json_key_options .= sprintf('<option value="%s">%s</option>', esc_attr($key), esc_html($key));
}
?>

<div class="wrap">
	<h1><?php esc_html_e('Bulk Post Importer - Step 2: Field Mapping', 'bulk-post-importer'); ?></h1>
	<?php // translators: %s is the uploaded file name ?>
	<p><?php printf(esc_html__('File: %s', 'bulk-post-importer'), '<strong>' . esc_html($file_name) . '</strong>'); ?></p>
	<p>
		<?php
		printf(
			// translators: %1$d is the number of items to import, %2$s is the post type name
			esc_html__('Found %1$d items to import as "%2$s" posts. Please map the data fields (left) to the corresponding WordPress fields (right).', 'bulk-post-importer'),
			absint($item_count),
			esc_html($post_type_label)
		);
		?>
	</p>
	<p><?php esc_html_e('Unmapped fields will be ignored or set to default values (e.g., status defaults to "publish", date to current time).', 'bulk-post-importer'); ?></p>

	<form method="post" action="<?php echo esc_url(admin_url('tools.php?page=' . BULKPOSTIMPORTER_PLUGIN_SLUG)); ?>">
		<?php wp_nonce_field(BULKPOSTIMPORTER_Admin::NONCE_ACTION, BULKPOSTIMPORTER_Admin::NONCE_NAME); ?>
		<input type="hidden" name="bulkpostimporter_transient_key" value="<?php echo esc_attr($transient_key); ?>" />
		<input type="hidden" name="bulkpostimporter_post_type" value="<?php echo esc_attr($post_type); ?>" />
		<input type="hidden" name="item_count" value="<?php echo esc_attr($item_count); ?>">
		<input type="hidden" name="step" value="2">

		<h2><?php esc_html_e('Standard Fields', 'bulk-post-importer'); ?></h2>
		<table class="form-table bulkpostimporter-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Data Field', 'bulk-post-importer'); ?></th>
					<th><?php esc_html_e('WordPress Field', 'bulk-post-importer'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($standard_fields as $wp_key => $wp_label) : ?>
					<tr valign="top">
						<th scope="row">
							<select name="mapping[standard][<?php echo esc_attr($wp_key); ?>]">
								<?php
								$selected_attr = '';
								$allowed_html = array(
									'option' => array(
										'value' => array(),
										'selected' => array(),
									),
								);
								foreach ($json_keys as $json_key) {
									if (0 === strcasecmp($wp_key, $json_key)) {
										$selected_attr = 'selected="selected"';
										echo wp_kses(str_replace('value="' . esc_attr($json_key) . '"', 'value="' . esc_attr($json_key) . '" ' . $selected_attr, $json_key_options), $allowed_html);
										break;
									}
								}
								if (empty($selected_attr)) {
									echo wp_kses($json_key_options, $allowed_html);
								}
								?>
							</select>
						</th>
						<td>
							<label><?php echo wp_kses_post($wp_label); ?></label>
							<?php if ('post_title' === $wp_key) : ?>
								<p class="description"><?php esc_html_e('Mapping a title is highly recommended.', 'bulk-post-importer'); ?></p>
							<?php endif; ?>
							<?php if ('post_content' === $wp_key) : ?>
								<p class="description"><?php esc_html_e('Newline characters in source will create separate Paragraph blocks.', 'bulk-post-importer'); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<hr>

		<?php if (BULKPOSTIMPORTER_Plugin::get_instance()->acf_handler->is_active()) : ?>
			<h2><?php esc_html_e('Advanced Custom Fields (ACF)', 'bulk-post-importer'); ?></h2>
			<?php if (! empty($acf_fields)) : ?>
				<p><?php esc_html_e('Map data fields to available ACF fields for this post type.', 'bulk-post-importer'); ?></p>
				<p class="description"><?php esc_html_e('Only simple field types (text, textarea, number, email, url, password, phone) can be mapped. Complex fields are shown for reference but cannot be imported.', 'bulk-post-importer'); ?></p>
				<table class="form-table bulkpostimporter-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Data Field', 'bulk-post-importer'); ?></th>
							<th><?php esc_html_e('ACF Field', 'bulk-post-importer'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($acf_fields as $field_key => $field) : ?>
							<?php if (! isset($field['key'], $field['name'], $field['label'], $field['type'])) continue; ?>
							<tr valign="top" <?php if (!$field['is_mappable']) echo 'style="opacity: 0.6;"'; ?>>
								<th scope="row">
									<?php if ($field['is_mappable']) : ?>
										<select name="mapping[acf][<?php echo esc_attr($field_key); ?>]">
											<?php
											$allowed_html = array(
												'option' => array(
													'value' => array(),
													'selected' => array(),
												),
											);
											$selected_attr = '';
											foreach ($json_keys as $json_key) {
												if (0 === strcasecmp($field['name'], $json_key)) {
													$selected_attr = 'selected="selected"';
													echo wp_kses(str_replace('value="' . esc_attr($json_key) . '"', 'value="' . esc_attr($json_key) . '" ' . $selected_attr, $json_key_options), $allowed_html);
													break;
												}
											}
											if (empty($selected_attr)) {
												echo wp_kses($json_key_options, $allowed_html);
											}
											?>
										</select>
									<?php else : ?>
										<select disabled>
											<option><?php esc_html_e('-- Cannot Map --', 'bulk-post-importer'); ?></option>
										</select>
									<?php endif; ?>
								</th>
								<td>
									<label title="Key: <?php echo esc_attr($field_key); ?> | Name: <?php echo esc_attr($field['name']); ?> | Type: <?php echo esc_attr($field['type']); ?>">
										<?php echo esc_html($field['label']); ?>
										(<code><?php echo esc_html($field['name']); ?></code> - <i><?php echo esc_html($field['type']); ?></i>)
										<?php if (!$field['is_mappable']) : ?>
											<br><small style="color: #d63638;"><?php esc_html_e('Complex field type - cannot be mapped from simple data', 'bulk-post-importer'); ?></small>
										<?php endif; ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e('No ACF field groups were found assigned to this post type.', 'bulk-post-importer'); ?></p>
			<?php endif; ?>
			<hr>
		<?php endif; ?>

		<h2><?php esc_html_e('Other Custom Fields (Non-ACF Post Meta)', 'bulk-post-importer'); ?></h2>
		<p><?php esc_html_e('Map data fields to standard WordPress custom field names (meta keys). Use this for meta fields NOT managed by ACF.', 'bulk-post-importer'); ?></p>
		<table class="form-table bulkpostimporter-mapping-table" id="bji-custom-fields-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Data Field', 'bulk-post-importer'); ?></th>
					<th><?php esc_html_e('Custom Field Name (Meta Key)', 'bulk-post-importer'); ?></th>
					<th><?php esc_html_e('Action', 'bulk-post-importer'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr valign="top" class="bulkpostimporter-custom-field-row">
					<td>
						<select name="mapping[custom][0][json_key]">
							<?php 
							$allowed_html = array(
								'option' => array(
									'value' => array(),
									'selected' => array(),
								),
							);
							echo wp_kses($json_key_options, $allowed_html);
							?>
						</select>
					</td>
					<td>
						<input type="text" name="mapping[custom][0][meta_key]" placeholder="<?php esc_attr_e('Enter meta key', 'bulk-post-importer'); ?>" />
					</td>
					<td>
						<button type="button" class="button bji-remove-row"><?php esc_html_e('Remove', 'bulk-post-importer'); ?></button>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3">
						<button type="button" id="bji-add-custom-field" class="button"><?php esc_html_e('Add Another Custom Field Mapping', 'bulk-post-importer'); ?></button>
					</td>
				</tr>
			</tfoot>
		</table>

		<?php submit_button(__('Process Import', 'bulk-post-importer'), 'primary', 'bulkpostimporter_process_import'); ?>
	</form>
</div>
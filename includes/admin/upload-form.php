<?php

/**
 * Upload form template
 *
 * @package Bulk_Post_Importer
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e('Bulk Post Importer - Step 1: Upload', 'bulk-post-importer'); ?></h1>
	<p><?php esc_html_e('Upload a JSON or CSV file containing your post data. For JSON: an array of objects. For CSV: each row represents a post with headers as field names.', 'bulk-post-importer'); ?></p>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('tools.php?page=' . BULKPOSTIMPORTER_PLUGIN_SLUG)); ?>">
		<?php wp_nonce_field(BULKPOSTIMPORTER_Admin::NONCE_ACTION, BULKPOSTIMPORTER_Admin::NONCE_NAME); ?>
		<input type="hidden" name="step" value="1">

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="bpi_json_file"><?php esc_html_e('Data File', 'bulk-post-importer'); ?></label>
				</th>
				<td>
					<input type="file" id="bulkpostimporter_json_file" name="bulkpostimporter_json_file" accept=".json,.csv,application/json,text/csv" required />
					<p class="description">
						<?php esc_html_e('Supported formats:', 'bulk-post-importer'); ?><br>
						<strong><?php esc_html_e('JSON:', 'bulk-post-importer'); ?></strong> <?php esc_html_e('Array of objects [{"title": "Post 1"}, {"title": "Post 2"}]', 'bulk-post-importer'); ?><br>
						<strong><?php esc_html_e('CSV:', 'bulk-post-importer'); ?></strong> <?php esc_html_e('First row as headers, subsequent rows as data', 'bulk-post-importer'); ?>
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="bpi_post_type"><?php esc_html_e('Target Post Type', 'bulk-post-importer'); ?></label>
				</th>
				<td>
					<select id="bulkpostimporter_post_type" name="bulkpostimporter_post_type" required>
						<?php foreach ($post_types as $post_type) : ?>
							<option value="<?php echo esc_attr($post_type->name); ?>">
								<?php echo esc_html($post_type->labels->singular_name . ' (' . $post_type->name . ')'); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e('Select the post type you want to create.', 'bulk-post-importer'); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(__('Upload and Proceed to Mapping', 'bulk-post-importer'), 'primary', 'bulkpostimporter_upload_json'); ?>
	</form>
</div>
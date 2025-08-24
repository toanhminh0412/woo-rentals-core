<?php
if (!defined('ABSPATH')) {
	exit;
}

/** @var array<string,mixed> $lease */
/** @var array<string,string> $allowed_statuses */

$list_url = add_query_arg([
	'page' => 'wrc_rentals_leases',
], admin_url('admin.php'));

?>
<div class="wrap">
	<h1>
		<?php esc_html_e('Edit Lease', 'woo-rentals-core'); ?>
		<a href="<?php echo esc_url($list_url); ?>" class="page-title-action"><?php esc_html_e('Back to list', 'woo-rentals-core'); ?></a>
	</h1>

	<?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Lease updated.', 'woo-rentals-core'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['json_error']) && $_GET['json_error'] === '1'): ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e('Meta must be valid JSON.', 'woo-rentals-core'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html(isset($_GET['msg']) ? (string)$_GET['msg'] : __('Failed to save. Please check inputs.', 'woo-rentals-core')); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field('save_lease_' . (int)$lease['id']); ?>
		<input type="hidden" name="lease_id" value="<?php echo esc_attr((string)$lease['id']); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="product_id"><?php esc_html_e('Product ID', 'woo-rentals-core'); ?></label></th>
					<td><input name="product_id" id="product_id" type="number" class="regular-text" value="<?php echo esc_attr((string)$lease['product_id']); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="variation_id"><?php esc_html_e('Variation ID', 'woo-rentals-core'); ?></label></th>
					<td><input name="variation_id" id="variation_id" type="number" class="regular-text" value="<?php echo esc_attr($lease['variation_id'] !== null ? (string)$lease['variation_id'] : ''); ?>" placeholder="<?php esc_attr_e('Optional', 'woo-rentals-core'); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="customer_id"><?php esc_html_e('Customer ID', 'woo-rentals-core'); ?></label></th>
					<td><input name="customer_id" id="customer_id" type="number" class="regular-text" value="<?php echo esc_attr((string)$lease['customer_id']); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="request_id"><?php esc_html_e('Request ID', 'woo-rentals-core'); ?></label></th>
					<td><input name="request_id" id="request_id" type="number" class="regular-text" value="<?php echo esc_attr($lease['request_id'] !== null ? (string)$lease['request_id'] : ''); ?>" placeholder="<?php esc_attr_e('Optional', 'woo-rentals-core'); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="start_date"><?php esc_html_e('Start date (UTC)', 'woo-rentals-core'); ?></label></th>
					<td><input name="start_date" id="start_date" type="text" class="regular-text" value="<?php echo esc_attr((string)$lease['start_date']); ?>" placeholder="YYYY-MM-DDTHH:MM" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="end_date"><?php esc_html_e('End date (UTC)', 'woo-rentals-core'); ?></label></th>
					<td><input name="end_date" id="end_date" type="text" class="regular-text" value="<?php echo esc_attr((string)$lease['end_date']); ?>" placeholder="YYYY-MM-DDTHH:MM" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="qty"><?php esc_html_e('Quantity', 'woo-rentals-core'); ?></label></th>
					<td><input name="qty" id="qty" type="number" min="1" class="regular-text" value="<?php echo esc_attr((string)$lease['qty']); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e('Status', 'woo-rentals-core'); ?></label></th>
					<td>
						<select name="status" id="status">
							<?php foreach ($allowed_statuses as $value => $label): ?>
								<option value="<?php echo esc_attr($value); ?>" <?php selected((string)$lease['status'], $value); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="meta"><?php esc_html_e('Meta (JSON)', 'woo-rentals-core'); ?></label></th>
					<td>
						<textarea name="meta" id="meta" class="large-text code" rows="8" placeholder="{\n  &quot;key&quot;: &quot;value&quot;\n}"><?php echo esc_textarea(wp_json_encode(is_array($lease['meta']) ? $lease['meta'] : [], JSON_PRETTY_PRINT)); ?></textarea>
						<p class="description"><?php esc_html_e('Provide a valid JSON object. Keys must be strings.', 'woo-rentals-core'); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(__('Save Lease', 'woo-rentals-core')); ?>
	</form>
</div>



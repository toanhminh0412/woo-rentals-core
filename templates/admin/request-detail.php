<?php
if (!defined('ABSPATH')) {
	exit;
}

$id = isset($_GET['id']) ? absint((string)$_GET['id']) : 0;
$request = $id ? \WRC\Domain\LeaseRequest::findByIdArray($id) : null;
?>
<div class="wrap">
	<h1><?php esc_html_e('Lease Request', 'woo-rentals-core'); ?></h1>
	<?php if (!$request): ?>
		<p><?php esc_html_e('Lease request not found.', 'woo-rentals-core'); ?></p>
	<?php else: ?>
		<table class="widefat striped">
			<tbody>
				<tr><th><?php esc_html_e('ID', 'woo-rentals-core'); ?></th><td><?php echo (int)$request['id']; ?></td></tr>
				<tr><th><?php esc_html_e('Product', 'woo-rentals-core'); ?></th><td><?php echo (int)$request['product_id']; ?></td></tr>
				<tr><th><?php esc_html_e('Requester', 'woo-rentals-core'); ?></th><td><?php echo (int)$request['requester_id']; ?></td></tr>
				<tr><th><?php esc_html_e('Requesting Vendor', 'woo-rentals-core'); ?></th><td>
					<?php 
					$vendorId = isset($request['requesting_vendor_id']) ? (int)$request['requesting_vendor_id'] : 0; 
					$vendor = $vendorId ? get_userdata($vendorId) : false; 
					echo $vendor ? esc_html($vendor->display_name) . ' (ID ' . (int)$vendorId . ')' : (int)$vendorId; 
					?>
				</td></tr>
				<tr><th><?php esc_html_e('Period', 'woo-rentals-core'); ?></th><td><?php echo esc_html($request['start_date'] . ' → ' . $request['end_date']); ?></td></tr>
				<tr><th><?php esc_html_e('Quantity', 'woo-rentals-core'); ?></th><td><?php echo (int)$request['qty']; ?></td></tr>
				<tr><th><?php esc_html_e('Notes', 'woo-rentals-core'); ?></th><td><?php echo isset($request['notes']) ? esc_html((string)$request['notes']) : ''; ?></td></tr>
				<tr><th><?php esc_html_e('Total', 'woo-rentals-core'); ?></th><td>
					<?php 
					$amount = isset($request['total_price']) ? (float)$request['total_price'] : 0.0; 
					if (function_exists('wc_price')) { 
						echo wp_kses_post(wc_price($amount, array('currency' => 'VND'))); 
					} else { 
						echo esc_html(number_format_i18n($amount, 0) . ' ₫'); 
					} 
					?>
				</td></tr>
				<tr><th><?php esc_html_e('Status', 'woo-rentals-core'); ?></th><td><?php echo esc_html((string)$request['status']); ?></td></tr>
				<tr><th><?php esc_html_e('Created', 'woo-rentals-core'); ?></th><td><?php echo esc_html((string)$request['created_at']); ?></td></tr>
				<tr><th><?php esc_html_e('Updated', 'woo-rentals-core'); ?></th><td><?php echo isset($request['updated_at']) ? esc_html((string)$request['updated_at']) : ''; ?></td></tr>
			</tbody>
		</table>
		<p>
			<a href="#" class="button" data-action="approve" data-id="<?php echo (int)$request['id']; ?>"><?php esc_html_e('Approve', 'woo-rentals-core'); ?></a>
			<a href="#" class="button" data-action="decline" data-id="<?php echo (int)$request['id']; ?>"><?php esc_html_e('Decline', 'woo-rentals-core'); ?></a>
			<a href="#" class="button" data-action="cancel" data-id="<?php echo (int)$request['id']; ?>"><?php esc_html_e('Cancel', 'woo-rentals-core'); ?></a>
		</p>
	<?php endif; ?>
</div>





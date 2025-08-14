<?php
if (!defined('ABSPATH')) {
	exit;
}

$id = isset($_GET['id']) ? absint((string)$_GET['id']) : 0;
$lease = $id ? \WRC\Domain\Lease::findByIdArray($id) : null;
?>
<div class="wrap">
	<h1><?php esc_html_e('Lease', 'woo-rentals-core'); ?></h1>
	<?php if (!$lease): ?>
		<p><?php esc_html_e('Lease not found.', 'woo-rentals-core'); ?></p>
	<?php else: ?>
		<table class="widefat striped">
			<tbody>
				<tr><th><?php esc_html_e('ID', 'woo-rentals-core'); ?></th><td><?php echo (int)$lease['id']; ?></td></tr>
				<tr><th><?php esc_html_e('Product', 'woo-rentals-core'); ?></th><td><?php echo (int)$lease['product_id']; ?></td></tr>
				<tr><th><?php esc_html_e('Customer', 'woo-rentals-core'); ?></th><td><?php echo (int)$lease['customer_id']; ?></td></tr>
				<tr><th><?php esc_html_e('Period', 'woo-rentals-core'); ?></th><td><?php echo esc_html($lease['start_date'] . ' → ' . $lease['end_date']); ?></td></tr>
				<tr><th><?php esc_html_e('Quantity', 'woo-rentals-core'); ?></th><td><?php echo (int)$lease['qty']; ?></td></tr>
				<tr><th><?php esc_html_e('Status', 'woo-rentals-core'); ?></th><td><?php echo esc_html((string)$lease['status']); ?></td></tr>
				<tr><th><?php esc_html_e('Created', 'woo-rentals-core'); ?></th><td><?php echo esc_html((string)$lease['created_at']); ?></td></tr>
				<tr><th><?php esc_html_e('Updated', 'woo-rentals-core'); ?></th><td><?php echo isset($lease['updated_at']) ? esc_html((string)$lease['updated_at']) : ''; ?></td></tr>
			</tbody>
		</table>
		<p>
			<a href="#" class="button" data-action="complete" data-id="<?php echo (int)$lease['id']; ?>"><?php esc_html_e('Complete', 'woo-rentals-core'); ?></a>
			<a href="#" class="button" data-action="cancel" data-id="<?php echo (int)$lease['id']; ?>"><?php esc_html_e('Cancel', 'woo-rentals-core'); ?></a>
		</p>
	<?php endif; ?>
</div>





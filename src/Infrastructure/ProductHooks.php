<?php

declare(strict_types=1);

namespace WRC\Infrastructure;

use WRC\Domain\Lease;
use WRC\Domain\LeaseRequest;

/**
 * Handles WordPress/WooCommerce hooks related to product lifecycle
 */
final class ProductHooks
{
	public function boot(): void
	{
		// Hook into WooCommerce product deletion
		add_action('woocommerce_before_delete_product', [$this, 'handle_product_deletion']);
		add_action('before_delete_post', [$this, 'handle_post_deletion']);
	}

	/**
	 * Handle WooCommerce product deletion
	 * This hook fires specifically for WooCommerce products
	 */
	public function handle_product_deletion(int $productId): void
	{
		$this->cleanup_rental_data($productId, 'woocommerce_before_delete_product');
	}

	/**
	 * Handle general post deletion as fallback
	 * This covers cases where WooCommerce hooks might not fire
	 */
	public function handle_post_deletion(int $postId): void
	{
		// Only process if this is a product post type
		$postType = get_post_type($postId);
		if ($postType !== 'product') {
			return;
		}

		$this->cleanup_rental_data($postId, 'before_delete_post');
	}

	/**
	 * Clean up all rental data for a product
	 */
	private function cleanup_rental_data(int $productId, string $hookName): void
	{
		do_action('qm/start', 'wrc_product_deletion_cleanup');
		do_action('qm/info', 'Product {product_id} is being deleted via {hook_name}. Cleaning up rental data.', [
			'product_id' => $productId,
			'hook_name' => $hookName,
		]);

		try {
			// Delete lease requests first (they might reference leases)
			$deletedRequests = LeaseRequest::deleteByProductId($productId);
			
			// Delete leases
			$deletedLeases = Lease::deleteByProductId($productId);

			do_action('qm/info', 'Product {product_id} cleanup completed: {deleted_requests} lease requests and {deleted_leases} leases removed', [
				'product_id' => $productId,
				'deleted_requests' => $deletedRequests,
				'deleted_leases' => $deletedLeases,
			]);

			// Fire custom action for other plugins/extensions to hook into
			do_action('wrc_product_deleted', $productId, $deletedRequests, $deletedLeases);

		} catch (\Exception $e) {
			do_action('qm/error', 'Error cleaning up rental data for product {product_id}: {error_message}', [
				'product_id' => $productId,
				'error_message' => $e->getMessage(),
			]);
		} finally {
			do_action('qm/stop', 'wrc_product_deletion_cleanup');
		}
	}
}

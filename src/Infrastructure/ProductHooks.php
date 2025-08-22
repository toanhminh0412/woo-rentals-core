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


		try {
			// Delete lease requests first (they might reference leases)
			$deletedRequests = LeaseRequest::deleteByProductId($productId);
			
			// Delete leases
			$deletedLeases = Lease::deleteByProductId($productId);



			// Fire custom action for other plugins/extensions to hook into
			do_action('wrc_product_deleted', $productId, $deletedRequests, $deletedLeases);

		} catch (\Exception $e) {
			// Error occurred during cleanup, but we still need to continue with product deletion
		}
	}
}

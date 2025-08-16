<?php

declare(strict_types=1);

namespace WRC\Application\Admin;

final class Admin
{
	public function boot(): void
	{
		\add_action('admin_menu', [$this, 'register_menus']);
	}

	public function register_menus(): void
	{
		// Top-level menu
		$capability = 'manage_wrc_requests';
		$slug = 'wrc_rentals';
		\add_menu_page(
			\__('Rentals', 'woo-rentals-core'),
			\__('Rentals', 'woo-rentals-core'),
			$capability,
			$slug,
			[$this, 'render_requests_list'],
			'dashicons-calendar-alt',
			56
		);

		// Submenu: Lease Requests
		\add_submenu_page(
			$slug,
			\__('Lease Requests', 'woo-rentals-core'),
			\__('Lease Requests', 'woo-rentals-core'),
			'manage_wrc_requests',
			'wrc_rentals_requests',
			[$this, 'render_requests_list']
		);

		// Submenu: Leases
		\add_submenu_page(
			$slug,
			\__('Leases', 'woo-rentals-core'),
			\__('Leases', 'woo-rentals-core'),
			'manage_wrc_leases',
			'wrc_rentals_leases',
			[$this, 'render_leases_list']
		);
	}

	public function render_requests_list(): void
	{
		if (!\current_user_can('manage_wrc_requests')) {
			\wp_die(\__('You do not have permission to view this page.', 'woo-rentals-core'));
		}
		
		// Handle delete action
		if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
			\do_action('qm/debug', 'Admin: Processing delete request for ID {id}', ['id' => (int)$_GET['id']]);
			
			if (!\wp_verify_nonce((string)$_GET['_wpnonce'], 'delete_request_' . (int)$_GET['id'])) {
				\do_action('qm/error', 'Admin: Nonce verification failed for delete request {id}', ['id' => (int)$_GET['id']]);
				\wp_die(\__('Security check failed.', 'woo-rentals-core'));
			}
			
			$id = (int)$_GET['id'];
			\do_action('qm/info', 'Admin: Attempting to delete lease request {id}', ['id' => $id]);
			$deleted = \WRC\Domain\LeaseRequest::deleteById($id);
			
			if ($deleted) {
				\do_action('qm/info', 'Admin: Successfully deleted lease request {id}', ['id' => $id]);
				\wp_redirect(\add_query_arg([
					'page' => 'wrc_rentals_requests',
					'deleted' => '1'
				], \admin_url('admin.php')));
				exit;
			} else {
				\do_action('qm/error', 'Admin: Failed to delete lease request {id}', ['id' => $id]);
				\wp_redirect(\add_query_arg([
					'page' => 'wrc_rentals_requests',
					'delete_error' => '1'
				], \admin_url('admin.php')));
				exit;
			}
		}
		
		// Check if we're viewing a specific request detail
		if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
			\wrc_render_template('admin/request-detail.php');
		} else {
			\wrc_render_template('admin/requests-list.php');
		}
	}

	public function render_leases_list(): void
	{
		if (!\current_user_can('manage_wrc_leases')) {
			\wp_die(\__('You do not have permission to view this page.', 'woo-rentals-core'));
		}
		\wrc_render_template('admin/leases-list.php');
	}
}





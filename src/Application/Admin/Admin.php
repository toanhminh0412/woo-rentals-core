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
		\wrc_render_template('admin/requests-list.php');
	}

	public function render_leases_list(): void
	{
		if (!\current_user_can('manage_wrc_leases')) {
			\wp_die(\__('You do not have permission to view this page.', 'woo-rentals-core'));
		}
		\wrc_render_template('admin/leases-list.php');
	}
}





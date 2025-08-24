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
			if (!\wp_verify_nonce((string)$_GET['_wpnonce'], 'delete_request_' . (int)$_GET['id'])) {
				\wp_die(\__('Security check failed.', 'woo-rentals-core'));
			}
			
			$id = (int)$_GET['id'];
			$deleted = \WRC\Domain\LeaseRequest::deleteById($id);
			
			if ($deleted) {
				\wp_redirect(\add_query_arg([
					'page' => 'wrc_rentals_requests',
					'deleted' => '1'
				], \admin_url('admin.php')));
				exit;
			} else {
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
		// Handle save from edit form
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lease_id']) && isset($_POST['_wpnonce'])) {
			$id = (int)$_POST['lease_id'];
			if (!\wp_verify_nonce((string)$_POST['_wpnonce'], 'save_lease_' . $id)) {
				\wp_die(\__('Security check failed.', 'woo-rentals-core'));
			}
			$toUpdate = [];
			if (isset($_POST['product_id'])) { $toUpdate['product_id'] = absint((string)$_POST['product_id']); }
			if (array_key_exists('variation_id', $_POST)) { $toUpdate['variation_id'] = $_POST['variation_id'] === '' ? null : absint((string)$_POST['variation_id']); }
			if (isset($_POST['customer_id'])) { $toUpdate['customer_id'] = absint((string)$_POST['customer_id']); }
			if (array_key_exists('request_id', $_POST)) { $toUpdate['request_id'] = $_POST['request_id'] === '' ? null : absint((string)$_POST['request_id']); }
			if (isset($_POST['start_date'])) { $toUpdate['start_date'] = sanitize_text_field((string)wp_unslash($_POST['start_date'])); }
			if (isset($_POST['end_date'])) { $toUpdate['end_date'] = sanitize_text_field((string)wp_unslash($_POST['end_date'])); }
			if (isset($_POST['qty'])) { $toUpdate['qty'] = max(1, (int)$_POST['qty']); }
			if (isset($_POST['status'])) { $toUpdate['status'] = sanitize_text_field((string)wp_unslash($_POST['status'])); }
			if (isset($_POST['meta'])) {
				$metaRaw = (string)wp_unslash($_POST['meta']);
				$decoded = json_decode($metaRaw, true);
				if ($metaRaw !== '' && !is_array($decoded)) {
					$redirect = \add_query_arg([
						'page' => 'wrc_rentals_leases',
						'action' => 'edit',
						'id' => $id,
						'json_error' => '1'
					], \admin_url('admin.php'));
					\wp_redirect($redirect);
					exit;
				}
				$toUpdate['meta'] = is_array($decoded) ? $decoded : [];
			}

			try {
				\WRC\Domain\Lease::updateFields($id, $toUpdate);
			} catch (\InvalidArgumentException $e) {
				$redirect = \add_query_arg([
					'page' => 'wrc_rentals_leases',
					'action' => 'edit',
					'id' => $id,
					'error' => '1',
					'msg' => rawurlencode($e->getMessage()),
				], \admin_url('admin.php'));
				\wp_redirect($redirect);
				exit;
			} catch (\RuntimeException $e) {
				$redirect = \add_query_arg([
					'page' => 'wrc_rentals_leases',
					'action' => 'edit',
					'id' => $id,
					'error' => '1'
				], \admin_url('admin.php'));
				\wp_redirect($redirect);
				exit;
			}

			$redirect = \add_query_arg([
				'page' => 'wrc_rentals_leases',
				'action' => 'edit',
				'id' => $id,
				'updated' => '1'
			], \admin_url('admin.php'));
			\wp_redirect($redirect);
			exit;
		}

		// Edit screen
		if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
			$id = (int)$_GET['id'];
			$lease = \WRC\Domain\Lease::findByIdArray($id);
			if ($lease === null) {
				\wp_die(\__('Lease not found.', 'woo-rentals-core'));
			}
			\wrc_render_template('admin/lease-edit.php', [
				'lease' => $lease,
				'allowed_statuses' => [
					'active' => \__('Active', 'woo-rentals-core'),
					'completed' => \__('Completed', 'woo-rentals-core'),
					'cancelled' => \__('Cancelled', 'woo-rentals-core'),
				],
			]);
			return;
		}

		\wrc_render_template('admin/leases-list.php');
	}
}





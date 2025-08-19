<?php

declare(strict_types=1);

namespace WRC\Infrastructure;

final class Installer
{
	private const SCHEMA_VERSION = '3';
	public function boot(): void
	{
		// Placeholder for any runtime setup needed later
	}
	public static function activate(): void
	{
		$installedVersion = get_option('wrc_db_version');
		if ($installedVersion === false) {
			add_option('wrc_db_version', '0');
			$installedVersion = '0';
		}

		// Only run schema (dbDelta) when version changes
		if ($installedVersion !== self::SCHEMA_VERSION) {
			self::create_lease_requests_table();
			self::create_leases_table();
			update_option('wrc_db_version', self::SCHEMA_VERSION);
		}

		// Always ensure capabilities are granted on activation
		self::grant_capabilities();
	}

	private static function grant_capabilities(): void
	{
		$rolesToGrant = ['administrator', 'shop_manager', 'wcfm_vendor'];
		$capabilities = ['manage_wrc_requests', 'manage_wrc_leases'];

		foreach ($rolesToGrant as $roleName) {
			$role = get_role($roleName);
			if (!$role) {
				continue;
			}
			foreach ($capabilities as $capability) {
				$role->add_cap($capability);
			}
		}
	}

	private static function create_lease_requests_table(): void
	{
		global $wpdb;

		$tableName = $wpdb->prefix . 'wrc_lease_requests';
		$charsetCollate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$tableName} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned DEFAULT NULL,
			requester_id bigint(20) unsigned NOT NULL,
			start_date datetime NOT NULL,
			end_date datetime NOT NULL,
			qty int(11) NOT NULL DEFAULT 1,
			notes text NULL,
			total_price bigint(20) unsigned NOT NULL,
			requesting_vendor_id bigint(20) unsigned NOT NULL,
			meta longtext NULL,
			status ENUM('awaiting lessee response','awaiting lessor response','awaiting payment','accepted','declined','cancelled') NOT NULL DEFAULT 'awaiting lessee response',
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY requester_id (requester_id),
			KEY status (status),
			KEY start_end (start_date, end_date)
		) {$charsetCollate};";

		dbDelta($sql);
	}

	private static function create_leases_table(): void
	{
		global $wpdb;

		$tableName = $wpdb->prefix . 'wrc_leases';
		$charsetCollate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$tableName} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			order_item_id bigint(20) unsigned DEFAULT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			request_id bigint(20) unsigned DEFAULT NULL,
			start_date datetime NOT NULL,
			end_date datetime NOT NULL,
			qty int(11) NOT NULL DEFAULT 1,
			meta longtext NULL,
			status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY start_end (start_date, end_date)
		) {$charsetCollate};";

		dbDelta($sql);
	}
}



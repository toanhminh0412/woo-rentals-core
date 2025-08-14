<?php
/**
 * Plugin Name: Woo Rentals Core
 * Plugin URI: https://example.com/
 * Description: Adds rental functionality to WooCommerce products.
 * Version: 0.1.0
 * Author: Archie
 * Author URI: https://example.com/
 * Text Domain: woo-rentals-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Attempt to load Composer autoloader if present
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
	require_once $composerAutoload;
} else {
	// Minimal PSR-4 autoloader for the WRC namespace
	spl_autoload_register(static function ($className) {
		$namespacePrefix = 'WRC\\';
		if (strpos($className, $namespacePrefix) !== 0) {
			return;
		}
		$relativeClass = substr($className, strlen($namespacePrefix));
		$relativePath = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
		if (file_exists($relativePath)) {
			require_once $relativePath;
		}
	});
}

// Register activation hook to run installer
register_activation_hook(__FILE__, ['WRC\\Infrastructure\\Installer', 'activate']);

// Boot the plugin on plugins_loaded
add_action('plugins_loaded', static function () {
	if (class_exists('WRC\\Plugin')) {
		WRC\Plugin::instance()->boot();
	}
});



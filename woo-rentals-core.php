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

// Plugin paths
if (!defined('WRC_PLUGIN_DIR')) {
	define('WRC_PLUGIN_DIR', \plugin_dir_path(__FILE__));
}
if (!defined('WRC_TEMPLATES_DIR')) {
	define('WRC_TEMPLATES_DIR', WRC_PLUGIN_DIR . 'templates/');
}

// Simple template renderer to load files from templates directory in global namespace
if (!function_exists('wrc_render_template')) {
	/**
	 * Render a template from the plugin's templates directory.
	 *
	 * @param string $relativePath Relative path inside templates directory, e.g. 'admin/requests-list.php'
	 * @param array<string,mixed> $variables Optional variables to extract for the template scope
	 */
	function wrc_render_template(string $relativePath, array $variables = []): void
	{
		$base = defined('WRC_TEMPLATES_DIR') ? WRC_TEMPLATES_DIR : \plugin_dir_path(__FILE__) . 'templates/';
		$file = $base . ltrim($relativePath, '/');
		if (!empty($variables)) {
			extract($variables, EXTR_SKIP);
		}
		if (file_exists($file)) {
			require $file;
			return;
		}
		if (\function_exists('esc_html')) {
			echo \esc_html(sprintf('Template not found: %s', $relativePath));
		} else {
			echo sprintf('Template not found: %s', $relativePath);
		}
	}
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
\register_activation_hook(__FILE__, ['WRC\\Infrastructure\\Installer', 'activate']);

// Boot the plugin on plugins_loaded

\add_action('plugins_loaded', static function () {
	if (class_exists('WRC\\Plugin')) {
		WRC\Plugin::instance()->boot();
	}
});



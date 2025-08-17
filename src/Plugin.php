<?php

declare(strict_types=1);

namespace WRC;

use WRC\Application\Admin\Admin;
use WRC\Infrastructure\Installer;
use WRC\Infrastructure\ProductHooks;
use WRC\Infrastructure\REST\Rest;

final class Plugin
{
	private static ?Plugin $instance = null;

	private function __construct()
	{
	}

	public static function instance(): Plugin
	{
		if (self::$instance === null) {
			self::$instance = new Plugin();
		}

		return self::$instance;
	}

	public function boot(): void
	{
		// Initialize subsystems
		(new Admin())->boot();
		(new Rest())->boot();
		(new Installer())->boot();
		(new ProductHooks())->boot();
	}
}



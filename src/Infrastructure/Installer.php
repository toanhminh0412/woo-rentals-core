<?php

declare(strict_types=1);

namespace WRC\Infrastructure;

final class Installer
{
	public function boot(): void
	{
		// Placeholder for any runtime setup needed later
	}
	public static function activate(): void
	{
		// Placeholder: schema will be created in a later task
		if (!get_option('wrc_db_version')) {
			add_option('wrc_db_version', '0');
		}
	}
}



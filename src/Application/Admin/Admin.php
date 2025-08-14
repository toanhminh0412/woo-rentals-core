<?php

declare(strict_types=1);

namespace WRC\Application\Admin;

final class Admin
{
	public function boot(): void
	{
		add_action('admin_init', static function () {
			// Admin subsystem bootstrap placeholder (menus/UI will arrive in later tasks)
		});
	}
}





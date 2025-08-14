<?php

declare(strict_types=1);

namespace WRC\Infrastructure\REST;

final class Rest
{
	public function boot(): void
	{
		add_action('rest_api_init', static function () {
			// REST routes will be registered in later tasks
		});
	}
}





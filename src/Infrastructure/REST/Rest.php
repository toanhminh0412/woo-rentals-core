<?php

declare(strict_types=1);

namespace WRC\Infrastructure\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Rest
{
	public function boot(): void
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes(): void
	{
		$namespace = 'wrc/v1';

		// Lease Requests collection
		register_rest_route($namespace, '/requests', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'create_request'],
				'permission_callback' => [$this, 'permission_create_request'],
			],
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'list_requests'],
				'permission_callback' => [$this, 'permission_list_requests'],
			],
		]);

		// Lease Requests item
		register_rest_route($namespace, '/requests/(?P<id>\\d+)', [
			[
				'args' => [ 'id' => [ 'validate_callback' => static function ($param): bool { return is_numeric($param) && (int)$param > 0; } ] ],
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_request'],
				'permission_callback' => [$this, 'permission_get_request'],
			],
			[
				'args' => [ 'id' => [ 'validate_callback' => static function ($param): bool { return is_numeric($param) && (int)$param > 0; } ] ],
				'events' => 'status update',
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => [$this, 'update_request_status'],
				'permission_callback' => [$this, 'permission_update_request_status'],
			],
		]);

		// Leases collection
		register_rest_route($namespace, '/leases', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'create_lease'],
				'permission_callback' => [$this, 'permission_create_lease'],
			],
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'list_leases'],
				'permission_callback' => [$this, 'permission_list_leases'],
			],
		]);

		// Leases item
		register_rest_route($namespace, '/leases/(?P<id>\\d+)', [
			[
				'args' => [ 'id' => [ 'validate_callback' => static function ($param): bool { return is_numeric($param) && (int)$param > 0; } ] ],
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_lease'],
				'permission_callback' => [$this, 'permission_get_lease'],
			],
			[
				'args' => [ 'id' => [ 'validate_callback' => static function ($param): bool { return is_numeric($param) && (int)$param > 0; } ] ],
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => [$this, 'update_lease_status'],
				'permission_callback' => [$this, 'permission_update_lease_status'],
			],
		]);
	}

	// Permissions
	public function permission_create_request(WP_REST_Request $request): bool
	{
		return is_user_logged_in();
	}

	public function permission_list_requests(WP_REST_Request $request): bool
	{
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			return is_user_logged_in();
		}
		return current_user_can('manage_wrc_requests');
	}

	public function permission_get_request(WP_REST_Request $request): bool
	{
		// Without object ownership checks yet, restrict to managers
		return current_user_can('manage_wrc_requests');
	}

	public function permission_update_request_status(WP_REST_Request $request): bool
	{
		return current_user_can('manage_wrc_requests');
	}

	public function permission_create_lease(WP_REST_Request $request): bool
	{
		return current_user_can('manage_wrc_leases');
	}

	public function permission_list_leases(WP_REST_Request $request): bool
	{
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			return is_user_logged_in();
		}
		return current_user_can('manage_wrc_leases');
	}

	public function permission_get_lease(WP_REST_Request $request): bool
	{
		return current_user_can('manage_wrc_leases');
	}

	public function permission_update_lease_status(WP_REST_Request $request): bool
	{
		return current_user_can('manage_wrc_leases');
	}

	// Handlers — Requests
	public function create_request(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$payload = (array)$request->get_json_params();
		$required = ['product_id', 'start_date', 'end_date', 'qty'];
		foreach ($required as $fieldName) {
			if (!array_key_exists($fieldName, $payload)) {
				return new WP_Error('wrc_missing_field', sprintf('Missing required field: %s', $fieldName), ['status' => 400]);
			}
		}
		$productId = absint($payload['product_id']);
		$variationId = isset($payload['variation_id']) ? absint($payload['variation_id']) : null;
		$startDate = (string)$payload['start_date'];
		$endDate = (string)$payload['end_date'];
		$qty = max(1, (int)$payload['qty']);
		$notes = isset($payload['notes']) ? sanitize_text_field((string)$payload['notes']) : '';
		$meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];

		if (!$this->is_valid_date($startDate) || !$this->is_valid_date($endDate)) {
			return new WP_Error('wrc_invalid_date', 'Invalid date format. Use YYYY-MM-DD.', ['status' => 400]);
		}

		$response = [
			'id' => 0,
			'product_id' => $productId,
			'variation_id' => $variationId,
			'qty' => $qty,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'notes' => $notes,
			'meta' => $meta,
			'message' => 'Lease request created (stub)'
		];

		return new WP_REST_Response($response, 201);
	}

	public function list_requests(WP_REST_Request $request): WP_REST_Response
	{
		$status = (string)$request->get_param('status');
		$productId = absint((string)$request->get_param('product_id'));
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		$page = max(1, (int)$request->get_param('page'));
		$perPage = max(1, (int)$request->get_param('per_page')) ?: 20;

		$data = [
			'filters' => [
				'status' => $status,
				'product_id' => $productId,
				'mine' => $mine,
				'page' => $page,
				'per_page' => $perPage,
			],
			'items' => [],
			'total' => 0,
		];

		return new WP_REST_Response($data, 200);
	}

	public function get_request(WP_REST_Request $request): WP_REST_Response
	{
		$id = absint($request['id']);
		return new WP_REST_Response([
			'id' => $id,
			'message' => 'Lease request detail (stub)'
		], 200);
	}

	public function update_request_status(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$payload = (array)$request->get_json_params();
		$action = isset($payload['action']) ? (string)$payload['action'] : '';
		$note = isset($payload['note']) ? sanitize_text_field((string)$payload['note']) : '';
		$allowed = ['approve', 'decline', 'cancel'];
		if (!in_array($action, $allowed, true)) {
			return new WP_Error('wrc_invalid_action', 'Invalid action.', ['status' => 400]);
		}
		return new WP_REST_Response([
			'id' => $id,
			'action' => $action,
			'note' => $note,
			'message' => 'Lease request status updated (stub)'
		], 200);
	}

	// Handlers — Leases
	public function create_lease(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$payload = (array)$request->get_json_params();
		$required = ['product_id', 'customer_id', 'start_date', 'end_date', 'qty'];
		foreach ($required as $fieldName) {
			if (!array_key_exists($fieldName, $payload)) {
				return new WP_Error('wrc_missing_field', sprintf('Missing required field: %s', $fieldName), ['status' => 400]);
			}
		}
		$productId = absint($payload['product_id']);
		$variationId = isset($payload['variation_id']) ? absint($payload['variation_id']) : null;
		$customerId = absint($payload['customer_id']);
		$requestId = isset($payload['request_id']) ? absint($payload['request_id']) : null;
		$startDate = (string)$payload['start_date'];
		$endDate = (string)$payload['end_date'];
		$qty = max(1, (int)$payload['qty']);
		$meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];

		if (!$this->is_valid_date($startDate) || !$this->is_valid_date($endDate)) {
			return new WP_Error('wrc_invalid_date', 'Invalid date format. Use YYYY-MM-DD.', ['status' => 400]);
		}

		$response = [
			'id' => 0,
			'product_id' => $productId,
			'variation_id' => $variationId,
			'customer_id' => $customerId,
			'request_id' => $requestId,
			'qty' => $qty,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'meta' => $meta,
			'message' => 'Lease created (stub)'
		];

		return new WP_REST_Response($response, 201);
	}

	public function list_leases(WP_REST_Request $request): WP_REST_Response
	{
		$status = (string)$request->get_param('status');
		$productId = absint((string)$request->get_param('product_id'));
		$customerId = absint((string)$request->get_param('customer_id'));
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);

		$data = [
			'filters' => [
				'status' => $status,
				'product_id' => $productId,
				'customer_id' => $customerId,
				'mine' => $mine,
			],
			'items' => [],
			'total' => 0,
		];

		return new WP_REST_Response($data, 200);
	}

	public function get_lease(WP_REST_Request $request): WP_REST_Response
	{
		$id = absint($request['id']);
		return new WP_REST_Response([
			'id' => $id,
			'message' => 'Lease detail (stub)'
		], 200);
	}

	public function update_lease_status(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$payload = (array)$request->get_json_params();
		$action = isset($payload['action']) ? (string)$payload['action'] : '';
		$allowed = ['complete', 'cancel'];
		if (!in_array($action, $allowed, true)) {
			return new WP_Error('wrc_invalid_action', 'Invalid action.', ['status' => 400]);
		}
		return new WP_REST_Response([
			'id' => $id,
			'action' => $action,
			'message' => 'Lease status updated (stub)'
		], 200);
	}

	// Utilities
	private function is_valid_date(string $date): bool
	{
		$dt = \DateTime::createFromFormat('Y-m-d', $date);
		return $dt !== false && $dt->format('Y-m-d') === $date;
	}
}





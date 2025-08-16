<?php

declare(strict_types=1);

namespace WRC\Infrastructure\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WRC\Domain\LeaseRequest;
use WRC\Domain\Lease;

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
		do_action('qm/start', 'wrc_api_create_request');
		do_action('qm/debug', 'REST API: Creating lease request via {endpoint} for user {user_id}', [
			'user_id' => get_current_user_id(),
			'endpoint' => 'POST /wrc/v1/requests',
		]);
		
		$payload = (array)$request->get_json_params();
		$required = ['product_id', 'start_date', 'end_date', 'qty'];
		foreach ($required as $fieldName) {
			if (!array_key_exists($fieldName, $payload)) {
				do_action('qm/warning', 'REST API validation failed: missing required field {missing_field}. Provided fields: {provided_fields}', [
					'missing_field' => $fieldName,
					'provided_fields' => array_keys($payload),
				]);
				do_action('qm/stop', 'wrc_api_create_request');
				return new WP_Error('wrc_missing_field', sprintf('Missing required field: %s', $fieldName), ['status' => 400]);
			}
		}
		$productId = absint($payload['product_id']);
		$variationId = isset($payload['variation_id']) ? absint($payload['variation_id']) : null;
		$startDate = (string)$payload['start_date'];
		$endDate = (string)$payload['end_date'];
		$qty = max(1, (int)$payload['qty']);
		$notes = isset($payload['notes']) ? sanitize_text_field((string)$payload['notes']) : null;
		$meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
		$requesterId = get_current_user_id();

		try {
			$entity = new LeaseRequest(
				null,
				$productId,
				$variationId ?: null,
				$requesterId,
				$startDate,
				$endDate,
				$qty,
				LeaseRequest::STATUS_PENDING,
				$notes,
				$meta
			);
		} catch (\InvalidArgumentException $e) {
			do_action('qm/warning', 'REST API: Invalid input for lease request creation: {error}', [
				'error' => $e->getMessage()
			]);
			do_action('qm/stop', 'wrc_api_create_request');
			return new WP_Error('wrc_invalid_input', $e->getMessage(), ['status' => 400]);
		}

		try {
			$id = LeaseRequest::create($entity);
		} catch (\RuntimeException $e) {
			do_action('qm/error', 'REST API: Database error creating lease request: {error}', [
				'error' => $e->getMessage(),
			]);
			do_action('qm/stop', 'wrc_api_create_request');
			return new WP_Error('wrc_db_error', 'Failed to create lease request.', ['status' => 500]);
		}

		do_action('qm/info', 'REST API: Lease request created successfully with ID {id}', ['id' => $id]);
		do_action('qm/stop', 'wrc_api_create_request');
		return new WP_REST_Response(LeaseRequest::findByIdArray($id), 201);
	}

	public function list_requests(WP_REST_Request $request): WP_REST_Response
	{
		$status = (string)$request->get_param('status');
		$productId = absint((string)$request->get_param('product_id'));
		$requesterId = null;
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			$requesterId = get_current_user_id();
		}
		$page = max(1, (int)$request->get_param('page'));
		$perPage = (int)$request->get_param('per_page');
		$perPage = $perPage > 0 ? $perPage : 20;

		$result = LeaseRequest::listAsArray([
			'status' => $status ?: null,
			'product_id' => $productId ?: null,
			'requester_id' => $requesterId ?: null,
		], $page, $perPage);

		return new WP_REST_Response($result, 200);
	}

	public function get_request(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$record = LeaseRequest::findByIdArray($id);
		if ($record === null) {
			return new WP_Error('wrc_not_found', 'Lease request not found.', ['status' => 404]);
		}
		return new WP_REST_Response($record, 200);
	}

	public function update_request_status(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		do_action('qm/start', 'wrc_api_update_request_status');
		
		$id = absint($request['id']);
		$payload = (array)$request->get_json_params();
		$action = isset($payload['action']) ? (string)$payload['action'] : '';
		$note = isset($payload['note']) ? sanitize_text_field((string)$payload['note']) : '';
		
		do_action('qm/debug', 'REST API: Updating lease request {id} status via {action} by user {user_id}', [
			'id' => $id,
			'action' => $action,
			'user_id' => get_current_user_id(),
		]);
		
		$allowed = ['approve', 'decline', 'cancel'];
		if (!in_array($action, $allowed, true)) {
			do_action('qm/warning', 'REST API: Invalid action {action} for request status update. Allowed actions: {allowed_actions}', [
				'action' => $action,
				'allowed_actions' => $allowed,
			]);
			do_action('qm/stop', 'wrc_api_update_request_status');
			return new WP_Error('wrc_invalid_action', 'Invalid action.', ['status' => 400]);
		}
		$existing = LeaseRequest::findByIdArray($id);
		if ($existing === null) {
			do_action('qm/warning', 'REST API: Lease request {id} not found for status update', ['id' => $id]);
			do_action('qm/stop', 'wrc_api_update_request_status');
			return new WP_Error('wrc_not_found', 'Lease request not found.', ['status' => 404]);
		}
		$actionToStatus = [
			'approve' => LeaseRequest::STATUS_APPROVED,
			'decline' => LeaseRequest::STATUS_DECLINED,
			'cancel' => LeaseRequest::STATUS_CANCELLED,
		];
		$statusToSet = $actionToStatus[$action] ?? null;
		if ($statusToSet === null) {
			do_action('qm/stop', 'wrc_api_update_request_status');
			return new WP_Error('wrc_invalid_action', 'Invalid action.', ['status' => 400]);
		}
		LeaseRequest::updateStatus($id, $statusToSet);
		
		do_action('qm/info', 'REST API: Lease request {id} status updated to {new_status} via {action}', [
			'id' => $id,
			'action' => $action,
			'new_status' => $statusToSet,
		]);
		do_action('qm/stop', 'wrc_api_update_request_status');
		return new WP_REST_Response(LeaseRequest::findByIdArray($id), 200);
	}

	// Handlers — Leases
	public function create_lease(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		do_action('qm/start', 'wrc_api_create_lease');
		do_action('qm/debug', 'REST API: Creating lease via {endpoint} for user {user_id}', [
			'user_id' => get_current_user_id(),
			'endpoint' => 'POST /wrc/v1/leases',
		]);
		
		$payload = (array)$request->get_json_params();
		$required = ['product_id', 'customer_id', 'start_date', 'end_date', 'qty'];
		foreach ($required as $fieldName) {
			if (!array_key_exists($fieldName, $payload)) {
				do_action('qm/warning', 'REST API validation failed: missing required field {missing_field} for lease. Provided fields: {provided_fields}', [
					'missing_field' => $fieldName,
					'provided_fields' => array_keys($payload),
				]);
				do_action('qm/stop', 'wrc_api_create_lease');
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

		try {
			$entity = new Lease(
				null,
				$productId,
				$variationId ?: null,
				null,
				null,
				$customerId,
				$requestId ?: null,
				$startDate,
				$endDate,
				$qty,
				Lease::STATUS_ACTIVE,
				$meta
			);
		} catch (\InvalidArgumentException $e) {
			do_action('qm/warning', 'REST API: Invalid input for lease creation: {error}', [
				'error' => $e->getMessage()
			]);
			do_action('qm/stop', 'wrc_api_create_lease');
			return new WP_Error('wrc_invalid_input', $e->getMessage(), ['status' => 400]);
		}

		try {
			$id = Lease::create($entity);
		} catch (\RuntimeException $e) {
			do_action('qm/error', 'REST API: Database error creating lease: {error}', [
				'error' => $e->getMessage(),
			]);
			do_action('qm/stop', 'wrc_api_create_lease');
			return new WP_Error('wrc_db_error', 'Failed to create lease.', ['status' => 500]);
		}
		
		do_action('qm/info', 'REST API: Lease created successfully with ID {id}', ['id' => $id]);
		do_action('qm/stop', 'wrc_api_create_lease');
		return new WP_REST_Response(Lease::findByIdArray($id), 201);
	}

	public function list_leases(WP_REST_Request $request): WP_REST_Response
	{
		$status = (string)$request->get_param('status');
		$productId = absint((string)$request->get_param('product_id'));
		$customerId = absint((string)$request->get_param('customer_id'));
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			$customerId = get_current_user_id();
		}
		$items = Lease::listAsArray([
			'status' => $status ?: null,
			'product_id' => $productId ?: null,
			'customer_id' => $customerId ?: null,
		]);

		return new WP_REST_Response([
			'items' => $items,
			'total' => count($items),
		], 200);
	}

	public function get_lease(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$record = Lease::findByIdArray($id);
		if ($record === null) {
			return new WP_Error('wrc_not_found', 'Lease not found.', ['status' => 404]);
		}
		return new WP_REST_Response($record, 200);
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
		$existing = Lease::findByIdArray($id);
		if ($existing === null) {
			return new WP_Error('wrc_not_found', 'Lease not found.', ['status' => 404]);
		}
		$actionToStatus = [
			'complete' => Lease::STATUS_COMPLETED,
			'cancel' => Lease::STATUS_CANCELLED,
		];
		$statusToSet = $actionToStatus[$action] ?? null;
		if ($statusToSet === null) {
			return new WP_Error('wrc_invalid_action', 'Invalid action.', ['status' => 400]);
		}
		Lease::updateStatus($id, $statusToSet);
		return new WP_REST_Response(Lease::findByIdArray($id), 200);
	}

	// Utilities
	private function is_valid_date(string $date): bool
	{
		$dt = \DateTime::createFromFormat('Y-m-d', $date);
		return $dt !== false && $dt->format('Y-m-d') === $date;
	}
}





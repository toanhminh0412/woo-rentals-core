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
			return new WP_Error('wrc_invalid_input', $e->getMessage(), ['status' => 400]);
		}

		global $wpdb;
		$nowUtc = gmdate('Y-m-d H:i:s');
		$inserted = $wpdb->insert(
			$this->table_lease_requests(),
			[
				'product_id' => $entity->getProductId(),
				'variation_id' => $entity->getVariationId(),
				'requester_id' => $requesterId,
				'start_date' => $entity->getStartDate()->format('Y-m-d'),
				'end_date' => $entity->getEndDate()->format('Y-m-d'),
				'qty' => $entity->getQuantity(),
				'notes' => $entity->getNotes(),
				'meta' => $this->encode_meta($entity->getMeta()),
				'status' => $entity->getStatus(),
				'created_at' => $nowUtc,
			],
			['%d','%d','%d','%s','%s','%d','%s','%s','%s','%s']
		);
		if ($inserted === false) {
			return new WP_Error('wrc_db_error', 'Failed to create lease request.', ['status' => 500]);
		}
		$id = (int)$wpdb->insert_id;

		return new WP_REST_Response($this->get_request_by_id_array($id), 201);
	}

	public function list_requests(WP_REST_Request $request): WP_REST_Response
	{
		global $wpdb;
		$where = [];
		$args = [];
		$status = (string)$request->get_param('status');
		if ($status !== '') {
			$where[] = 'status = %s';
			$args[] = $status;
		}
		$productId = absint((string)$request->get_param('product_id'));
		if ($productId > 0) {
			$where[] = 'product_id = %d';
			$args[] = $productId;
		}
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			$requesterId = get_current_user_id();
			$where[] = 'requester_id = %d';
			$args[] = $requesterId;
		}
		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

		$page = max(1, (int)$request->get_param('page'));
		$perPage = (int)$request->get_param('per_page');
		$perPage = $perPage > 0 ? $perPage : 20;
		$offset = ($page - 1) * $perPage;

		$totalSql = "SELECT COUNT(*) FROM {$this->table_lease_requests()} {$whereSql}";
		$total = (int)$wpdb->get_var($wpdb->prepare($totalSql, $args));

		$listSql = "SELECT * FROM {$this->table_lease_requests()} {$whereSql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$listArgs = array_merge($args, [$perPage, $offset]);
		$rows = $wpdb->get_results($wpdb->prepare($listSql, $listArgs));
		$items = array_map([$this, 'map_request_row_to_array'], $rows ?: []);

		return new WP_REST_Response([
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'per_page' => $perPage,
		], 200);
	}

	public function get_request(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$record = $this->get_request_by_id_array($id);
		if ($record === null) {
			return new WP_Error('wrc_not_found', 'Lease request not found.', ['status' => 404]);
		}
		return new WP_REST_Response($record, 200);
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
		global $wpdb;
		$existing = $this->get_request_by_id_array($id);
		if ($existing === null) {
			return new WP_Error('wrc_not_found', 'Lease request not found.', ['status' => 404]);
		}
		$wpdb->update(
			$this->table_lease_requests(),
			['status' => $action, 'updated_at' => gmdate('Y-m-d H:i:s')],
			['id' => $id],
			['%s','%s'],
			['%d']
		);
		return new WP_REST_Response($this->get_request_by_id_array($id), 200);
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
			return new WP_Error('wrc_invalid_input', $e->getMessage(), ['status' => 400]);
		}

		global $wpdb;
		$nowUtc = gmdate('Y-m-d H:i:s');
		$inserted = $wpdb->insert(
			$this->table_leases(),
			[
				'product_id' => $entity->getProductId(),
				'variation_id' => $entity->getVariationId(),
				'order_id' => null,
				'order_item_id' => null,
				'customer_id' => $entity->getCustomerId(),
				'request_id' => $entity->getRequestId(),
				'start_date' => $entity->getStartDate()->format('Y-m-d'),
				'end_date' => $entity->getEndDate()->format('Y-m-d'),
				'qty' => $entity->getQuantity(),
				'meta' => $this->encode_meta($entity->getMeta()),
				'status' => $entity->getStatus(),
				'created_at' => $nowUtc,
			],
			['%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%s','%s']
		);
		if ($inserted === false) {
			return new WP_Error('wrc_db_error', 'Failed to create lease.', ['status' => 500]);
		}
		$id = (int)$wpdb->insert_id;
		return new WP_REST_Response($this->get_lease_by_id_array($id), 201);
	}

	public function list_leases(WP_REST_Request $request): WP_REST_Response
	{
		global $wpdb;
		$where = [];
		$args = [];
		$status = (string)$request->get_param('status');
		if ($status !== '') {
			$where[] = 'status = %s';
			$args[] = $status;
		}
		$productId = absint((string)$request->get_param('product_id'));
		if ($productId > 0) {
			$where[] = 'product_id = %d';
			$args[] = $productId;
		}
		$customerId = absint((string)$request->get_param('customer_id'));
		$mine = filter_var((string)$request->get_param('mine'), FILTER_VALIDATE_BOOLEAN);
		if ($mine) {
			$customerId = get_current_user_id();
		}
		if ($customerId > 0) {
			$where[] = 'customer_id = %d';
			$args[] = $customerId;
		}
		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

		$sql = "SELECT * FROM {$this->table_leases()} {$whereSql} ORDER BY created_at DESC LIMIT 100";
		$rows = $wpdb->get_results($wpdb->prepare($sql, $args));
		$items = array_map([$this, 'map_lease_row_to_array'], $rows ?: []);

		return new WP_REST_Response([
			'items' => $items,
			'total' => count($items),
		], 200);
	}

	public function get_lease(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$id = absint($request['id']);
		$record = $this->get_lease_by_id_array($id);
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
		global $wpdb;
		$existing = $this->get_lease_by_id_array($id);
		if ($existing === null) {
			return new WP_Error('wrc_not_found', 'Lease not found.', ['status' => 404]);
		}
		$wpdb->update(
			$this->table_leases(),
			['status' => $action, 'updated_at' => gmdate('Y-m-d H:i:s')],
			['id' => $id],
			['%s','%s'],
			['%d']
		);
		return new WP_REST_Response($this->get_lease_by_id_array($id), 200);
	}

	// Utilities
	private function is_valid_date(string $date): bool
	{
		$dt = \DateTime::createFromFormat('Y-m-d', $date);
		return $dt !== false && $dt->format('Y-m-d') === $date;
	}

	// DB helpers
	private function table_lease_requests(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'wrc_lease_requests';
	}

	private function table_leases(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'wrc_leases';
	}

	/** @param array<string,mixed> $meta */
	private function encode_meta(array $meta): string
	{
		$json = wp_json_encode($meta);
		return is_string($json) ? $json : '{}';
	}

	/** @return array<string,mixed> */
	private function decode_meta(?string $json): array
	{
		if ($json === null || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	/** @return array<string,mixed>|null */
	private function get_request_by_id_array(int $id): ?array
	{
		global $wpdb;
		$sql = "SELECT * FROM {$this->table_lease_requests()} WHERE id = %d";
		$row = $wpdb->get_row($wpdb->prepare($sql, [$id]));
		return $row ? $this->map_request_row_to_array($row) : null;
	}

	/** @return array<string,mixed> */
	private function map_request_row_to_array(object $row): array
	{
		return [
			'id' => (int)$row->id,
			'product_id' => (int)$row->product_id,
			'variation_id' => $row->variation_id !== null ? (int)$row->variation_id : null,
			'requester_id' => (int)$row->requester_id,
			'start_date' => (string)$row->start_date,
			'end_date' => (string)$row->end_date,
			'qty' => (int)$row->qty,
			'notes' => $row->notes !== null ? (string)$row->notes : null,
			'meta' => $this->decode_meta($row->meta ?? null),
			'status' => (string)$row->status,
			'created_at' => (string)$row->created_at,
			'updated_at' => $row->updated_at !== null ? (string)$row->updated_at : null,
		];
	}

	/** @return array<string,mixed>|null */
	private function get_lease_by_id_array(int $id): ?array
	{
		global $wpdb;
		$sql = "SELECT * FROM {$this->table_leases()} WHERE id = %d";
		$row = $wpdb->get_row($wpdb->prepare($sql, [$id]));
		return $row ? $this->map_lease_row_to_array($row) : null;
	}

	/** @return array<string,mixed> */
	private function map_lease_row_to_array(object $row): array
	{
		return [
			'id' => (int)$row->id,
			'product_id' => (int)$row->product_id,
			'variation_id' => $row->variation_id !== null ? (int)$row->variation_id : null,
			'order_id' => $row->order_id !== null ? (int)$row->order_id : null,
			'order_item_id' => $row->order_item_id !== null ? (int)$row->order_item_id : null,
			'customer_id' => (int)$row->customer_id,
			'request_id' => $row->request_id !== null ? (int)$row->request_id : null,
			'start_date' => (string)$row->start_date,
			'end_date' => (string)$row->end_date,
			'qty' => (int)$row->qty,
			'meta' => $this->decode_meta($row->meta ?? null),
			'status' => (string)$row->status,
			'created_at' => (string)$row->created_at,
			'updated_at' => $row->updated_at !== null ? (string)$row->updated_at : null,
		];
	}
}





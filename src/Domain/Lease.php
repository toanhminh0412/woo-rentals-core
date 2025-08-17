<?php

declare(strict_types=1);

namespace WRC\Domain;

final class Lease
{
	public const STATUS_ACTIVE = 'active';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_CANCELLED = 'cancelled';

	/** @var array<int, string> */
	private static array $allowedStatuses = [
		self::STATUS_ACTIVE,
		self::STATUS_COMPLETED,
		self::STATUS_CANCELLED,
	];

	private ?int $id;
	private int $productId;
	private ?int $variationId;
	private ?int $orderId;
	private ?int $orderItemId;
	private int $customerId;
	private ?int $requestId;
	private \DateTimeImmutable $startDate;
	private \DateTimeImmutable $endDate;
	private int $quantity;
	/** @var array<string, mixed> */
	private array $meta;
	private string $status;
	private ?\DateTimeImmutable $createdAt;
	private ?\DateTimeImmutable $updatedAt;

	/**
	 * @param array<string, mixed> $meta
	 */
	public function __construct(
		?int $id,
		int $productId,
		?int $variationId,
		?int $orderId,
		?int $orderItemId,
		int $customerId,
		?int $requestId,
		string $startDate,
		string $endDate,
		int $quantity,
		string $status = self::STATUS_ACTIVE,
		array $meta = [],
		?\DateTimeImmutable $createdAt = null,
		?\DateTimeImmutable $updatedAt = null
	) {
		$this->id = $id;
		$this->productId = self::assertPositiveInt($productId, 'product_id');
		$this->variationId = $variationId !== null ? self::assertPositiveInt($variationId, 'variation_id') : null;
		$this->orderId = $orderId !== null ? self::assertPositiveInt($orderId, 'order_id') : null;
		$this->orderItemId = $orderItemId !== null ? self::assertPositiveInt($orderItemId, 'order_item_id') : null;
		$this->customerId = self::assertPositiveInt($customerId, 'customer_id');
		$this->requestId = $requestId !== null ? self::assertPositiveInt($requestId, 'request_id') : null;
		$this->quantity = self::assertMinInt($quantity, 1, 'qty');
		$this->meta = self::assertJsonEncodableMap($meta, 'meta');
		$this->status = self::assertAllowedStatus($status);

		$tz = new \DateTimeZone('UTC');
		$this->startDate = self::assertDateYmd($startDate, 'start_date', $tz);
		$this->endDate = self::assertDateYmd($endDate, 'end_date', $tz);
		self::assertStartBeforeOrEqualEnd($this->startDate, $this->endDate);

		$this->createdAt = $createdAt;
		$this->updatedAt = $updatedAt;
	}

	public function getId(): ?int { return $this->id; }
	public function getProductId(): int { return $this->productId; }
	public function getVariationId(): ?int { return $this->variationId; }
	public function getOrderId(): ?int { return $this->orderId; }
	public function getOrderItemId(): ?int { return $this->orderItemId; }
	public function getCustomerId(): int { return $this->customerId; }
	public function getRequestId(): ?int { return $this->requestId; }
	public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
	public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
	public function getQuantity(): int { return $this->quantity; }
	/** @return array<string, mixed> */
	public function getMeta(): array { return $this->meta; }
	public function getStatus(): string { return $this->status; }
	public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
	public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

	private static function assertPositiveInt(int $value, string $fieldName): int
	{
		if ($value <= 0) {
			do_action('qm/warning', 'Validation failed: {field} must be positive (got {value})', [
				'field' => $fieldName,
				'value' => $value,
			]);
			throw new \InvalidArgumentException(sprintf('%s must be a positive integer', $fieldName));
		}
		return $value;
	}

	private static function assertMinInt(int $value, int $min, string $fieldName): int
	{
		if ($value < $min) {
			throw new \InvalidArgumentException(sprintf('%s must be >= %d', $fieldName, $min));
		}
		return $value;
	}

	private static function assertAllowedStatus(string $status): string
	{
		if (!in_array($status, self::$allowedStatuses, true)) {
			do_action('qm/warning', 'Invalid lease status: {provided_status}. Allowed statuses: {allowed_statuses}', [
				'provided_status' => $status,
				'allowed_statuses' => self::$allowedStatuses,
			]);
			throw new \InvalidArgumentException('Invalid status');
		}
		return $status;
	}

	private static function assertDateYmd(string $datetime, string $fieldName, \DateTimeZone $tz): \DateTimeImmutable
	{
		// Try ISO 8601 datetime format first (Y-m-d\TH:i), then fall back to other formats
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $datetime, $tz);
		if ($dt === false || $dt->format('Y-m-d\TH:i') !== $datetime) {
			// Try legacy datetime format (Y-m-d H:i:s)
			$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, $tz);
			if ($dt === false || $dt->format('Y-m-d H:i:s') !== $datetime) {
				// Try date-only format with time set to 00:00:00
				$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $datetime, $tz);
				if ($dt === false || $dt->format('Y-m-d') !== $datetime) {
					do_action('qm/warning', 'Invalid datetime format for {field}: {provided_datetime} (expected {expected_format})', [
						'field' => $fieldName,
						'provided_datetime' => $datetime,
						'expected_format' => 'YYYY-MM-DDTHH:MM, YYYY-MM-DD HH:MM:SS, or YYYY-MM-DD',
					]);
					throw new \InvalidArgumentException(sprintf('%s must be in YYYY-MM-DDTHH:MM, YYYY-MM-DD HH:MM:SS, or YYYY-MM-DD format', $fieldName));
				}
			}
		}
		return $dt;
	}

	private static function assertStartBeforeOrEqualEnd(\DateTimeImmutable $start, \DateTimeImmutable $end): void
	{
		if ($start > $end) {
			do_action('qm/warning', 'Date range validation failed: {start_date} is after {end_date}', [
				'start_date' => $start->format('Y-m-d\TH:i'),
				'end_date' => $end->format('Y-m-d\TH:i'),
			]);
			throw new \InvalidArgumentException('start_date must be before or equal to end_date');
		}
	}

	/**
	 * @param array<string, mixed> $map
	 * @return array<string, mixed>
	 */
	private static function assertJsonEncodableMap(array $map, string $fieldName): array
	{
		foreach (array_keys($map) as $key) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(sprintf('%s must be an object-like associative array', $fieldName));
			}
		}
		$json = json_encode($map);
		if ($json === false) {
			throw new \InvalidArgumentException(sprintf('%s must be JSON encodable', $fieldName));
		}
		return $map;
	}

	// ===== Persistence Helpers =====
	private static function tableName(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'wrc_leases';
	}

	/** @param array<string,mixed> $meta */
	private static function encodeMeta(array $meta): string
	{
		$encoder = function_exists('wp_json_encode') ? 'wp_json_encode' : 'json_encode';
		$encoded = \call_user_func($encoder, $meta);
		return is_string($encoded) ? $encoded : '{}';
	}

	/** @return array<string,mixed> */
	private static function decodeMeta(?string $json): array
	{
		if ($json === null || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	/** Insert and return new ID */
	public static function create(Lease $lease): int
	{
		global $wpdb;
		
		// Start timing the database operation
		do_action('qm/start', 'wrc_lease_create');
		
		do_action('qm/debug', 'Creating new lease for product {product_id} with {quantity} units and {status} status for customer {customer_id} ({start_date} to {end_date})', [
			'product_id' => $lease->getProductId(),
			'customer_id' => $lease->getCustomerId(),
			'start_date' => $lease->getStartDate()->format('Y-m-d\TH:i'),
			'end_date' => $lease->getEndDate()->format('Y-m-d\TH:i'),
			'quantity' => $lease->getQuantity(),
			'status' => $lease->getStatus(),
			'request_id' => $lease->getRequestId(),
		]);
		
		$nowUtc = gmdate('Y-m-d H:i:s');
		$inserted = $wpdb->insert(
			self::tableName(),
			[
				'product_id' => $lease->getProductId(),
				'variation_id' => $lease->getVariationId(),
				'order_id' => $lease->getOrderId(),
				'order_item_id' => $lease->getOrderItemId(),
				'customer_id' => $lease->getCustomerId(),
				'request_id' => $lease->getRequestId(),
				'start_date' => $lease->getStartDate()->format('Y-m-d H:i:s'),
				'end_date' => $lease->getEndDate()->format('Y-m-d H:i:s'),
				'qty' => $lease->getQuantity(),
				'meta' => self::encodeMeta($lease->getMeta()),
				'status' => $lease->getStatus(),
				'created_at' => $nowUtc,
			],
			['%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%s','%s']
		);

		if ($inserted === false) {
			do_action('qm/error', 'Failed to insert lease: {wpdb_error}. Query: {wpdb_query}', [
				'wpdb_error' => $wpdb->last_error,
				'wpdb_query' => $wpdb->last_query,
			]);
			do_action('qm/stop', 'wrc_lease_create');
			throw new \RuntimeException('Failed to insert lease');
		}
		
		$newId = (int)$wpdb->insert_id;
		do_action('qm/info', 'Lease created successfully with ID {id}', ['id' => $newId]);
		do_action('qm/stop', 'wrc_lease_create');
		
		return $newId;
	}

	/** @return array<string,mixed>|null */
	public static function findByIdArray(int $id): ?array
	{
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::tableName() . ' WHERE id = %d';
		$row = $wpdb->get_row($wpdb->prepare($sql, [$id]));
		return $row ? self::mapRowToArray($row) : null;
	}

	/**
	 * @param array{status?:string,product_id?:int,customer_id?:int} $filters
	 * @return array<int, array<string,mixed>>
	 */
	public static function listAsArray(array $filters): array
	{
		global $wpdb;
		
		do_action('qm/start', 'wrc_lease_list');
		do_action('qm/debug', 'Listing leases with filters: {filters}', ['filters' => $filters]);
		
		$where = [];
		$args = [];
		if (!empty($filters['status'])) {
			$where[] = 'status = %s';
			$args[] = (string)$filters['status'];
		}
		if (!empty($filters['product_id'])) {
			$where[] = 'product_id = %d';
			$args[] = (int)$filters['product_id'];
		}
		if (!empty($filters['customer_id'])) {
			$where[] = 'customer_id = %d';
			$args[] = (int)$filters['customer_id'];
		}
		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

		$sql = 'SELECT * FROM ' . self::tableName() . ' ' . $whereSql . ' ORDER BY created_at DESC LIMIT 100';
		$rows = $args
			? $wpdb->get_results($wpdb->prepare($sql, $args))
			: $wpdb->get_results($sql);
		
		$items = array_map([self::class, 'mapRowToArray'], $rows ?: []);
		
		do_action('qm/info', 'Retrieved {items_returned} leases', ['items_returned' => count($items)]);
		do_action('qm/stop', 'wrc_lease_list');
		
		return $items;
	}

	public static function updateStatus(int $id, string $status): void
	{
		global $wpdb;
		
		do_action('qm/debug', 'Updating lease {id} status to {new_status}', [
			'id' => $id,
			'new_status' => $status,
		]);
		
		$result = $wpdb->update(
			self::tableName(),
			['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
			['id' => $id],
			['%s','%s'],
			['%d']
		);
		
		if ($result === false) {
			do_action('qm/error', 'Failed to update lease {id} status to {status}: {wpdb_error}', [
				'id' => $id,
				'status' => $status,
				'wpdb_error' => $wpdb->last_error,
			]);
		} else {
			do_action('qm/info', 'Lease {id} status updated to {status} ({rows_affected} rows affected)', [
				'id' => $id,
				'status' => $status,
				'rows_affected' => $result,
			]);
		}
	}

	public static function deleteById(int $id): bool
	{
		global $wpdb;
		
		do_action('qm/debug', 'Deleting lease {id}', ['id' => $id]);
		
		// Check if the record exists first
		$exists = self::findByIdArray($id);
		if (!$exists) {
			do_action('qm/warning', 'Attempted to delete non-existent lease {id}', ['id' => $id]);
			return false;
		}
		
		$result = $wpdb->delete(
			self::tableName(),
			['id' => $id],
			['%d']
		);
		
		if ($result === false) {
			do_action('qm/error', 'Failed to delete lease {id}: {wpdb_error}', [
				'id' => $id,
				'wpdb_error' => $wpdb->last_error,
			]);
			return false;
		} else {
			do_action('qm/info', 'Lease {id} deleted ({rows_affected} rows affected)', [
				'id' => $id,
				'rows_affected' => $result,
			]);
			return $result > 0;
		}
	}

	public static function deleteByProductId(int $productId): int
	{
		global $wpdb;
		
		do_action('qm/debug', 'Deleting all leases for product {product_id}', ['product_id' => $productId]);
		
		// Get count before deletion for logging
		$countSql = 'SELECT COUNT(*) FROM ' . self::tableName() . ' WHERE product_id = %d';
		$count = (int)$wpdb->get_var($wpdb->prepare($countSql, [$productId]));
		
		if ($count === 0) {
			do_action('qm/info', 'No leases found for product {product_id}', ['product_id' => $productId]);
			return 0;
		}
		
		$result = $wpdb->delete(
			self::tableName(),
			['product_id' => $productId],
			['%d']
		);
		
		if ($result === false) {
			do_action('qm/error', 'Failed to delete leases for product {product_id}: {wpdb_error}', [
				'product_id' => $productId,
				'wpdb_error' => $wpdb->last_error,
			]);
			return 0;
		} else {
			do_action('qm/info', 'Deleted {rows_affected} leases for product {product_id}', [
				'product_id' => $productId,
				'rows_affected' => $result,
			]);
			return (int)$result;
		}
	}

	/** Convert database datetime format to ISO format for API output */
	private static function formatDateTimeForOutput(string $dbDateTime): string
	{
		// Convert from database format (Y-m-d H:i:s) to ISO format (Y-m-d\TH:i)
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dbDateTime, new \DateTimeZone('UTC'));
		return $dt ? $dt->format('Y-m-d\TH:i') : $dbDateTime;
	}

	/** @return array<string,mixed> */
	public static function mapRowToArray(object $row): array
	{
		return [
			'id' => (int)$row->id,
			'product_id' => (int)$row->product_id,
			'variation_id' => $row->variation_id !== null ? (int)$row->variation_id : null,
			'order_id' => $row->order_id !== null ? (int)$row->order_id : null,
			'order_item_id' => $row->order_item_id !== null ? (int)$row->order_item_id : null,
			'customer_id' => (int)$row->customer_id,
			'request_id' => $row->request_id !== null ? (int)$row->request_id : null,
			'start_date' => self::formatDateTimeForOutput((string)$row->start_date),
			'end_date' => self::formatDateTimeForOutput((string)$row->end_date),
			'qty' => (int)$row->qty,
			'meta' => self::decodeMeta($row->meta ?? null),
			'status' => (string)$row->status,
			'created_at' => (string)$row->created_at,
			'updated_at' => $row->updated_at !== null ? (string)$row->updated_at : null,
		];
	}
}



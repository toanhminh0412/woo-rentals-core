<?php

declare(strict_types=1);

namespace WRC\Domain;

final class LeaseRequest
{
	public const STATUS_AWAITING_LESSEE_RESPONSE = "awaiting lessee's response";
	public const STATUS_AWAITING_LESSOR_RESPONSE = "awaiting lessor's response";
	public const STATUS_AWAITING_PAYMENT = "awaiting payment";
	public const STATUS_ACCEPTED = "accepted";
	public const STATUS_DECLINED = "declined";
	public const STATUS_CANCELLED = "cancelled";

	/** @var array<int, string> */
	private static array $allowedStatuses = [
		self::STATUS_AWAITING_LESSEE_RESPONSE,
		self::STATUS_AWAITING_LESSOR_RESPONSE,
		self::STATUS_AWAITING_PAYMENT,
		self::STATUS_ACCEPTED,
		self::STATUS_DECLINED,
		self::STATUS_CANCELLED
	];

	private ?int $id;
	private int $productId;
	private ?int $variationId;
	private int $requesterId;
	private \DateTimeImmutable $startDate;
	private \DateTimeImmutable $endDate;
	private int $quantity;
	private ?string $notes;
	/** @var array<string, mixed> */
	private array $meta;
	private string $status;
	private ?\DateTimeImmutable $createdAt;
	private ?\DateTimeImmutable $updatedAt;
	private int $totalPrice;
	private int $requestingVendorId;
	/**
	 * @param array<string, mixed> $meta
	 */
	public function __construct(
		?int $id,
		int $productId,
		?int $variationId,
		int $requesterId,
		string $startDate,
		string $endDate,
		int $quantity,
		string $status = self::STATUS_AWAITING_LESSOR_RESPONSE,
		?string $notes = null,
		int $totalPrice,
		int $requestingVendorId,
		array $meta = [],
		?\DateTimeImmutable $createdAt = null,
		?\DateTimeImmutable $updatedAt = null
	) {
		$this->id = $id;
		$this->productId = self::assertPositiveInt($productId, 'product_id');
		$this->variationId = $variationId !== null ? self::assertPositiveInt($variationId, 'variation_id') : null;
		$this->requesterId = self::assertPositiveInt($requesterId, 'requester_id');
		$this->quantity = self::assertMinInt($quantity, 1, 'qty');
		$this->notes = $notes;
		$this->meta = self::assertJsonEncodableMap($meta, 'meta');
		$this->status = self::assertAllowedStatus($status);
		$this->totalPrice = self::assertPositiveInt($totalPrice, 'total_price');
		$this->requestingVendorId = self::assertPositiveInt($requestingVendorId, 'requesting_vendor_id');
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
	public function getRequesterId(): int { return $this->requesterId; }
	public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
	public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
	public function getQuantity(): int { return $this->quantity; }
	public function getNotes(): ?string { return $this->notes; }
	/** @return array<string, mixed> */
	public function getMeta(): array { return $this->meta; }
	public function getStatus(): string { return $this->status; }
	public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
	public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
	public function getTotalPrice(): int { return $this->totalPrice; }
	public function getRequestingVendorId(): int { return $this->requestingVendorId; }

	private static function assertPositiveInt(int $value, string $fieldName): int
	{
		if ($value <= 0) {
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
					throw new \InvalidArgumentException(sprintf('%s must be in YYYY-MM-DDTHH:MM, YYYY-MM-DD HH:MM:SS, or YYYY-MM-DD format', $fieldName));
				}
			}
		}
		return $dt;
	}

	private static function assertStartBeforeOrEqualEnd(\DateTimeImmutable $start, \DateTimeImmutable $end): void
	{
		if ($start > $end) {
			throw new \InvalidArgumentException('start_date must be before or equal to end_date');
		}
	}

	/**
	 * @param array<string, mixed> $map
	 * @return array<string, mixed>
	 */
	private static function assertJsonEncodableMap(array $map, string $fieldName): array
	{
		// Ensure keys are strings and the structure is JSON encodable
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
		return $wpdb->prefix . 'wrc_lease_requests';
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
	public static function create(LeaseRequest $request): int
	{
		global $wpdb;
		

		
		$nowUtc = gmdate('Y-m-d H:i:s');
		$inserted = $wpdb->insert(
			self::tableName(),
			[
				'product_id' => $request->getProductId(),
				'variation_id' => $request->getVariationId(),
				'requester_id' => $request->getRequesterId(),
				'start_date' => $request->getStartDate()->format('Y-m-d H:i:s'),
				'end_date' => $request->getEndDate()->format('Y-m-d H:i:s'),
				'qty' => $request->getQuantity(),
				'notes' => $request->getNotes(),
				'meta' => self::encodeMeta($request->getMeta()),
				'status' => $request->getStatus(),
				'total_price' => $request->getTotalPrice(),
				'requesting_vendor_id' => $request->getRequestingVendorId(),
				'created_at' => $nowUtc,
			],
			['%d','%d','%d','%s','%s','%d','%s','%s','%s','%d','%d','%s']
		);
		if ($inserted === false) {
			throw new \RuntimeException('Failed to insert lease request');
		}
		
		$newId = (int)$wpdb->insert_id;

        // Create a history record for the request
        $historyEntity = new LeaseRequestHistory(
            null,
            $newId,
            []
        );
        LeaseRequestHistory::create($historyEntity);

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
	 * @param array{status?:string,product_id?:int,requester_id?:int} $filters
	 * @return array{items: array<int, array<string,mixed>>, total: int, page: int, per_page: int}
	 */
	public static function listAsArray(array $filters, int $page, int $perPage): array
	{
		global $wpdb;
		

		
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
		if (!empty($filters['requester_id'])) {
			$where[] = 'requester_id = %d';
			$args[] = (int)$filters['requester_id'];
		}
		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

		$page = max(1, $page);
		$perPage = $perPage > 0 ? $perPage : 20;
		$offset = ($page - 1) * $perPage;

		$totalSql = 'SELECT COUNT(*) FROM ' . self::tableName() . ' ' . $whereSql;
		$total = (int)($args
			? $wpdb->get_var($wpdb->prepare($totalSql, $args))
			: $wpdb->get_var($totalSql)
		);

		$listSql = 'SELECT * FROM ' . self::tableName() . ' ' . $whereSql . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$listArgs = array_merge($args, [$perPage, $offset]);
		$rows = $wpdb->get_results($wpdb->prepare($listSql, $listArgs));
		$items = array_map([self::class, 'mapRowToArray'], $rows ?: []);

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'per_page' => $perPage,
		];
	}



	public static function deleteById(int $id): bool
	{
		global $wpdb;
		

		
		// Check if the record exists first
		$exists = self::findByIdArray($id);
		if (!$exists) {
			return false;
		}
		
		$result = $wpdb->delete(
			self::tableName(),
			['id' => $id],
			['%d']
		);
		
		if ($result === false) {
			return false;
		} else {
			return $result > 0;
		}
	}

	public static function deleteByProductId(int $productId): int
	{
		global $wpdb;
		

		
		// Get count before deletion for logging
		$countSql = 'SELECT COUNT(*) FROM ' . self::tableName() . ' WHERE product_id = %d';
		$count = (int)$wpdb->get_var($wpdb->prepare($countSql, [$productId]));
		
		if ($count === 0) {
			return 0;
		}
		
		$result = $wpdb->delete(
			self::tableName(),
			['product_id' => $productId],
			['%d']
		);
		
		if ($result === false) {
			return 0;
		} else {
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

	/**
	 * Update selected fields of a lease request.
	 *
	 * @param array{start_date?:string,end_date?:string,qty?:int,notes?:string|null,meta?:array,variation_id?:int|null,status?:string,total_price?:int,requesting_vendor_id?:int} $fields
	 */
	public static function updateFields(int $id, array $fields): void
	{
		global $wpdb;

		$existing = self::findByIdArray($id);
		if ($existing === null) {
			throw new \RuntimeException('Lease request not found');
		}

		$tz = new \DateTimeZone('UTC');
		$startInput = array_key_exists('start_date', $fields) ? (string)$fields['start_date'] : (string)$existing['start_date'];
		$endInput = array_key_exists('end_date', $fields) ? (string)$fields['end_date'] : (string)$existing['end_date'];
		$qtyInput = array_key_exists('qty', $fields) ? (int)$fields['qty'] : (int)$existing['qty'];
		$notesInput = array_key_exists('notes', $fields) ? ($fields['notes'] !== null ? (string)$fields['notes'] : null) : ($existing['notes'] !== null ? (string)$existing['notes'] : null);
		$metaInput = array_key_exists('meta', $fields) ? (is_array($fields['meta']) ? $fields['meta'] : []) : (is_array($existing['meta']) ? $existing['meta'] : []);
		$variationInput = array_key_exists('variation_id', $fields)
			? ($fields['variation_id'] !== null ? (int)$fields['variation_id'] : null)
			: ($existing['variation_id'] !== null ? (int)$existing['variation_id'] : null);
		$statusInput = array_key_exists('status', $fields) ? (string)$fields['status'] : (string)$existing['status'];

		// Validate using domain validators
		$start = self::assertDateYmd($startInput, 'start_date', $tz);
		$end = self::assertDateYmd($endInput, 'end_date', $tz);
		self::assertStartBeforeOrEqualEnd($start, $end);
		$qty = self::assertMinInt($qtyInput, 1, 'qty');
		if ($variationInput !== null) {
			$variationInput = self::assertPositiveInt($variationInput, 'variation_id');
		}
		$meta = self::assertJsonEncodableMap($metaInput, 'meta');
		$status = self::assertAllowedStatus($statusInput);
		$totalPriceInput = array_key_exists('total_price', $fields) ? (int)$fields['total_price'] : (int)$existing['total_price'];
        $requestingVendorIdInput = array_key_exists('requesting_vendor_id', $fields) ? (int)$fields['requesting_vendor_id'] : (int)$existing['requesting_vendor_id'];


		$data = [
			'start_date' => $start->format('Y-m-d H:i:s'),
			'end_date' => $end->format('Y-m-d H:i:s'),
			'qty' => $qty,
			'notes' => $notesInput,
			'meta' => self::encodeMeta($meta),
			// Only include variation_id if provided explicitly; null unsets
			'variation_id' => $variationInput,
			'status' => $status,
			'total_price' => $totalPriceInput,
			'requesting_vendor_id' => $requestingVendorIdInput,
			'updated_at' => gmdate('Y-m-d H:i:s'),
		];

		// Only update columns that were actually provided, plus updated_at
		$providedCols = [];
		$formats = [];
		$map = [
			'start_date' => '%s',
			'end_date' => '%s',
			'qty' => '%d',
			'notes' => '%s',
			'meta' => '%s',
			'variation_id' => '%d',
			'status' => '%s',
			'total_price' => '%d',
			'requesting_vendor_id' => '%d',
			'updated_at' => '%s',
		];
		foreach (['start_date','end_date','qty','notes','meta','variation_id','status','total_price','requesting_vendor_id'] as $col) {
			if (!array_key_exists($col, $fields)) {
				continue;
			}
			// Skip updating variation_id when explicitly set to null to avoid forcing 0
			if ($col === 'variation_id' && $fields['variation_id'] === null) {
				continue;
			}
			$providedCols[$col] = $data[$col];
			$formats[] = $map[$col];
		}
		// Always include updated_at
		$providedCols['updated_at'] = $data['updated_at'];
		$formats[] = '%s';

		// Save snapshot to history if status is being updated
		if (array_key_exists('status', $fields) && $fields['status'] !== $existing['status']) {
			try {
                $historyEntity = LeaseRequestHistory::findByRequestIdArray($id);
                if (!is_array($historyEntity) || empty($historyEntity)) {
                    $historyEntity = new LeaseRequestHistory(
                        null,
                        $id,
                        [$existing]
                    );
                    LeaseRequestHistory::create($historyEntity);
                } else {
                    LeaseRequestHistory::addRequestToHistory($historyEntity[0]['id'], $existing);
                }
			} catch (\Exception $e) {
				// Don't fail the update if history save fails, just log the warning
			}
		}

		$result = $wpdb->update(
			self::tableName(),
			$providedCols,
			['id' => $id],
			$formats,
			['%d']
		);

		if ($result === false) {
			throw new \RuntimeException('Failed to update lease request');
		}


	}

	/** @return array<string,mixed> */
	public static function mapRowToArray(object $row): array
	{
		return [
			'id' => (int)$row->id,
			'product_id' => (int)$row->product_id,
			'variation_id' => $row->variation_id !== null ? (int)$row->variation_id : null,
			'requester_id' => (int)$row->requester_id,
			'start_date' => self::formatDateTimeForOutput((string)$row->start_date),
			'end_date' => self::formatDateTimeForOutput((string)$row->end_date),
			'qty' => (int)$row->qty,
			'notes' => $row->notes !== null ? (string)$row->notes : null,
			'meta' => self::decodeMeta($row->meta ?? null),
			'status' => (string)$row->status,
			'total_price' => (int)$row->total_price,
			'requesting_vendor_id' => (int)$row->requesting_vendor_id,
			'created_at' => (string)$row->created_at,
			'updated_at' => $row->updated_at !== null ? (string)$row->updated_at : null,
		];
	}
}



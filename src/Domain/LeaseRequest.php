<?php

declare(strict_types=1);

namespace WRC\Domain;

final class LeaseRequest
{
	public const STATUS_PENDING = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_DECLINED = 'declined';
	public const STATUS_CANCELLED = 'cancelled';

	/** @var array<int, string> */
	private static array $allowedStatuses = [
		self::STATUS_PENDING,
		self::STATUS_APPROVED,
		self::STATUS_DECLINED,
		self::STATUS_CANCELLED,
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
		string $status = self::STATUS_PENDING,
		?string $notes = null,
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

	private static function assertDateYmd(string $date, string $fieldName, \DateTimeZone $tz): \DateTimeImmutable
	{
		$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
		if ($dt === false || $dt->format('Y-m-d') !== $date) {
			throw new \InvalidArgumentException(sprintf('%s must be in YYYY-MM-DD format', $fieldName));
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
				'start_date' => $request->getStartDate()->format('Y-m-d'),
				'end_date' => $request->getEndDate()->format('Y-m-d'),
				'qty' => $request->getQuantity(),
				'notes' => $request->getNotes(),
				'meta' => self::encodeMeta($request->getMeta()),
				'status' => $request->getStatus(),
				'created_at' => $nowUtc,
			],
			['%d','%d','%d','%s','%s','%d','%s','%s','%s','%s']
		);
		if ($inserted === false) {
			throw new \RuntimeException('Failed to insert lease request');
		}
		return (int)$wpdb->insert_id;
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

	public static function updateStatus(int $id, string $status): void
	{
		global $wpdb;
		$wpdb->update(
			self::tableName(),
			['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
			['id' => $id],
			['%s','%s'],
			['%d']
		);
	}

	/** @return array<string,mixed> */
	public static function mapRowToArray(object $row): array
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
			'meta' => self::decodeMeta($row->meta ?? null),
			'status' => (string)$row->status,
			'created_at' => (string)$row->created_at,
			'updated_at' => $row->updated_at !== null ? (string)$row->updated_at : null,
		];
	}
}



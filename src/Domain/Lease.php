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
}



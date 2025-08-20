<?php

declare(strict_types=1);

namespace WRC\Domain;

final class LeaseRequestHistory
{
	private ?int $id;
	private int $requestId;
	/** @var array<int, array<string, mixed>> */
	private array $history;

	/**
	 * @param array<int, array<string, mixed>> $history
	 */
	public function __construct(
		?int $id,
		int $requestId,
		array $history
	) {
		$this->id = $id;
		$this->requestId = self::assertPositiveInt($requestId, 'request_id');
		$this->history = self::assertJsonEncodableArray($history, 'history');
	}

	public function getId(): ?int { return $this->id; }
	public function getRequestId(): int { return $this->requestId; }
	/** @return array<int, array<string, mixed>> */
	public function getHistory(): array { return $this->history; }

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

	/**
	 * @param array<int, array<string, mixed>> $array
	 * @return array<int, array<string, mixed>>
	 */
	private static function assertJsonEncodableArray(array $array, string $fieldName): array
	{
		// Validate that it's an array of arrays
		foreach ($array as $index => $item) {
			if (!is_array($item)) {
				throw new \InvalidArgumentException(sprintf('%s must be an array of arrays', $fieldName));
			}
		}
		
		$json = json_encode($array);
		if ($json === false) {
			throw new \InvalidArgumentException(sprintf('%s must be JSON encodable', $fieldName));
		}
		return $array;
	}

	// ===== Persistence Helpers =====
	private static function tableName(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'wrc_lease_request_history';
	}

	/** @param array<int, array<string,mixed>> $history */
	private static function encodeHistory(array $history): string
	{
		$encoder = function_exists('wp_json_encode') ? 'wp_json_encode' : 'json_encode';
		$encoded = \call_user_func($encoder, $history);
		return is_string($encoded) ? $encoded : '[]';
	}

	/** @return array<int, array<string,mixed>> */
	private static function decodeHistory(?string $json): array
	{
		if ($json === null || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		// Ensure it's an array of arrays
		foreach ($decoded as $item) {
			if (!is_array($item)) {
				return [];
			}
		}
		return $decoded;
	}

	/** Insert and return new ID */
	public static function create(LeaseRequestHistory $history): int
	{
		global $wpdb;
		
		do_action('qm/start', 'wrc_lease_request_history_create');
		
		do_action('qm/debug', 'Creating new lease request history for request {request_id}', [
			'request_id' => $history->getRequestId(),
		]);
		
		$inserted = $wpdb->insert(
			self::tableName(),
			[
				'request_id' => $history->getRequestId(),
				'history' => self::encodeHistory($history->getHistory()),
				'created_at' => gmdate('Y-m-d H:i:s'),
			],
			['%d', '%s', '%s']
		);
		
		if ($inserted === false) {
			do_action('qm/error', 'Failed to insert lease request history: {wpdb_error}. Query: {wpdb_query}', [
				'wpdb_error' => $wpdb->last_error,
				'wpdb_query' => $wpdb->last_query,
			]);
			do_action('qm/stop', 'wrc_lease_request_history_create');
			throw new \RuntimeException('Failed to insert lease request history');
		}
		
		$newId = (int)$wpdb->insert_id;
		do_action('qm/info', 'Lease request history created successfully with ID {id}', ['id' => $newId]);
		do_action('qm/stop', 'wrc_lease_request_history_create');
		
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

	/** @return array<int, array<string,mixed>> */
	public static function findByRequestIdArray(int $requestId): array
	{
		global $wpdb;
		
		do_action('qm/debug', 'Finding lease request history for request {request_id}', [
			'request_id' => $requestId,
		]);
		
		$sql = 'SELECT * FROM ' . self::tableName() . ' WHERE request_id = %d ORDER BY created_at DESC';
		$rows = $wpdb->get_results($wpdb->prepare($sql, [$requestId]));
		
		return array_map([self::class, 'mapRowToArray'], $rows ?: []);
	}

	public static function deleteById(int $id): bool
	{
		global $wpdb;
		
		do_action('qm/debug', 'Deleting lease request history {id}', ['id' => $id]);
		
		$result = $wpdb->delete(
			self::tableName(),
			['id' => $id],
			['%d']
		);
		
		if ($result === false) {
			do_action('qm/error', 'Failed to delete lease request history {id}: {wpdb_error}', [
				'id' => $id,
				'wpdb_error' => $wpdb->last_error,
			]);
			return false;
		} else {
			do_action('qm/info', 'Lease request history {id} deleted ({rows_affected} rows affected)', [
				'id' => $id,
				'rows_affected' => $result,
			]);
			return $result > 0;
		}
	}

	public static function deleteByRequestId(int $requestId): int
	{
		global $wpdb;
		
		do_action('qm/debug', 'Deleting all lease request history for request {request_id}', ['request_id' => $requestId]);
		
		$result = $wpdb->delete(
			self::tableName(),
			['request_id' => $requestId],
			['%d']
		);
		
		if ($result === false) {
			do_action('qm/error', 'Failed to delete lease request history for request {request_id}: {wpdb_error}', [
				'request_id' => $requestId,
				'wpdb_error' => $wpdb->last_error,
			]);
			return 0;
		} else {
			do_action('qm/info', 'Deleted {rows_affected} lease request history records for request {request_id}', [
				'request_id' => $requestId,
				'rows_affected' => $result,
			]);
			return (int)$result;
		}
	}

    public static function updateFields(int $id, array $fields): void
    {
        global $wpdb;
        
        $existing = self::findByIdArray($id);
        if ($existing === null) {
            throw new \RuntimeException('Lease request history not found');
        }

        $requestId = array_key_exists('request_id', $fields) ? (int)$fields['request_id'] : (int)$existing['request_id'];
        $history = array_key_exists('history', $fields) ? $fields['history'] : $existing['history'];
        
        $requestId = self::assertPositiveInt($requestId, 'request_id');
        $history = self::assertJsonEncodableArray($history, 'history');

        $providedCols = [];
        $formats = [];
        $map = [
            'request_id' => '%d',
            'history' => '%s',
        ];
        foreach (['request_id', 'history'] as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $providedCols[$col] = $fields[$col];
            $formats[] = $map[$col];
        }

        $wpdb->update(self::tableName(), $providedCols, ['id' => $id], $formats, ['%d']);
    }

    public static function addRequestToHistory(int $requestHistoryId, array $request)
    {
        $requestHistory = self::findByIdArray($requestHistoryId);
        if ($requestHistory === null) {
            throw new \RuntimeException('Request history not found');
        }
        $history = self::decodeHistory($requestHistory['history']);
        $history[] = $request;
        self::updateFields($requestHistoryId, [
            'history' => $history
        ]);
    }

	/** @return array<string,mixed> */
	public static function mapRowToArray(object $row): array
	{
		return [
			'id' => (int)$row->id,
			'request_id' => (int)$row->request_id,
			'history' => self::decodeHistory($row->history ?? null),
			'created_at' => (string)$row->created_at,
		];
	}
}

<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WRC_Lease_Requests_List_Table extends WP_List_Table
{
	/** @var array<int, array<string, mixed>> */
	private array $itemsData = [];

	public function __construct()
	{
		parent::__construct([
			'singular' => 'lease_request',
			'plural' => 'lease_requests',
			'ajax' => false,
		]);
	}

	public function get_columns(): array
	{
		return [
			'id' => __('ID', 'woo-rentals-core'),
			'product' => __('Product', 'woo-rentals-core'),
			'requester' => __('Requester', 'woo-rentals-core'),
			'period' => __('Period', 'woo-rentals-core'),
			'qty' => __('Qty', 'woo-rentals-core'),
			'status' => __('Status', 'woo-rentals-core'),
			'created_at' => __('Created', 'woo-rentals-core'),
		];
	}

	public function get_sortable_columns(): array
	{
		return [
			'id' => ['id', false],
			'created_at' => ['created_at', false],
		];
	}

	protected function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'id':
				$view_url = add_query_arg([
					'page' => 'wrc_rentals_requests',
					'action' => 'view',
					'id' => (int)$item['id']
				], admin_url('admin.php'));
				
				$actions = [
					'view' => sprintf('<a href="%s">%s</a>', esc_url($view_url), esc_html__('View', 'woo-rentals-core')),
					'approve' => sprintf('<a href="#" data-action="approve" data-id="%d">%s</a>', (int)$item['id'], esc_html__('Approve', 'woo-rentals-core')),
					'decline' => sprintf('<a href="#" data-action="decline" data-id="%d">%s</a>', (int)$item['id'], esc_html__('Decline', 'woo-rentals-core')),
					'cancel' => sprintf('<a href="#" data-action="cancel" data-id="%d">%s</a>', (int)$item['id'], esc_html__('Cancel', 'woo-rentals-core')),
				];
				return sprintf('%d %s', (int)$item['id'], $this->row_actions($actions));
			case 'product':
				$productId = (int)$item['product_id'];
				$title = $productId ? get_the_title($productId) : '';
				return $title !== '' ? sprintf('%s (ID %d)', esc_html($title), $productId) : sprintf('ID %d', $productId);
			case 'requester':
				$userId = (int)$item['requester_id'];
				$user = $userId ? get_userdata($userId) : false;
				return $user ? sprintf('%s (ID %d)', esc_html($user->display_name), $userId) : sprintf('ID %d', $userId);
			case 'period':
				return sprintf('%s → %s', esc_html($item['start_date']), esc_html($item['end_date']));
			case 'qty':
				return (int)$item['qty'];
			case 'status':
				return esc_html((string)$item['status']);
			case 'created_at':
				return esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string)$item['created_at'], true));
			default:
				return '';
		}
	}

	public function prepare_items(): void
	{
		$perPage = 20;
		$page = isset($_REQUEST['paged']) ? max(1, (int)$_REQUEST['paged']) : 1;

		$status = isset($_REQUEST['status']) ? sanitize_text_field((string)$_REQUEST['status']) : '';
		$productId = isset($_REQUEST['product_id']) ? absint((string)$_REQUEST['product_id']) : 0;

		$result = \WRC\Domain\LeaseRequest::listAsArray([
			'status' => $status ?: null,
			'product_id' => $productId ?: null,
		], $page, $perPage);

		$this->itemsData = $result['items'];
		$this->items = $this->itemsData;

		$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
		$this->set_pagination_args([
			'total_items' => (int)$result['total'],
			'per_page' => $perPage,
			'total_pages' => (int)ceil(((int)$result['total']) / $perPage),
		]);
	}

	protected function extra_tablenav($which)
	{
		if ($which !== 'top') {
			return;
		}
		$status = isset($_REQUEST['status']) ? sanitize_text_field((string)$_REQUEST['status']) : '';
		$productId = isset($_REQUEST['product_id']) ? absint((string)$_REQUEST['product_id']) : 0;
		?>
		<div class="alignleft actions">
			<label for="wrc_status" class="screen-reader-text"><?php esc_html_e('Filter by status', 'woo-rentals-core'); ?></label>
			<select id="wrc_status" name="status">
				<option value=""><?php esc_html_e('All statuses', 'woo-rentals-core'); ?></option>
				<?php foreach ([
					'pending' => __('Pending', 'woo-rentals-core'),
					'approved' => __('Approved', 'woo-rentals-core'),
					'declined' => __('Declined', 'woo-rentals-core'),
					'cancelled' => __('Cancelled', 'woo-rentals-core'),
				] as $value => $label): ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="wrc_product" class="screen-reader-text"><?php esc_html_e('Filter by product ID', 'woo-rentals-core'); ?></label>
			<input type="number" id="wrc_product" name="product_id" value="<?php echo esc_attr((string)$productId); ?>" placeholder="<?php esc_attr_e('Product ID', 'woo-rentals-core'); ?>" />

			<?php submit_button(__('Filter'), 'secondary', 'filter_action', false); ?>
		</div>
		<?php
	}
}

$list_table = new WRC_Lease_Requests_List_Table();
$list_table->prepare_items();
?>
<div class="wrap">
	<h1><?php esc_html_e('Lease Requests', 'woo-rentals-core'); ?></h1>
	<form method="get">
		<input type="hidden" name="page" value="wrc_rentals_requests" />
		<?php $list_table->display(); ?>
	</form>
</div>





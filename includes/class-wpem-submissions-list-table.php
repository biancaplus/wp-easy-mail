<?php
/**
 * 后台提交记录列表。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 渲染提交记录列表。
 */
class WPEM_Submissions_List_Table extends WP_List_Table {

	/**
	 * 初始化列表。
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wpem_submission',
				'plural'   => 'wpem_submissions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * 返回列表列。
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'wp-easy-mail' ),
			'form_type'    => __( '表单类型', 'wp-easy-mail' ),
			'form'         => __( '表单', 'wp-easy-mail' ),
			'name'         => __( '姓名', 'wp-easy-mail' ),
			'email'        => __( '邮箱', 'wp-easy-mail' ),
			'status'       => __( '状态', 'wp-easy-mail' ),
			'email_status' => __( '邮件', 'wp-easy-mail' ),
			'submitted_at' => __( '提交时间', 'wp-easy-mail' ),
		);
	}

	/**
	 * 返回可排序列。
	 *
	 * @return array<string, array<int, mixed>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'           => array( 'id', true ),
			'submitted_at' => array( 'submitted_at', true ),
		);
	}

	/**
	 * 准备列表数据。
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$table       = $wpdb->prefix . 'wp_easy_mail_submissions';
		$per_page    = 20;
		$current     = max( 1, $this->get_pagenum() );
		$offset      = ( $current - 1 ) * $per_page;
		$where       = array( '1=1' );
		$params      = array();
		$form_type   = isset( $_GET['form_type'] ) ? sanitize_key( wp_unslash( $_GET['form_type'] ) ) : '';
		$form_id     = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$status      = isset( $_GET['submission_status'] ) ? sanitize_key( wp_unslash( $_GET['submission_status'] ) ) : '';
		$orderby_key = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'submitted_at';
		$order       = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		$orderby     = in_array( $orderby_key, array( 'id', 'submitted_at' ), true ) ? $orderby_key : 'submitted_at';

		if ( WPEM_Form_Types::is_valid_type( $form_type ) ) {
			$where[]  = 'form_type = %s';
			$params[] = $form_type;
		}
		if ( $form_id ) {
			$where[]  = 'form_id = %d';
			$params[] = $form_id;
		}
		if ( in_array( $status, array( 'read', 'unread' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$data_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$total_items = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: (int) $wpdb->get_var( $count_sql );

		$data_params   = array_merge( $params, array( $per_page, $offset ) );
		$this->items   = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * 输出无记录提示。
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( '暂无提交记录。', 'wp-easy-mail' );
	}

	/**
	 * 输出默认列。
	 *
	 * @param array<string, mixed> $item   当前记录。
	 * @param string               $column 列名。
	 * @return string
	 */
	public function column_default( $item, $column ) {
		$fields = json_decode( $item['field_data'], true );
		$fields = is_array( $fields ) ? $fields : array();

		switch ( $column ) {
			case 'id':
				$view_url = add_query_arg(
					array(
						'page'          => 'wpem-submissions',
						'submission_id' => absint( $item['id'] ),
					),
					admin_url( 'admin.php' )
				);
				$actions  = array(
					'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( '查看', 'wp-easy-mail' ) ),
				);
				if ( 'unread' === $item['status'] ) {
					$actions['read'] = $this->get_action_link( $item['id'], 'read', __( '标为已读', 'wp-easy-mail' ) );
				}
				$actions['delete'] = $this->get_action_link( $item['id'], 'delete', __( '删除', 'wp-easy-mail' ), true );
				return '<strong>' . absint( $item['id'] ) . '</strong>' . $this->row_actions( $actions );
			case 'form_type':
				$type_class = 'get_quote' === $item['form_type'] ? 'wpem-tag-quote' : 'wpem-tag-contact';
				return sprintf(
					'<span class="wpem-type-tag %s">%s</span>',
					esc_attr( $type_class ),
					esc_html( WPEM_Form_Types::get_type_label( $item['form_type'] ) )
				);
			case 'form':
				return esc_html( get_the_title( (int) $item['form_id'] ) ?: __( '已删除的表单', 'wp-easy-mail' ) );
			case 'name':
			case 'email':
				return isset( $fields[ $column ] ) ? esc_html( $fields[ $column ] ) : '—';
			case 'status':
				return 'unread' === $item['status'] ? esc_html__( '未读', 'wp-easy-mail' ) : esc_html__( '已读', 'wp-easy-mail' );
			case 'email_status':
				return esc_html( $this->get_email_status_label( $item['email_status'] ) );
			case 'submitted_at':
				return esc_html( $item['submitted_at'] );
			default:
				return '';
		}
	}

	/**
	 * 输出筛选控件。
	 *
	 * @param string $which 表格位置。
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_type = isset( $_GET['form_type'] ) ? sanitize_key( wp_unslash( $_GET['form_type'] ) ) : '';
		$current_form = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$current_status = isset( $_GET['submission_status'] ) ? sanitize_key( wp_unslash( $_GET['submission_status'] ) ) : '';
		$forms = get_posts(
			array(
				'post_type'      => 'wpem_form',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="alignleft actions">
			<select name="form_type">
				<option value=""><?php esc_html_e( '全部表单类型', 'wp-easy-mail' ); ?></option>
				<?php foreach ( WPEM_Form_Types::get_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="form_id">
				<option value=""><?php esc_html_e( '全部表单', 'wp-easy-mail' ); ?></option>
				<?php foreach ( $forms as $form ) : ?>
					<option value="<?php echo esc_attr( $form->ID ); ?>" <?php selected( $current_form, $form->ID ); ?>><?php echo esc_html( $form->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="submission_status">
				<option value=""><?php esc_html_e( '全部状态', 'wp-easy-mail' ); ?></option>
				<option value="unread" <?php selected( $current_status, 'unread' ); ?>><?php esc_html_e( '未读', 'wp-easy-mail' ); ?></option>
				<option value="read" <?php selected( $current_status, 'read' ); ?>><?php esc_html_e( '已读', 'wp-easy-mail' ); ?></option>
			</select>
			<?php submit_button( __( '筛选', 'wp-easy-mail' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * 构建记录操作链接。
	 *
	 * @param int    $id        提交 ID。
	 * @param string $operation 操作。
	 * @param string $label     链接文字。
	 * @param bool   $danger    是否危险操作。
	 * @return string
	 */
	private function get_action_link( $id, $operation, $label, $danger = false ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'wpem_submission_action',
					'operation'   => $operation,
					'submission'  => absint( $id ),
				),
				admin_url( 'admin-post.php' )
			),
			'wpem_submission_' . absint( $id )
		);
		$class = $danger ? ' class="wpem-delete-link"' : '';
		return sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, esc_html( $label ) );
	}

	/**
	 * 获取邮件状态名称。
	 *
	 * @param string $status 状态值。
	 * @return string
	 */
	private function get_email_status_label( $status ) {
		$labels = array(
			'pending' => __( '等待发送', 'wp-easy-mail' ),
			'sending' => __( '发送中', 'wp-easy-mail' ),
			'sent'    => __( '已发送', 'wp-easy-mail' ),
			'failed'  => __( '发送失败', 'wp-easy-mail' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( '未知', 'wp-easy-mail' );
	}
}

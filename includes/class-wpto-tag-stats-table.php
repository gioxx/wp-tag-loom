<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPTO_Tag_Stats_Table extends WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'tag',
				'plural'   => 'tags',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'    => '<input type="checkbox" />',
			'name'  => __( 'Name', 'smart-tags-optimizer' ),
			'slug'  => __( 'Slug', 'smart-tags-optimizer' ),
			'count' => __( 'Assigned posts', 'smart-tags-optimizer' ),
			'lock'  => __( 'Lock', 'smart-tags-optimizer' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'  => array( 'name', false ),
			'count' => array( 'count', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'add_to_merge' => __( 'Add to merge selection', 'smart-tags-optimizer' ),
			'lock'         => __( 'Lock', 'smart-tags-optimizer' ),
			'unlock'       => __( 'Unlock', 'smart-tags-optimizer' ),
			'delete'       => __( 'Delete', 'smart-tags-optimizer' ),
		);
	}

	public function no_items() {
		esc_html_e( 'No tags found.', 'smart-tags-optimizer' );
	}

	public function column_cb( $item ) {
		// Locked tags stay selectable (checkbox always rendered) so they can be
		// picked for the "Unlock" bulk action; destructive bulk actions (delete,
		// add to merge) still drop locked ids server-side via
		// WPTO_Tag_Lock::filter_unlocked(). Lock state itself is shown in the
		// "Lock" column, not duplicated here.
		return sprintf( '<input type="checkbox" name="tag_id[]" value="%d" />', $item['id'] );
	}

	public function column_lock( $item ) {
		$locked = WPTO_Tag_Lock::is_locked( $item['id'] );

		$toggle_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'             => WPTO_Admin_Page::MAIN_SLUG,
					'tab'              => 'stats',
					'wpto_toggle_lock' => $item['id'],
				),
				admin_url( 'edit.php' )
			),
			'wpto_toggle_lock_' . $item['id']
		);

		$label = $locked
			? __( 'Unlock this tag', 'smart-tags-optimizer' )
			: __( 'Lock this tag (protects it from merging and deletion)', 'smart-tags-optimizer' );

		return sprintf(
			'<a href="%s" class="wpto-toggle-lock %s" data-id="%d" aria-label="%s" title="%s"><span class="dashicons %s"></span></a>',
			esc_url( $toggle_url ),
			$locked ? 'wpto-toggle-lock--locked' : 'wpto-toggle-lock--unlocked',
			$item['id'],
			esc_attr( $label ),
			esc_attr( $label ),
			$locked ? 'dashicons-lock' : 'dashicons-unlock'
		);
	}

	public function column_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'action'    => 'edit',
				'taxonomy'  => 'post_tag',
				'tag_ID'    => $item['id'],
				'post_type' => 'post',
			),
			admin_url( 'edit-tags.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'            => WPTO_Admin_Page::MAIN_SLUG,
					'tab'             => 'stats',
					'wpto_delete_tag' => $item['id'],
				),
				admin_url( 'edit.php' )
			),
			'wpto_delete_tag_' . $item['id']
		);

		$row_actions = array(
			'quick_edit' => sprintf(
				'<a href="#" class="wpto-quick-edit" data-id="%d" data-name="%s" data-slug="%s">%s</a>',
				$item['id'],
				esc_attr( $item['name'] ),
				esc_attr( $item['slug'] ),
				esc_html__( 'Quick Edit', 'smart-tags-optimizer' )
			),
			'edit'       => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'smart-tags-optimizer' ) ),
		);

		if ( ! WPTO_Tag_Lock::is_locked( $item['id'] ) ) {
			// No inline onclick confirm here: the "wpto-delete-tag" JS handler
			// confirms and deletes via Ajax, falling back to this plain nonce'd
			// link (no confirmation) if JS is unavailable.
			$row_actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete wpto-delete-tag" data-id="%d">%s</a>',
				esc_url( $delete_url ),
				$item['id'],
				esc_html__( 'Delete', 'smart-tags-optimizer' )
			);
		}

		return sprintf(
			'<a href="%s"><strong>%s</strong></a>%s',
			esc_url( $edit_url ),
			esc_html( $item['name'] ),
			$this->row_actions( $row_actions )
		);
	}

	public function column_slug( $item ) {
		return esc_html( $item['slug'] );
	}

	public function column_count( $item ) {
		$posts_url = add_query_arg( array( 'tag' => $item['slug'] ), admin_url( 'edit.php' ) );

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $posts_url ),
			esc_html( number_format_i18n( $item['count'] ) )
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	public function single_row( $item ) {
		printf( '<tr id="wpto-tag-row-%d">', (int) $item['id'] );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only search/sort on an admin list table, no state change.
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$user_id = get_current_user_id();

		if ( isset( $_REQUEST['orderby'] ) || isset( $_REQUEST['order'] ) ) {
			$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'count';
			$order   = isset( $_REQUEST['order'] ) ? strtolower( sanitize_key( $_REQUEST['order'] ) ) : 'desc';
			// phpcs:enable

			if ( ! in_array( $orderby, array( 'name', 'count' ), true ) ) {
				$orderby = 'count';
			}
			if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
				$order = 'desc';
			}

			if ( $user_id ) {
				update_user_meta( $user_id, 'wpto_tags_orderby', $orderby );
				update_user_meta( $user_id, 'wpto_tags_order', $order );
			}
		} else {
			$orderby = $user_id ? get_user_meta( $user_id, 'wpto_tags_orderby', true ) : '';
			$order   = $user_id ? get_user_meta( $user_id, 'wpto_tags_order', true ) : '';

			if ( ! in_array( $orderby, array( 'name', 'count' ), true ) ) {
				$orderby = 'count';
			}
			if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
				$order = 'desc';
			}
		}

		$per_page     = $this->get_items_per_page( 'wpto_tags_per_page', self::PER_PAGE );
		$current_page = $this->get_pagenum();

		$bucket       = WPTO_Admin_Page::get_active_bucket();
		$count_range  = isset( WPTO_Admin_Page::USAGE_BUCKETS[ $bucket ] ) ? WPTO_Admin_Page::USAGE_BUCKETS[ $bucket ] : null;
		$range_filter = null;

		if ( $count_range ) {
			$range_filter = function ( $clauses ) use ( $count_range ) {
				global $wpdb;

				if ( PHP_INT_MAX === $count_range[1] ) {
					$clauses['where'] .= $wpdb->prepare( ' AND tt.count >= %d', $count_range[0] );
				} else {
					$clauses['where'] .= $wpdb->prepare( ' AND tt.count BETWEEN %d AND %d', $count_range[0], $count_range[1] );
				}

				return $clauses;
			};

			add_filter( 'terms_clauses', $range_filter );
		}

		// A search implies the user is hunting for a specific tag (e.g. an
		// empty one just created to act as a merge master), not browsing
		// usage stats, so don't hide 0-count tags in that case.
		$hide_empty = '' === $search;

		// Locked tags have no core alternative to filter out; unlocked ones have
		// no wpto_locked term meta at all (set_locked() deletes it on unlock),
		// so "not locked" is simply "meta doesn't exist".
		$meta_query = WPTO_Admin_Page::get_hide_locked_tags()
			? array( array( 'key' => WPTO_Tag_Lock::META_KEY, 'compare' => 'NOT EXISTS' ) )
			: array();

		$total_items = (int) wp_count_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => $hide_empty,
				'search'     => $search,
				'meta_query' => $meta_query,
			)
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => $hide_empty,
				'search'     => $search,
				'orderby'    => $orderby,
				'order'      => $order,
				'number'     => $per_page,
				'offset'     => ( $current_page - 1 ) * $per_page,
				'meta_query' => $meta_query,
			)
		);

		if ( $range_filter ) {
			remove_filter( 'terms_clauses', $range_filter );
		}

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$this->items = array();
		foreach ( $terms as $term ) {
			$this->items[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			);
		}

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}

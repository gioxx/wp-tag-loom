<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Admin_Page {

	const MAIN_SLUG = 'wpto-tags-optimizer';
	const SETTINGS_SLUG = 'wpto-settings';

	/**
	 * One-shot admin-notice query args. Rendered server-side as notices, then
	 * stripped from the visible URL by assets/admin.js so a plain page refresh
	 * doesn't keep re-showing a dismissed notice.
	 */
	const NOTICE_QUERY_ARGS = array( 'wpto_merged', 'wpto_merged_target', 'wpto_merged_count', 'wpto_merge_error', 'wpto_deleted', 'wpto_delete_error', 'wpto_locked', 'wpto_unlocked', 'wpto_tags_added', 'wpto_tags_not_found' );

	const USAGE_BUCKETS = array(
		'1'     => array( 1, 1 ),
		'2'     => array( 2, 2 ),
		'3-5'   => array( 3, 5 ),
		'6-10'  => array( 6, 10 ),
		'11-25' => array( 11, 25 ),
		'26-50' => array( 26, 50 ),
		'51+'   => array( 51, PHP_INT_MAX ),
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_process_stats_tab_actions' ) );
		add_filter( 'set_screen_option', array( __CLASS__, 'save_screen_options' ), 10, 3 );
		add_action( 'wp_ajax_wpto_delete_unused', array( __CLASS__, 'ajax_delete_unused' ) );
		add_action( 'wp_ajax_wpto_suggestion_action', array( __CLASS__, 'ajax_suggestion_action' ) );
		add_action( 'wp_ajax_wpto_bulk_suggestion_action', array( __CLASS__, 'ajax_bulk_suggestion_action' ) );
		add_action( 'wp_ajax_wpto_recount_tags', array( __CLASS__, 'ajax_recount_tags' ) );
		add_action( 'wp_ajax_wpto_update_tag', array( __CLASS__, 'ajax_update_tag' ) );
		add_action( 'wp_ajax_wpto_toggle_lock', array( __CLASS__, 'ajax_toggle_lock' ) );
		add_action( 'wp_ajax_wpto_bulk_lock', array( __CLASS__, 'ajax_bulk_lock' ) );
		add_action( 'wp_ajax_wpto_delete_tags', array( __CLASS__, 'ajax_delete_tags' ) );
	}

	public static function register_menu() {
		$main_hook = add_posts_page(
			__( 'Termcraft', 'termcraft' ),
			__( 'Termcraft', 'termcraft' ),
			'manage_options',
			self::MAIN_SLUG,
			array( __CLASS__, 'render_main_page' )
		);

		add_action( "load-{$main_hook}", array( __CLASS__, 'maybe_add_screen_options' ) );

		add_management_page(
			__( 'Termcraft: Settings', 'termcraft' ),
			__( 'Termcraft: Settings', 'termcraft' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( 'WPTO_Settings', 'render_page' )
		);
	}

	public static function maybe_add_screen_options() {
		if ( ! isset( $_GET['tab'] ) || 'stats' !== $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Tags per page', 'termcraft' ),
				'default' => 20,
				'option'  => 'wpto_tags_per_page',
			)
		);
	}

	public static function save_screen_options( $status, $option, $value ) {
		if ( 'wpto_tags_per_page' === $option ) {
			return (int) $value;
		}

		return $status;
	}

	public static function main_page_url() {
		return admin_url( 'edit.php?page=' . self::MAIN_SLUG );
	}

	public static function settings_page_url() {
		return admin_url( 'tools.php?page=' . self::SETTINGS_SLUG );
	}

	public static function enqueue_assets( $hook ) {
		$allowed_hooks = array( 'posts_page_' . self::MAIN_SLUG, 'tools_page_' . self::SETTINGS_SLUG );

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'wpto-admin', WPTO_PLUGIN_URL . 'assets/admin.css', array(), WPTO_VERSION );
		wp_enqueue_script( 'wpto-admin', WPTO_PLUGIN_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-autocomplete' ), WPTO_VERSION, true );

		wp_localize_script(
			'wpto-admin',
			'wptoData',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wpto_admin_action' ),
				'noticeArgs' => self::NOTICE_QUERY_ARGS,
				'i18n'       => array(
					'confirmDelete'      => __( 'Delete the selected tags?', 'termcraft' ),
					'confirmMerge'       => __( 'Confirm this merge? This action cannot be undone.', 'termcraft' ),
					'confirmDeleteTags'  => __( 'Delete the selected tags? This action cannot be undone.', 'termcraft' ),
					'confirmBulkApprove' => __( 'Approve the selected suggestions? This action cannot be undone.', 'termcraft' ),
					'confirmBulkReject'  => __( 'Reject the selected suggestions?', 'termcraft' ),
					'confirmBulkRestore' => __( 'Restore the selected suggestions to pending?', 'termcraft' ),
					'confirmNewAnalysis' => __( 'Starting a new analysis will clear any unreviewed pending suggestions and re-analyze your current tags. Rejected and previously applied suggestions are kept. Continue?', 'termcraft' ),
					'noneSelected'       => __( 'Select at least one row first.', 'termcraft' ),
					'bulkFailed'         => __( 'Some items could not be processed:', 'termcraft' ),
					'error'              => __( 'Something went wrong.', 'termcraft' ),
					'processing'         => __( 'Processing batch, this can take up to two minutes...', 'termcraft' ),
					'checkingProvider'   => __( 'Checking...', 'termcraft' ),
					'quickEditName'      => __( 'Name', 'termcraft' ),
					'quickEditSlug'      => __( 'Slug', 'termcraft' ),
					'quickEditSave'      => __( 'Save', 'termcraft' ),
					'quickEditCancel'    => __( 'Cancel', 'termcraft' ),
					'confirmDeleteTag'   => __( 'Delete this tag? This action cannot be undone.', 'termcraft' ),
					'lockThisTag'        => __( 'Lock this tag (protects it from merging and deletion)', 'termcraft' ),
					'unlockThisTag'      => __( 'Unlock this tag', 'termcraft' ),
				),
			)
		);
	}

	public static function render_main_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = ( isset( $_GET['tab'] ) && 'optimizer' === $_GET['tab'] ) ? 'optimizer' : 'stats'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wpto-wrap">
			<h1><?php esc_html_e( 'Termcraft', 'termcraft' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: %s: link to the plugin settings screen */
					esc_html__( 'Need to change the AI provider, model preference, or other options? Head over to %s.', 'termcraft' ),
					'<a href="' . esc_url( self::settings_page_url() ) . '">' . esc_html__( 'the plugin settings', 'termcraft' ) . '</a>'
				);
				?>
			</p>

			<div class="wpto-intro">
				<p>
					<strong><?php esc_html_e( 'Manage Tags', 'termcraft' ); ?></strong>
					&mdash;
					<?php esc_html_e( 'browse, search, sort, recount, delete, and merge tags by hand. No AI involved, nothing happens without your explicit confirmation.', 'termcraft' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'AI Analysis (experimental)', 'termcraft' ); ?></strong>
					&mdash;
					<?php esc_html_e( 'ask your configured AI provider to scan your tags for likely duplicates/synonyms and unused tags, then review and approve or reject each suggestion before anything changes.', 'termcraft' ); ?>
				</p>
			</div>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::main_page_url() ); ?>" class="nav-tab <?php echo esc_attr( 'stats' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Manage Tags', 'termcraft' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'optimizer', self::main_page_url() ) ); ?>" class="nav-tab <?php echo esc_attr( 'optimizer' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'AI Analysis (experimental)', 'termcraft' ); ?></a>
			</h2>

			<?php if ( 'stats' === $active_tab ) : ?>
				<?php self::render_stats_tab(); ?>
			<?php else : ?>
				<?php self::render_optimizer_tab(); ?>
			<?php endif; ?>

			<button type="button" id="wpto-back-to-top" class="button button-primary" aria-label="<?php esc_attr_e( 'Back to top', 'termcraft' ); ?>">&uarr;</button>
		</div>
		<?php
	}

	private static function render_optimizer_tab() {
		WPTO_Suggestions_Repo::prune_orphaned_rejected();
		WPTO_Suggestions_Repo::prune_orphaned_pending();

		$suggestions  = WPTO_Suggestions_Repo::get_suggestions( 'pending' );
		$rejected     = WPTO_Suggestions_Repo::get_suggestions( 'rejected' );
		$progress     = WPTO_Suggestions_Repo::get_batch_progress();
		$failed       = WPTO_Suggestions_Repo::get_failed_batches();
		$counts       = WPTO_Suggestions_Repo::get_status_counts();
		$applied      = WPTO_Suggestions_Repo::get_applied_suggestions( 50 );

		$grouped = array(
			'near_duplicate'   => array(),
			'semantic_overlap' => array(),
			'low_usage_merge'  => array(),
		);
		foreach ( $suggestions as $suggestion ) {
			if ( isset( $grouped[ $suggestion['type'] ] ) ) {
				$grouped[ $suggestion['type'] ][] = $suggestion;
			}
		}

		$type_labels = array(
			'near_duplicate'   => __( 'Near-duplicates', 'termcraft' ),
			'semantic_overlap' => __( 'Semantic overlaps', 'termcraft' ),
			'low_usage_merge'  => __( 'Low-usage tags', 'termcraft' ),
		);

		?>
			<div class="wpto-stats">
				<div class="wpto-stat-tile">
					<span class="wpto-stat-number"><?php echo esc_html( $counts['pending'] ); ?></span>
					<span class="wpto-stat-label"><?php esc_html_e( 'Pending suggestions', 'termcraft' ); ?></span>
				</div>
				<div class="wpto-stat-tile">
					<span class="wpto-stat-number"><?php echo esc_html( $counts['applied'] ); ?></span>
					<span class="wpto-stat-label"><?php esc_html_e( 'Merges applied', 'termcraft' ); ?></span>
				</div>
				<div class="wpto-stat-tile">
					<span class="wpto-stat-number"><?php echo esc_html( $counts['rejected'] ); ?></span>
					<span class="wpto-stat-label"><?php esc_html_e( 'Rejected suggestions', 'termcraft' ); ?></span>
				</div>
			</div>

			<h2><?php esc_html_e( 'AI Analysis (experimental)', 'termcraft' ); ?></h2>
			<p>
				<button type="button" class="button button-primary" id="wpto-start-analysis" <?php disabled( $progress['pending'] > 0 ); ?>><?php esc_html_e( 'Start analysis', 'termcraft' ); ?></button>
				<button type="button" class="button" id="wpto-stop-analysis" <?php disabled( 0 === $progress['pending'] ); ?>><?php esc_html_e( 'Stop analysis', 'termcraft' ); ?></button>
			</p>
			<div id="wpto-progress" data-total="<?php echo esc_attr( $progress['total'] ); ?>" data-done="<?php echo esc_attr( $progress['done'] ); ?>" data-pending="<?php echo esc_attr( $progress['pending'] ); ?>">
				<?php if ( $progress['total'] > 0 ) : ?>
					<p><?php
						printf(
							/* translators: 1: batches done, 2: total batches */
							esc_html__( 'Batches completed: %1$d / %2$d', 'termcraft' ),
							(int) $progress['done'],
							(int) $progress['total']
						);
					?></p>
				<?php endif; ?>
			</div>
			<div id="wpto-current-status"></div>

			<h3><?php esc_html_e( 'Processing log', 'termcraft' ); ?></h3>
			<ul id="wpto-log">
				<?php foreach ( WPTO_Suggestions_Repo::get_recent_batches( 10 ) as $batch_row ) : ?>
					<li data-batch-id="<?php echo esc_attr( $batch_row['id'] ); ?>">
						<?php
						printf(
							/* translators: 1: batch id, 2: status, 3: timestamp */
							esc_html__( 'Batch #%1$d - %2$s (%3$s)', 'termcraft' ),
							(int) $batch_row['id'],
							esc_html( $batch_row['status'] ),
							esc_html( $batch_row['processed_at'] ? $batch_row['processed_at'] : $batch_row['created_at'] )
						);
						if ( ! empty( $batch_row['error_message'] ) ) {
							echo ' - ' . esc_html( $batch_row['error_message'] );
						}
						?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( ! empty( $failed ) ) : ?>
				<h3><?php esc_html_e( 'Failed batches', 'termcraft' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Batch ID', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Error', 'termcraft' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $failed as $batch ) : ?>
							<tr>
								<td><?php echo esc_html( $batch['id'] ); ?></td>
								<td><?php echo esc_html( $batch['error_message'] ); ?></td>
								<td><button type="button" class="button wpto-retry-batch" data-batch-id="<?php echo esc_attr( $batch['id'] ); ?>"><?php esc_html_e( 'Retry', 'termcraft' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Suggestions', 'termcraft' ); ?></h2>

			<?php foreach ( $grouped as $type => $rows ) : ?>
				<?php if ( empty( $rows ) ) { continue; } ?>
				<h3><?php echo esc_html( $type_labels[ $type ] ); ?></h3>
				<p class="wpto-bulk-actions">
					<button type="button" class="button wpto-bulk-approve" data-group="<?php echo esc_attr( $type ); ?>" disabled><?php esc_html_e( 'Approve selected', 'termcraft' ); ?></button>
					<button type="button" class="button wpto-bulk-reject" data-group="<?php echo esc_attr( $type ); ?>" disabled><?php esc_html_e( 'Reject selected', 'termcraft' ); ?></button>
				</p>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" class="wpto-select-all-suggestions" data-group="<?php echo esc_attr( $type ); ?>" autocomplete="off" /></td>
							<th><?php esc_html_e( 'Source tag(s)', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Target tag', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'termcraft' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php self::render_suggestion_row( $row, false, $type ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php if ( ! empty( $rejected ) ) : ?>
				<h3><?php esc_html_e( 'Rejected suggestions', 'termcraft' ); ?></h3>
				<p class="wpto-bulk-actions">
					<button type="button" class="button wpto-bulk-restore" data-group="rejected" disabled><?php esc_html_e( 'Restore selected', 'termcraft' ); ?></button>
				</p>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" class="wpto-select-all-suggestions" data-group="rejected" autocomplete="off" /></td>
							<th><?php esc_html_e( 'Source tag(s)', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Target tag', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'termcraft' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rejected as $row ) : ?>
							<?php self::render_suggestion_row( $row, true, 'rejected' ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $applied ) ) : ?>
				<hr />
				<h2><?php esc_html_e( 'Applied suggestions (history)', 'termcraft' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The most recent merges applied to your tags, read-only.', 'termcraft' ); ?></p>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source tag(s)', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Target tag', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Applied on', 'termcraft' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $applied as $row ) : ?>
							<?php
							$source_names = json_decode( $row['source_names'], true );
							?>
							<tr>
								<td><?php echo esc_html( implode( ', ', (array) $source_names ) ); ?></td>
								<td><?php echo esc_html( $row['target_name'] ); ?></td>
								<td><?php echo esc_html( $row['reason'] ); ?></td>
								<td><?php echo esc_html( round( $row['confidence'] * 100 ) . '%' ); ?></td>
								<td><?php echo esc_html( $row['applied_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php
	}

	private static function render_stats_tab() {
		self::render_stats_notices();

		$table  = new WPTO_Tag_Stats_Table();
		$action = $table->current_action();

		if ( 'add_to_merge' === $action ) {
			check_admin_referer( 'bulk-tags' );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_admin_referer(); the "All tags" filter form is a GET request so tag_id[] arrives via $_REQUEST.
			$ids = isset( $_REQUEST['tag_id'] ) ? array_map( 'absint', (array) $_REQUEST['tag_id'] ) : array();
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			// Locked tags are allowed into the merge basket: they can safely be
			// picked as the merge *target* (which always survives), and if left
			// as a source instead, WPTO_Merge_Handler::apply() already skips
			// locked sources rather than deleting them.

			if ( ! empty( $ids ) ) {
				self::add_to_merge_basket( $ids );
				?>
				<div class="notice notice-success wpto-scroll-to-merge"><p><?php esc_html_e( 'Added to the merge selection below.', 'termcraft' ); ?></p></div>
				<?php
			}
		}

		if ( isset( $_POST['wpto_prepare_merge_basket'] ) ) {
			$basket_terms = self::get_merge_basket_terms();

			if ( count( $basket_terms ) >= 2 ) {
				self::render_merge_confirmation( $basket_terms );
				return;
			}

			?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Select at least two tags to merge.', 'termcraft' ); ?></p></div>
			<?php
		}

		self::render_section_nav();

		$unused_terms = WPTO_Unused_Tags::get_unused_terms();
		$in_use_count = (int) wp_count_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			)
		);
		?>
		<h2 id="wpto-section-overview"><?php esc_html_e( 'Overview', 'termcraft' ); ?></h2>
		<div class="wpto-overview-panel">
			<div class="wpto-stats">
				<div class="wpto-stat-tile">
					<span class="wpto-stat-number"><?php echo esc_html( number_format_i18n( $in_use_count ) ); ?></span>
					<span class="wpto-stat-label"><?php esc_html_e( 'Tags in use', 'termcraft' ); ?></span>
				</div>
				<div class="wpto-stat-tile">
					<span class="wpto-stat-number"><?php echo esc_html( number_format_i18n( count( $unused_terms ) ) ); ?></span>
					<span class="wpto-stat-label"><?php esc_html_e( 'Unused tags', 'termcraft' ); ?></span>
				</div>
			</div>

			<?php self::render_usage_histogram(); ?>
		</div>

		<hr />

		<h2 id="wpto-section-unused"><?php esc_html_e( 'Unused tags (0 posts)', 'termcraft' ); ?></h2>
		<p>
			<button type="button" class="button" id="wpto-recount-tags"><?php esc_html_e( 'Recount tag counts', 'termcraft' ); ?></button>
			<span class="description"><?php esc_html_e( 'Fixes the per-tag post count if it has drifted out of sync with the actual associations (e.g. after an import).', 'termcraft' ); ?></span>
		</p>
		<?php if ( empty( $unused_terms ) ) : ?>
			<p><?php esc_html_e( 'No unused tags found.', 'termcraft' ); ?></p>
		<?php else : ?>
			<form id="wpto-unused-form">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="wpto-select-all-unused" autocomplete="off" /></td>
							<th><?php esc_html_e( 'Name', 'termcraft' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'termcraft' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $unused_terms as $term ) : ?>
							<tr>
								<th class="check-column"><input type="checkbox" class="wpto-unused-checkbox" value="<?php echo esc_attr( $term->term_id ); ?>" autocomplete="off" /></th>
								<td><?php echo esc_html( $term->name ); ?></td>
								<td><?php echo esc_html( $term->slug ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button button-secondary" id="wpto-delete-unused"><?php esc_html_e( 'Delete selected', 'termcraft' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<hr />

		<h2 id="wpto-section-all-tags"><?php esc_html_e( 'All tags', 'termcraft' ); ?></h2>
		<?php self::render_quick_sort_links(); ?>
		<?php self::render_hide_locked_filter(); ?>
		<?php
		$table->prepare_items();
		?>
		<form method="get" id="wpto-tags-filter">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MAIN_SLUG ); ?>" />
			<input type="hidden" name="tab" value="stats" />
			<?php $current_bucket = self::get_active_bucket(); ?>
			<?php if ( '' !== $current_bucket ) : ?>
				<input type="hidden" name="bucket" value="<?php echo esc_attr( $current_bucket ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $current_bucket && isset( self::USAGE_BUCKETS[ $current_bucket ] ) ) : ?>
				<p class="wpto-histogram-filter-notice">
					<?php
					printf(
						/* translators: %s: usage bucket label, e.g. "3-5" */
						esc_html__( 'Filtering by usage: %s posts.', 'termcraft' ),
						esc_html( $current_bucket )
					);
					?>
					<a href="<?php echo esc_url( add_query_arg( 'bucket', '', remove_query_arg( 'paged', add_query_arg( 'tab', 'stats', self::main_page_url() ) ) ) ); ?>"><?php esc_html_e( 'Clear filter', 'termcraft' ); ?></a>
				</p>
			<?php endif; ?>
			<?php
			$table->search_box( __( 'Search tags', 'termcraft' ), 'wpto-tag-search' );
			$table->display();
			?>
		</form>

		<hr />

		<h2 id="wpto-section-merge"><?php esc_html_e( 'Merge selection', 'termcraft' ); ?></h2>
		<?php self::render_merge_basket_bar(); ?>
		<?php
	}

	private static function render_section_nav() {
		$sections = array(
			'wpto-section-overview' => __( 'Overview', 'termcraft' ),
			'wpto-section-unused'   => __( 'Unused tags', 'termcraft' ),
			'wpto-section-all-tags' => __( 'All tags', 'termcraft' ),
			'wpto-section-merge'    => __( 'Merge selection', 'termcraft' ),
		);
		?>
		<nav class="wpto-section-nav">
			<?php foreach ( $sections as $id => $label ) : ?>
				<a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function render_quick_sort_links() {
		$user_id = get_current_user_id();

		if ( isset( $_GET['orderby'] ) || isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'count'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_order   = isset( $_GET['order'] ) ? strtolower( sanitize_key( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$current_orderby = $user_id ? get_user_meta( $user_id, 'wpto_tags_orderby', true ) : '';
			$current_order   = $user_id ? get_user_meta( $user_id, 'wpto_tags_order', true ) : '';
		}

		if ( ! in_array( $current_orderby, array( 'name', 'count' ), true ) ) {
			$current_orderby = 'count';
		}
		if ( ! in_array( $current_order, array( 'asc', 'desc' ), true ) ) {
			$current_order = 'desc';
		}

		$presets = array(
			array(
				'label'   => __( 'Name A→Z', 'termcraft' ),
				'orderby' => 'name',
				'order'   => 'asc',
			),
			array(
				'label'   => __( 'Name Z→A', 'termcraft' ),
				'orderby' => 'name',
				'order'   => 'desc',
			),
			array(
				'label'   => __( 'Most used', 'termcraft' ),
				'orderby' => 'count',
				'order'   => 'desc',
			),
			array(
				'label'   => __( 'Least used', 'termcraft' ),
				'orderby' => 'count',
				'order'   => 'asc',
			),
		);

		$base_url      = add_query_arg( 'tab', 'stats', self::main_page_url() );
		$active_bucket = self::get_active_bucket();
		if ( '' !== $active_bucket ) {
			$base_url = add_query_arg( 'bucket', $active_bucket, $base_url );
		}
		?>
		<p class="wpto-quick-sort">
			<span class="wpto-quick-sort-label"><?php esc_html_e( 'Quick sort:', 'termcraft' ); ?></span>
			<?php foreach ( $presets as $preset ) : ?>
				<?php
				$is_active = ( $current_orderby === $preset['orderby'] && $current_order === $preset['order'] );
				$url       = add_query_arg(
					array(
						'orderby' => $preset['orderby'],
						'order'   => $preset['order'],
					),
					$base_url
				);
				$url       = remove_query_arg( 'paged', $url );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-small<?php echo $is_active ? ' button-primary' : ''; ?>"><?php echo esc_html( $preset['label'] ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php
	}

	private static function render_stats_notices() {
		if ( isset( $_GET['wpto_merged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Resolve the target name server-side from its term ID rather than
			// trusting a client-supplied string, so the URL can't be crafted
			// to display an arbitrary "merged into" message.
			$target_id   = isset( $_GET['wpto_merged_target'] ) ? absint( $_GET['wpto_merged_target'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target_term = $target_id ? get_term( $target_id, 'post_tag' ) : null;
			$target      = ( $target_term && ! is_wp_error( $target_term ) ) ? $target_term->name : '';
			$count       = isset( $_GET['wpto_merged_count'] ) ? absint( $_GET['wpto_merged_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						esc_html(
							/* translators: 1: number of merged tags, 2: target tag name */
							_n( 'Merged %1$d tag into "%2$s".', 'Merged %1$d tags into "%2$s".', $count, 'termcraft' )
						),
						absint( $count ),
						esc_html( $target )
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_merge_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'The merge could not be completed. Please try again.', 'termcraft' ); ?></p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['wpto_deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of deleted tags */
							_n( '%d tag deleted.', '%d tags deleted.', $count, 'termcraft' ),
							$count
						)
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_delete_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'The tag could not be deleted. Please try again.', 'termcraft' ); ?></p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_locked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['wpto_locked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of locked tags */
							_n( '%d tag locked.', '%d tags locked.', $count, 'termcraft' ),
							$count
						)
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_unlocked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['wpto_unlocked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of unlocked tags */
							_n( '%d tag unlocked.', '%d tags unlocked.', $count, 'termcraft' ),
							$count
						)
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( isset( $_GET['wpto_tags_added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$added     = absint( $_GET['wpto_tags_added'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$not_found = isset( $_GET['wpto_tags_not_found'] ) ? sanitize_text_field( wp_unslash( $_GET['wpto_tags_not_found'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $added > 0 ) :
				?>
				<div class="notice notice-success is-dismissible wpto-scroll-to-merge">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of tags added to the merge selection */
								_n( '%d tag added to the merge selection.', '%d tags added to the merge selection.', $added, 'termcraft' ),
								$added
							)
						);
						?>
					</p>
				</div>
				<?php
			endif;

			if ( '' !== $not_found ) :
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %s: comma-separated list of tag names that don't exist */
							esc_html__( 'No matching tag found for: %s', 'termcraft' ),
							esc_html( $not_found )
						);
						?>
					</p>
				</div>
				<?php
			endif;
		}
	}

	private static function get_merge_basket_terms() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		$ids = array_map( 'absint', (array) get_user_meta( $user_id, 'wpto_merge_basket', true ) );
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$terms     = array();
		$valid_ids = array();

		foreach ( $ids as $id ) {
			$term = get_term( $id, 'post_tag' );
			// Locked tags stay in the basket: they're safe as a merge target
			// (survives) and protected as a source (WPTO_Merge_Handler::apply()
			// skips locked sources instead of deleting them).
			if ( $term && ! is_wp_error( $term ) ) {
				$terms[]     = $term;
				$valid_ids[] = $id;
			}
		}

		if ( $valid_ids !== $ids ) {
			update_user_meta( $user_id, 'wpto_merge_basket', $valid_ids );
		}

		return $terms;
	}

	private static function add_to_merge_basket( array $ids ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$ids      = array_map( 'absint', $ids );
		$existing = array_map( 'absint', (array) get_user_meta( $user_id, 'wpto_merge_basket', true ) );
		$merged   = array_values( array_unique( array_merge( $existing, $ids ) ) );

		update_user_meta( $user_id, 'wpto_merge_basket', $merged );
	}

	private static function clear_merge_basket() {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			delete_user_meta( $user_id, 'wpto_merge_basket' );
		}
	}

	private static function render_merge_basket_bar() {
		$terms = self::get_merge_basket_terms();
		$count = count( $terms );
		?>
		<div class="wpto-merge-basket">
			<p>
				<label for="wpto-merge-tag-names"><?php esc_html_e( 'Add tags by name:', 'termcraft' ); ?></label>
				<form method="post" class="wpto-add-tags-by-name-form">
					<?php wp_nonce_field( 'wpto_add_tags_by_name' ); ?>
					<input type="hidden" name="page" value="<?php echo esc_attr( self::MAIN_SLUG ); ?>" />
					<input type="hidden" name="tab" value="stats" />
					<input type="text" id="wpto-merge-tag-names" name="wpto_tag_names" class="wpto-tag-autocomplete" placeholder="<?php echo esc_attr__( 'tag1, tag2, tag3', 'termcraft' ); ?>" />
					<button type="submit" name="wpto_add_tags_by_name" value="1" class="button"><?php esc_html_e( 'Add to selection', 'termcraft' ); ?></button>
				</form>
			</p>
			<?php if ( $count > 0 ) : ?>
				<p>
					<?php
					printf(
						esc_html(
							/* translators: 1: number of tags selected for merge, 2: comma-separated tag names */
							_n( 'Merge selection (%1$d): %2$s', 'Merge selection (%1$d): %2$s', $count, 'termcraft' )
						),
						absint( $count ),
						esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
					);
					?>
				</p>
				<p>
					<form method="post" style="display:inline;">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::MAIN_SLUG ); ?>" />
						<input type="hidden" name="tab" value="stats" />
						<button type="submit" name="wpto_prepare_merge_basket" value="1" class="button button-primary" <?php disabled( $count < 2 ); ?>><?php esc_html_e( 'Prepare merge', 'termcraft' ); ?></button>
					</form>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::MAIN_SLUG, 'tab' => 'stats', 'wpto_clear_merge_basket' => 1 ), admin_url( 'edit.php' ) ), 'wpto_clear_merge_basket' ) ); ?>" class="button"><?php esc_html_e( 'Clear selection', 'termcraft' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_merge_confirmation( array $terms ) {
		usort(
			$terms,
			function ( $a, $b ) {
				return $b->count - $a->count;
			}
		);
		$default_target = $terms[0]->term_id;
		?>
		<h2><?php esc_html_e( 'Confirm merge', 'termcraft' ); ?></h2>
		<p><?php esc_html_e( 'These tags will be merged into the one you choose below; the others will be deleted and all their posts re-tagged with it.', 'termcraft' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'termcraft' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'termcraft' ); ?></th>
					<th><?php esc_html_e( 'Assigned posts', 'termcraft' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $terms as $term ) : ?>
					<tr>
						<td><?php echo esc_html( $term->name ); ?></td>
						<td><?php echo esc_html( $term->slug ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $term->count ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" id="wpto-confirm-merge-form">
			<?php wp_nonce_field( 'wpto_confirm_merge' ); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MAIN_SLUG ); ?>" />
			<input type="hidden" name="tab" value="stats" />
			<?php foreach ( $terms as $term ) : ?>
				<input type="hidden" name="merge_tag_id[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
			<?php endforeach; ?>
			<p>
				<label for="wpto-merge-target"><?php esc_html_e( 'Merge into', 'termcraft' ); ?></label>
				<select name="wpto_merge_target" id="wpto-merge-target">
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $term->term_id, $default_target ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<button type="submit" name="wpto_confirm_merge" value="1" class="button button-primary" id="wpto-confirm-merge-submit"><?php esc_html_e( 'Confirm merge', 'termcraft' ); ?></button>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'stats', self::main_page_url() ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'termcraft' ); ?></a>
			</p>
		</form>
		<?php
	}

	/**
	 * Resolves the active usage-bucket filter: an explicit ?bucket= in the
	 * request always wins (and is remembered for next time, even if empty
	 * i.e. an explicit "clear filter"); otherwise falls back to the value
	 * remembered from a previous visit, so the filter survives a plain
	 * page reload/reopen until the user clears it.
	 */
	public static function get_active_bucket() {
		$user_id = get_current_user_id();

		if ( isset( $_REQUEST['bucket'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$bucket = sanitize_text_field( wp_unslash( $_REQUEST['bucket'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $user_id ) {
				update_user_meta( $user_id, 'wpto_tags_bucket', $bucket );
			}

			return $bucket;
		}

		return $user_id ? get_user_meta( $user_id, 'wpto_tags_bucket', true ) : '';
	}

	/**
	 * Whether the "All tags" table should hide locked tags, mirroring
	 * get_active_bucket()'s persistence: an explicit submission of the
	 * "Hide locked tags" checkbox (tracked via the wpto_hide_locked_submitted
	 * sentinel, since an unchecked checkbox sends no value at all) is saved
	 * and remembered across visits; defaults to true (hidden) when never set.
	 */
	public static function get_hide_locked_tags() {
		$user_id = get_current_user_id();

		if ( isset( $_REQUEST['wpto_hide_locked_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$hide_locked = isset( $_REQUEST['wpto_hide_locked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $user_id ) {
				update_user_meta( $user_id, 'wpto_tags_hide_locked', $hide_locked ? '1' : '0' );
			}

			return $hide_locked;
		}

		$stored = $user_id ? get_user_meta( $user_id, 'wpto_tags_hide_locked', true ) : '';

		return '0' !== $stored;
	}

	private static function render_hide_locked_filter() {
		$hide_locked = self::get_hide_locked_tags();
		?>
		<form method="get" class="wpto-hide-locked-filter">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MAIN_SLUG ); ?>" />
			<input type="hidden" name="tab" value="stats" />
			<input type="hidden" name="wpto_hide_locked_submitted" value="1" />
			<label>
				<input type="checkbox" id="wpto-hide-locked" name="wpto_hide_locked" value="1" <?php checked( $hide_locked ); ?> onchange="this.form.submit()" />
				<?php esc_html_e( 'Hide locked tags', 'termcraft' ); ?>
			</label>
		</form>
		<?php
	}

	private static function render_usage_histogram() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'fields'     => 'all',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$buckets = array_fill_keys( array_keys( self::USAGE_BUCKETS ), 0 );

		foreach ( $terms as $term ) {
			$count = (int) $term->count;
			foreach ( self::USAGE_BUCKETS as $label => $range ) {
				if ( $count >= $range[0] && $count <= $range[1] ) {
					++$buckets[ $label ];
					break;
				}
			}
		}

		$max           = max( 1, max( $buckets ) );
		$active_bucket = self::get_active_bucket();
		$base_url      = remove_query_arg( 'paged', add_query_arg( 'tab', 'stats', self::main_page_url() ) );
		?>
		<div class="wpto-overview-histogram">
			<h3 class="wpto-overview-histogram-title"><?php esc_html_e( 'Usage distribution', 'termcraft' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Click a bar to filter the table below to that range.', 'termcraft' ); ?></p>
			<div class="wpto-histogram">
				<?php foreach ( $buckets as $label => $count ) : ?>
					<?php $is_active = ( $active_bucket === $label ); ?>
					<a
						class="wpto-histogram-bar-wrap<?php echo $is_active ? ' wpto-histogram-bar-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'bucket', $label, $base_url ) ); ?>"
						title="<?php echo esc_attr( $label ); ?>"
					>
						<span class="wpto-histogram-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
						<div class="wpto-histogram-bar" style="height: <?php echo esc_attr( max( 2, round( ( $count / $max ) * 100 ) ) ); ?>%;"></div>
						<span class="wpto-histogram-label"><?php echo esc_html( $label ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<?php if ( '' !== $active_bucket && isset( self::USAGE_BUCKETS[ $active_bucket ] ) ) : ?>
				<p class="wpto-histogram-filter-notice">
					<?php
					printf(
						/* translators: %s: usage bucket label, e.g. "3-5" */
						esc_html__( 'Filtering by usage: %s posts.', 'termcraft' ),
						esc_html( $active_bucket )
					);
					?>
					<a href="<?php echo esc_url( add_query_arg( 'bucket', '', $base_url ) ); ?>"><?php esc_html_e( 'Clear filter', 'termcraft' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function maybe_process_stats_tab_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only, dispatches to a handler that verifies its own nonce via check_admin_referer().
		if ( ! isset( $_REQUEST['page'] ) || self::MAIN_SLUG !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only, dispatches to process_confirm_merge() which verifies the nonce via check_admin_referer().
		if ( isset( $_POST['wpto_confirm_merge'] ) ) {
			self::process_confirm_merge();
			return;
		}

		if ( isset( $_GET['wpto_delete_tag'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::process_single_delete();
			return;
		}

		if ( isset( $_GET['wpto_toggle_lock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::process_toggle_lock();
			return;
		}

		if ( isset( $_GET['wpto_clear_merge_basket'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::process_clear_merge_basket();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only, dispatches to process_add_tags_by_name() which verifies the nonce via check_admin_referer().
		if ( isset( $_POST['wpto_add_tags_by_name'] ) ) {
			self::process_add_tags_by_name();
			return;
		}

		$bulk_action = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- the bulk action name itself; process_bulk_delete()/process_bulk_toggle_lock() verify the nonce via check_admin_referer() before acting on it. The "All tags" filter form is a GET request, so the submitted action arrives via $_REQUEST.
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$bulk_action = sanitize_key( $_REQUEST['action'] );
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$bulk_action = sanitize_key( $_REQUEST['action2'] );
		}
		// phpcs:enable

		if ( 'delete' === $bulk_action ) {
			self::process_bulk_delete();
		} elseif ( 'lock' === $bulk_action ) {
			self::process_bulk_toggle_lock( true );
		} elseif ( 'unlock' === $bulk_action ) {
			self::process_bulk_toggle_lock( false );
		}
	}

	private static function process_add_tags_by_name() {
		check_admin_referer( 'wpto_add_tags_by_name' );

		$raw = isset( $_POST['wpto_tag_names'] ) ? sanitize_text_field( wp_unslash( $_POST['wpto_tag_names'] ) ) : '';

		$redirect_args = array(
			'page' => self::MAIN_SLUG,
			'tab'  => 'stats',
		);

		$names = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );

		if ( empty( $names ) ) {
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
			exit;
		}

		$seen        = array();
		$matched_ids = array();
		$not_found   = array();

		foreach ( $names as $name ) {
			$key = strtolower( $name );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$term = get_term_by( 'name', $name, 'post_tag' );

			if ( $term && ! is_wp_error( $term ) ) {
				// Locked tags are allowed in: they're safe as a merge target
				// (survives) and protected as a source at apply time.
				$matched_ids[] = (int) $term->term_id;
			} else {
				$not_found[] = $name;
			}
		}

		if ( ! empty( $matched_ids ) ) {
			self::add_to_merge_basket( $matched_ids );
		}

		$redirect_args['wpto_tags_added'] = count( $matched_ids );

		if ( ! empty( $not_found ) ) {
			$redirect_args['wpto_tags_not_found'] = rawurlencode( implode( ', ', $not_found ) );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function process_confirm_merge() {
		check_admin_referer( 'wpto_confirm_merge' );

		$target_id  = isset( $_POST['wpto_merge_target'] ) ? absint( $_POST['wpto_merge_target'] ) : 0;
		$source_ids = isset( $_POST['merge_tag_id'] ) ? array_map( 'absint', (array) $_POST['merge_tag_id'] ) : array();
		$source_ids = array_values( array_diff( array_unique( $source_ids ), array( $target_id ) ) );

		$redirect_args = array(
			'page' => self::MAIN_SLUG,
			'tab'  => 'stats',
		);

		$target = $target_id ? get_term( $target_id, 'post_tag' ) : null;

		if ( ! $target_id || empty( $source_ids ) || ! $target || is_wp_error( $target ) ) {
			$redirect_args['wpto_merge_error'] = 1;
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
			exit;
		}

		$result = WPTO_Merge_Handler::apply(
			array(
				'target_term_id'  => $target_id,
				'source_term_ids' => wp_json_encode( $source_ids ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$redirect_args['wpto_merge_error'] = 1;
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
			exit;
		}

		self::clear_merge_basket();

		$redirect_args['wpto_merged']        = 1;
		$redirect_args['wpto_merged_target'] = $target_id;
		$redirect_args['wpto_merged_count']  = count( $result['source_names'] );

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function process_clear_merge_basket() {
		check_admin_referer( 'wpto_clear_merge_basket' );

		self::clear_merge_basket();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MAIN_SLUG,
					'tab'  => 'stats',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	private static function process_single_delete() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below via check_admin_referer().
		$tag_id = isset( $_GET['wpto_delete_tag'] ) ? absint( $_GET['wpto_delete_tag'] ) : 0;

		check_admin_referer( 'wpto_delete_tag_' . $tag_id );

		$redirect_args = array(
			'page' => self::MAIN_SLUG,
			'tab'  => 'stats',
		);

		$deleted = $tag_id && ! WPTO_Tag_Lock::is_locked( $tag_id ) && true === wp_delete_term( $tag_id, 'post_tag' );

		$redirect_args[ $deleted ? 'wpto_deleted' : 'wpto_delete_error' ] = 1;

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function process_toggle_lock() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below via check_admin_referer().
		$tag_id = isset( $_GET['wpto_toggle_lock'] ) ? absint( $_GET['wpto_toggle_lock'] ) : 0;

		check_admin_referer( 'wpto_toggle_lock_' . $tag_id );

		if ( $tag_id ) {
			WPTO_Tag_Lock::set_locked( $tag_id, ! WPTO_Tag_Lock::is_locked( $tag_id ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MAIN_SLUG,
					'tab'  => 'stats',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	private static function process_bulk_delete() {
		check_admin_referer( 'bulk-tags' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_admin_referer(); the "All tags" filter form is a GET request so tag_id[] arrives via $_REQUEST.
		$ids = isset( $_REQUEST['tag_id'] ) ? array_map( 'absint', (array) $_REQUEST['tag_id'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$ids = WPTO_Tag_Lock::filter_unlocked( $ids );

		$redirect_args = array(
			'page' => self::MAIN_SLUG,
			'tab'  => 'stats',
		);

		if ( empty( $ids ) ) {
			$redirect_args['wpto_delete_error'] = 1;
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
			exit;
		}

		$deleted_count = 0;
		foreach ( $ids as $id ) {
			if ( true === wp_delete_term( $id, 'post_tag' ) ) {
				++$deleted_count;
			}
		}

		if ( $deleted_count > 0 ) {
			$redirect_args['wpto_deleted'] = $deleted_count;
		} else {
			$redirect_args['wpto_delete_error'] = 1;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function process_bulk_toggle_lock( $locked ) {
		check_admin_referer( 'bulk-tags' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_admin_referer(); the "All tags" filter form is a GET request so tag_id[] arrives via $_REQUEST.
		$ids = isset( $_REQUEST['tag_id'] ) ? array_map( 'absint', (array) $_REQUEST['tag_id'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$redirect_args = array(
			'page' => self::MAIN_SLUG,
			'tab'  => 'stats',
		);

		foreach ( $ids as $id ) {
			WPTO_Tag_Lock::set_locked( $id, $locked );
		}

		$redirect_args[ $locked ? 'wpto_locked' : 'wpto_unlocked' ] = count( $ids );

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function render_suggestion_row( array $row, $rejected = false, $group = '' ) {
		$source_ids   = json_decode( $row['source_term_ids'], true );
		$source_terms = array();
		foreach ( (array) $source_ids as $sid ) {
			$t = get_term( $sid, 'post_tag' );
			if ( $t && ! is_wp_error( $t ) ) {
				$source_terms[ (int) $sid ] = $t->name;
			}
		}
		$target_id   = (int) $row['target_term_id'];
		$target_term = get_term( $target_id, 'post_tag' );
		$target_name = ( $target_term && ! is_wp_error( $target_term ) ) ? $target_term->name : '';
		$all_terms   = array( $target_id => $target_name ) + $source_terms;
		?>
		<tr>
			<th class="check-column"><input type="checkbox" class="wpto-suggestion-checkbox" data-group="<?php echo esc_attr( $group ); ?>" value="<?php echo esc_attr( $row['id'] ); ?>" autocomplete="off" /></th>
			<td><?php echo esc_html( implode( ', ', $source_terms ) ); ?></td>
			<td><?php echo esc_html( $target_name ); ?></td>
			<td><?php echo esc_html( $row['reason'] ); ?></td>
			<td><?php echo esc_html( round( $row['confidence'] * 100 ) . '%' ); ?></td>
			<td>
				<?php if ( $rejected ) : ?>
					<button type="button" class="button wpto-restore" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Restore', 'termcraft' ); ?></button>
				<?php else : ?>
					<label class="screen-reader-text" for="wpto-target-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Merge into', 'termcraft' ); ?></label>
					<select class="wpto-target-select" id="wpto-target-<?php echo esc_attr( $row['id'] ); ?>">
						<?php foreach ( $all_terms as $tid => $tname ) : ?>
							<option value="<?php echo esc_attr( $tid ); ?>" <?php selected( $tid, $target_id ); ?>><?php echo esc_html( $tname ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button button-primary wpto-approve" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Approve', 'termcraft' ); ?></button>
					<button type="button" class="button wpto-reject" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Reject', 'termcraft' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public static function ajax_delete_unused() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$term_ids = isset( $_POST['term_ids'] ) ? array_map( 'absint', (array) $_POST['term_ids'] ) : array();

		if ( empty( $term_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No tags selected.', 'termcraft' ) ) );
		}

		$result = WPTO_Unused_Tags::delete_terms( $term_ids );

		wp_send_json_success( $result );
	}

	public static function ajax_recount_tags() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$count = WPTO_Unused_Tags::recount_all();

		wp_send_json_success( array( 'count' => $count ) );
	}

	public static function ajax_update_tag() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $tag_id || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'The tag name cannot be empty.', 'termcraft' ) ) );
		}

		$args = array( 'name' => $name );

		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		$result = wp_update_term( $tag_id, 'post_tag', $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$term = get_term( $result['term_id'], 'post_tag' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong.', 'termcraft' ) ) );
		}

		wp_send_json_success(
			array(
				'name' => $term->name,
				'slug' => $term->slug,
			)
		);
	}

	public static function ajax_toggle_lock() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;

		if ( ! $tag_id || ! term_exists( $tag_id, 'post_tag' ) ) {
			wp_send_json_error( array( 'message' => __( 'Tag not found.', 'termcraft' ) ) );
		}

		$locked = ! WPTO_Tag_Lock::is_locked( $tag_id );
		WPTO_Tag_Lock::set_locked( $tag_id, $locked );

		wp_send_json_success( array( 'locked' => $locked ) );
	}

	public static function ajax_bulk_lock() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$ids    = isset( $_POST['tag_id'] ) ? array_map( 'absint', (array) $_POST['tag_id'] ) : array();
		$ids    = array_values( array_unique( array_filter( $ids ) ) );
		$locked = isset( $_POST['locked'] ) && '1' === $_POST['locked'];

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No tags selected.', 'termcraft' ) ) );
		}

		foreach ( $ids as $id ) {
			WPTO_Tag_Lock::set_locked( $id, $locked );
		}

		wp_send_json_success(
			array(
				'ids'    => $ids,
				'locked' => $locked,
			)
		);
	}

	public static function ajax_delete_tags() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$ids = isset( $_POST['tag_id'] ) ? array_map( 'absint', (array) $_POST['tag_id'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$ids = WPTO_Tag_Lock::filter_unlocked( $ids );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No tags could be deleted.', 'termcraft' ) ) );
		}

		$deleted = array();
		foreach ( $ids as $id ) {
			if ( true === wp_delete_term( $id, 'post_tag' ) ) {
				$deleted[] = $id;
			}
		}

		if ( empty( $deleted ) ) {
			wp_send_json_error( array( 'message' => __( 'The tag(s) could not be deleted.', 'termcraft' ) ) );
		}

		wp_send_json_success( array( 'ids' => $deleted ) );
	}

	public static function ajax_suggestion_action() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$id              = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$action          = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$target_override = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		if ( ! $id || ! in_array( $action, array( 'approve', 'reject', 'restore' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'termcraft' ) ) );
		}

		$error = self::apply_suggestion_action( $id, $action, $target_override );

		if ( null !== $error ) {
			wp_send_json_error( array( 'message' => $error ) );
		}

		wp_send_json_success();
	}

	public static function ajax_bulk_suggestion_action() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$action = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$ids    = array_filter( $ids );

		if ( empty( $ids ) || ! in_array( $action, array( 'approve', 'reject', 'restore' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'termcraft' ) ) );
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $ids as $id ) {
			$error = self::apply_suggestion_action( $id, $action );

			if ( null !== $error ) {
				$failed[ $id ] = $error;
			} else {
				$succeeded[] = $id;
			}
		}

		wp_send_json_success(
			array(
				'succeeded' => $succeeded,
				'failed'    => $failed,
			)
		);
	}

	/**
	 * Applies a single approve/reject/restore action to a suggestion.
	 *
	 * @return string|null Error message, or null on success.
	 */
	private static function apply_suggestion_action( $id, $action, $target_override = 0 ) {
		$suggestion = WPTO_Suggestions_Repo::get_suggestion( $id );

		if ( ! $suggestion ) {
			return __( 'Suggestion not found.', 'termcraft' );
		}

		if ( 'reject' === $action ) {
			WPTO_Suggestions_Repo::set_suggestion_status( $id, 'rejected' );
			return null;
		}

		if ( 'restore' === $action ) {
			WPTO_Suggestions_Repo::set_suggestion_status( $id, 'pending' );
			return null;
		}

		if ( $target_override && $target_override !== (int) $suggestion['target_term_id'] ) {
			$source_ids = array_map( 'intval', (array) json_decode( $suggestion['source_term_ids'], true ) );
			$all_ids    = array_unique( array_merge( $source_ids, array( (int) $suggestion['target_term_id'] ) ) );

			if ( ! in_array( $target_override, $all_ids, true ) ) {
				return __( 'Invalid target selection.', 'termcraft' );
			}

			$suggestion['source_term_ids'] = wp_json_encode( array_values( array_diff( $all_ids, array( $target_override ) ) ) );
			$suggestion['target_term_id']  = $target_override;
		}

		$result = WPTO_Merge_Handler::apply( $suggestion );

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		WPTO_Suggestions_Repo::mark_applied( $id, $result['source_names'], $result['target_name'] );
		return null;
	}
}

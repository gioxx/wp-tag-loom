<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Queue {

	const CRON_HOOK = 'wpto_process_batch';
	const LOCK_KEY = 'wpto_batch_processing_lock';
	const MAX_ANCHOR_TAGS = 100;

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_next_batch' ) );
		add_action( 'wp_ajax_wpto_start_analysis', array( __CLASS__, 'ajax_start_analysis' ) );
		add_action( 'wp_ajax_wpto_process_tick', array( __CLASS__, 'ajax_process_tick' ) );
		add_action( 'wp_ajax_wpto_stop_analysis', array( __CLASS__, 'ajax_stop_analysis' ) );
		add_action( 'wp_ajax_wpto_retry_batch', array( __CLASS__, 'ajax_retry_batch' ) );
	}

	public static function ajax_start_analysis() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$ready = ( new WPTO_Api_Client() )->provider_available();
		if ( is_wp_error( $ready ) ) {
			wp_send_json_error( array( 'message' => $ready->get_error_message() ) );
		}

		$progress = WPTO_Suggestions_Repo::get_batch_progress();

		// A run is already in progress (or waiting to be resumed): don't
		// re-enqueue every tag again, just let the caller resume ticking it.
		if ( $progress['pending'] > 0 ) {
			wp_send_json_success( self::build_status_payload() );
		}

		WPTO_Suggestions_Repo::clear_for_new_analysis();
		self::enqueue_all_tags();

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_process_tick() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		self::process_next_batch();

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_stop_analysis() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		WPTO_Suggestions_Repo::cancel_pending_batches();
		wp_clear_scheduled_hook( self::CRON_HOOK );

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_retry_batch() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'termcraft' ) ), 403 );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;

		if ( ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch.', 'termcraft' ) ) );
		}

		WPTO_Suggestions_Repo::retry_batch( $batch_id );
		self::schedule_next_run();

		wp_send_json_success( self::build_status_payload() );
	}

	private static function build_status_payload() {
		return array(
			'progress' => WPTO_Suggestions_Repo::get_batch_progress(),
			'log'      => WPTO_Suggestions_Repo::get_recent_batches( 10 ),
		);
	}

	/**
	 * Builds analysis batches. Every batch includes the most-used tags
	 * ("anchors") alongside a rotating slice of the rest, so low-usage
	 * (often single-post) tags are always compared against real, popular
	 * candidates instead of only whichever tags happen to sort next to
	 * them alphabetically.
	 */
	private static function enqueue_all_tags() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		usort(
			$terms,
			function ( $a, $b ) {
				if ( $a->count === $b->count ) {
					return strcasecmp( $a->name, $b->name );
				}
				return $b->count - $a->count;
			}
		);

		$tags = array();
		foreach ( $terms as $term ) {
			$tags[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'count' => (int) $term->count,
			);
		}

		$batch_size   = WPTO_Settings::get_batch_size();
		$anchor_count = min( self::MAX_ANCHOR_TAGS, (int) floor( $batch_size / 2 ), count( $tags ) );
		$anchors      = array_slice( $tags, 0, $anchor_count );
		$remaining    = array_slice( $tags, $anchor_count );

		if ( empty( $remaining ) ) {
			WPTO_Suggestions_Repo::create_batch( wp_list_pluck( $anchors, 'id' ) );
			self::schedule_next_run();
			return;
		}

		$rotating_size = max( 1, $batch_size - $anchor_count );

		foreach ( array_chunk( $remaining, $rotating_size ) as $chunk ) {
			$batch_tags = array_merge( $anchors, $chunk );
			WPTO_Suggestions_Repo::create_batch( wp_list_pluck( $batch_tags, 'id' ) );
		}

		self::schedule_next_run();
	}

	private static function schedule_next_run() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/**
	 * Process a single pending batch. Guarded by a transient lock so the
	 * WP-Cron fallback and the browser-driven "tick" requests never process
	 * the same batch twice at once.
	 */
	public static function process_next_batch() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			self::do_process_next_batch();
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	private static function do_process_next_batch() {
		$batch = WPTO_Suggestions_Repo::get_next_pending_batch();

		if ( ! $batch ) {
			return;
		}

		$term_ids = json_decode( $batch['term_ids'], true );

		if ( ! is_array( $term_ids ) || empty( $term_ids ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], __( 'Batch tag list is empty or invalid.', 'termcraft' ) );
			self::maybe_reschedule();
			return;
		}

		$tags = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tags[] = array(
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'count' => (int) $term->count,
				);
			}
		}

		if ( empty( $tags ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], __( 'No valid tags found for this batch.', 'termcraft' ) );
			self::maybe_reschedule();
			return;
		}

		$client = new WPTO_Api_Client();
		$result = $client->analyze_batch( $tags );

		if ( is_wp_error( $result ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], $result->get_error_message() );
			self::maybe_reschedule();
			return;
		}

		WPTO_Suggestions_Repo::insert_suggestions( $batch['id'], $result );
		WPTO_Suggestions_Repo::mark_batch_done( $batch['id'] );

		self::maybe_reschedule();
	}

	private static function maybe_reschedule() {
		$next = WPTO_Suggestions_Repo::get_next_pending_batch();
		if ( $next ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}
}

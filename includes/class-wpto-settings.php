<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Settings {

	const OPTION_GROUP = 'wpto_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_wpto_check_ai_provider', array( __CLASS__, 'ajax_check_provider' ) );
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'wpto_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_batch_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_batch_size' ),
				'default'           => 150,
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_ai_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_cleanup_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);
	}

	public static function sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	public static function sanitize_batch_size( $value ) {
		$value = absint( $value );
		if ( $value < 10 ) {
			$value = 10;
		}
		if ( $value > 500 ) {
			$value = 500;
		}
		return $value;
	}

	/**
	 * Preferred model IDs in priority order, passed to the AI Client as a
	 * model preference. Empty means "let the site's AI provider decide".
	 *
	 * @return string[]
	 */
	public static function get_model_preference() {
		$raw = (string) get_option( 'wpto_model', '' );

		$models = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

		return array_values( $models );
	}

	public static function get_batch_size() {
		return (int) get_option( 'wpto_batch_size', 150 );
	}

	public static function get_ai_language() {
		return get_option( 'wpto_ai_language', '' );
	}

	public static function get_cleanup_on_uninstall() {
		return (bool) get_option( 'wpto_cleanup_on_uninstall', true );
	}

	public static function ajax_check_provider() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tag-loom' ) ), 403 );
		}

		$client = new WPTO_Api_Client();
		$result = $client->provider_available();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'An AI provider is configured and ready.', 'tag-loom' ) ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tag Loom: Settings', 'tag-loom' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: link to the "Posts > Tag Loom" screen */
					esc_html__( 'Looking to run an analysis or review suggestions? Head over to %s.', 'tag-loom' ),
					'<a href="' . esc_url( WPTO_Admin_Page::main_page_url() ) . '">' . esc_html__( 'Posts &rsaquo; Tag Loom', 'tag-loom' ) . '</a>'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'AI provider', 'tag-loom' ); ?></th>
						<td>
							<button type="button" class="button" id="wpto-check-provider"><?php esc_html_e( 'Check AI provider', 'tag-loom' ); ?></button>
							<span id="wpto-check-provider-result"></span>
							<p class="description">
								<?php esc_html_e( 'The "AI Analysis" tab uses WordPress core\'s AI Client. Choose and configure a provider (and its credentials) once in the WordPress settings; Tag Loom never stores an API key of its own. Tag names and usage counts are sent to that provider only when you click "Start analysis" - nothing is sent automatically, and the "Manage Tags" tab works fully offline.', 'tag-loom' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_model"><?php esc_html_e( 'Preferred model(s)', 'tag-loom' ); ?></label></th>
						<td>
							<input type="text" id="wpto_model" name="wpto_model" value="<?php echo esc_attr( (string) get_option( 'wpto_model', '' ) ); ?>" class="regular-text" placeholder="claude-haiku-4-5, gpt-5.4, gemini-3.1-pro" />
							<p class="description"><?php esc_html_e( 'Optional. Comma-separated model IDs in priority order; the AI Client uses the first one your provider supports. Leave blank to let the provider pick its default model.', 'tag-loom' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_batch_size"><?php esc_html_e( 'Batch size', 'tag-loom' ); ?></label></th>
						<td>
							<input type="number" min="10" max="500" id="wpto_batch_size" name="wpto_batch_size" value="<?php echo esc_attr( self::get_batch_size() ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of tags sent per AI request (10-500).', 'tag-loom' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_ai_language"><?php esc_html_e( 'AI response language', 'tag-loom' ); ?></label></th>
						<td>
							<input type="text" id="wpto_ai_language" name="wpto_ai_language" value="<?php echo esc_attr( self::get_ai_language() ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Italian, English...', 'tag-loom' ); ?>" />
							<p class="description"><?php esc_html_e( 'Language the AI should use for the "reason" it gives on each suggestion. Leave blank to let it match the language of your tag names automatically. This only affects the AI output, not the plugin\'s own interface language.', 'tag-loom' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Full cleanup on uninstall', 'tag-loom' ); ?></th>
						<td>
							<input type="hidden" name="wpto_cleanup_on_uninstall" value="0" />
							<label>
								<input type="checkbox" id="wpto_cleanup_on_uninstall" name="wpto_cleanup_on_uninstall" value="1" <?php checked( self::get_cleanup_on_uninstall() ); ?> />
								<?php esc_html_e( 'Remove all plugin data (database tables and settings) when the plugin is deleted.', 'tag-loom' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Uncheck this if you want batch history and suggestions to survive a delete-and-reinstall of the plugin.', 'tag-loom' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php self::render_credits(); ?>
		</div>
		<?php
	}

	private static function render_credits() {
		?>
		<div class="wpto-credits">
			<p class="description">
				<?php
				printf(
					/* translators: 1: link to the author's site, 2: link to the plugin repository */
					esc_html__( 'Tag Loom by %1$s, source on %2$s.', 'tag-loom' ),
					'<a href="https://gioxx.org" target="_blank" rel="noopener noreferrer">Gioxx</a>',
					'<a href="https://github.com/gioxx/wp-tag-loom" target="_blank" rel="noopener noreferrer">GitHub</a>'
				);
				?>
			</p>
			<p class="description wpto-trademark-notice">
				<?php esc_html_e( 'All trademarks mentioned are the property of their respective owners. Third-party trademarks, product names, trade names, corporate names and companies mentioned may be trademarks of their respective owners or registered trademarks of other companies and have been used for explanatory purposes only and for the benefit of the owner, without any intent to infringe existing copyright.', 'tag-loom' ); ?>
			</p>
		</div>
		<?php
	}
}

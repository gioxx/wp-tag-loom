<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Settings {

	const OPTION_GROUP = 'wpto_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_wpto_test_api_key', array( __CLASS__, 'ajax_test_api_key' ) );
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'wpto_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'claude-haiku-4-5',
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

	public static function get_api_key() {
		return get_option( 'wpto_api_key', '' );
	}

	public static function get_model() {
		return get_option( 'wpto_model', 'claude-haiku-4-5' );
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

	public static function ajax_test_api_key() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'smart-tags-optimizer' ) ), 403 );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Enter an API key first.', 'smart-tags-optimizer' ) ) );
		}

		$client = new WPTO_Api_Client();
		$result = $client->test_connection( $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful.', 'smart-tags-optimizer' ) ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Tags Optimizer: Settings', 'smart-tags-optimizer' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: link to the "Posts > Smart Tags" screen */
					esc_html__( 'Looking to run an analysis or review suggestions? Head over to %s.', 'smart-tags-optimizer' ),
					'<a href="' . esc_url( WPTO_Admin_Page::main_page_url() ) . '">' . esc_html__( 'Posts &rsaquo; Smart Tags', 'smart-tags-optimizer' ) . '</a>'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpto_api_key"><?php esc_html_e( 'Anthropic API Key', 'smart-tags-optimizer' ); ?></label></th>
						<td>
							<input type="password" id="wpto_api_key" name="wpto_api_key" value="<?php echo esc_attr( self::get_api_key() ); ?>" class="regular-text" autocomplete="off" />
							<button type="button" class="button" id="wpto-test-api-key"><?php esc_html_e( 'Test API key', 'smart-tags-optimizer' ); ?></button>
							<span id="wpto-test-api-key-result"></span>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to Anthropic's privacy policy */
									esc_html__( 'Required only for the "AI Analysis" tab. Tag names and usage counts are sent to the Anthropic Claude API (%s) solely when you click "Start analysis" there; nothing is sent automatically, and the "Manage Tags" tab works fully offline without a key.', 'smart-tags-optimizer' ),
									'<a href="https://www.anthropic.com/legal/privacy" target="_blank" rel="noopener noreferrer">anthropic.com</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_model"><?php esc_html_e( 'Model', 'smart-tags-optimizer' ); ?></label></th>
						<td>
							<input type="text" id="wpto_model" name="wpto_model" value="<?php echo esc_attr( self::get_model() ); ?>" class="regular-text" placeholder="claude-haiku-4-5" />
							<p class="description"><?php esc_html_e( 'E.g. claude-haiku-4-5, claude-sonnet-5.', 'smart-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_batch_size"><?php esc_html_e( 'Batch size', 'smart-tags-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="10" max="500" id="wpto_batch_size" name="wpto_batch_size" value="<?php echo esc_attr( self::get_batch_size() ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of tags sent per API call (10-500).', 'smart-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_ai_language"><?php esc_html_e( 'AI response language', 'smart-tags-optimizer' ); ?></label></th>
						<td>
							<input type="text" id="wpto_ai_language" name="wpto_ai_language" value="<?php echo esc_attr( self::get_ai_language() ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Italian, English...', 'smart-tags-optimizer' ); ?>" />
							<p class="description"><?php esc_html_e( 'Language Claude should use for the "reason" it gives on each suggestion. Leave blank to let it match the language of your tag names automatically. This only affects Claude\'s output, not the plugin\'s own interface language.', 'smart-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Full cleanup on uninstall', 'smart-tags-optimizer' ); ?></th>
						<td>
							<input type="hidden" name="wpto_cleanup_on_uninstall" value="0" />
							<label>
								<input type="checkbox" id="wpto_cleanup_on_uninstall" name="wpto_cleanup_on_uninstall" value="1" <?php checked( self::get_cleanup_on_uninstall() ); ?> />
								<?php esc_html_e( 'Remove all plugin data (database tables and settings) when the plugin is deleted.', 'smart-tags-optimizer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Uncheck this if you want batch history and suggestions to survive a delete-and-reinstall of the plugin.', 'smart-tags-optimizer' ); ?></p>
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
					esc_html__( 'Smart Tags Optimizer by %1$s, source on %2$s.', 'smart-tags-optimizer' ),
					'<a href="https://gioxx.org" target="_blank" rel="noopener noreferrer">Gioxx</a>',
					'<a href="https://github.com/gioxx/wp-smart-tags" target="_blank" rel="noopener noreferrer">GitHub</a>'
				);
				?>
			</p>
			<p class="description wpto-trademark-notice">
				<?php esc_html_e( 'All trademarks mentioned are the property of their respective owners. Third-party trademarks, product names, trade names, corporate names and companies mentioned may be trademarks of their respective owners or registered trademarks of other companies and have been used for explanatory purposes only and for the benefit of the owner, without any intent to infringe existing copyright.', 'smart-tags-optimizer' ); ?>
			</p>
		</div>
		<?php
	}
}

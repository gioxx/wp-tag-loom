<?php
/**
 * Plugin Name: Smart Tags Optimizer
 * Description: Manage WordPress tags with or without AI: get Claude (Anthropic) suggestions for merging duplicates/synonyms and flagging unused tags, or search, merge, and delete tags manually. Always requires manual approval before any change.
 * Version: 0.22.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Gioxx
 * Author URI: https://gioxx.org
 * Plugin URI: https://github.com/gioxx/wp-smart-tags
 * License: GPL-2.0-or-later
 * Text Domain: smart-tags-optimizer
 * Domain Path: /languages
 *
 * GitHub Plugin URI: gioxx/wp-smart-tags
 * GitHub Branch: main
 * GitHub Languages: true
 * Release Asset: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPTO_VERSION', '0.22.2' );
define( 'WPTO_PLUGIN_FILE', __FILE__ );
define( 'WPTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-activator.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-tag-lock.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-settings.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-unused-tags.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-api-client.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-suggestions-repo.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-queue.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-merge-handler.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-tag-stats-table.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-admin-page.php';

register_activation_hook( __FILE__, array( 'WPTO_Activator', 'activate' ) );
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'wpto_process_batch' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		WPTO_Activator::maybe_upgrade();
		WPTO_Settings::init();
		WPTO_Queue::init();
		WPTO_Admin_Page::init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( array $links ) {
		$settings_link = '<a href="' . esc_url( WPTO_Admin_Page::settings_page_url() ) . '">' . esc_html__( 'Settings', 'smart-tags-optimizer' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
);

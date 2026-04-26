<?php
/**
 * Plugin Name: Whisker Example Plugin
 * Plugin URI: https://usewhisker.com
 * Description: Production-quality starter plugin for integrating Whisker licensing in WordPress.
 * Version: 1.0.0
 * Author: Whisker
 * Author URI: https://usewhisker.com
 * Text Domain: whisker-example-plugin
 *
 * @package WhiskerExamplePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WHISKER_EXAMPLE_PLUGIN_VERSION', '1.0.0' );
define( 'WHISKER_EXAMPLE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WHISKER_EXAMPLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'WHISKER_API_BASE', 'https://usewhisker.com/api/license' );
define( 'WHISKER_PRODUCT_KEY', 'pk_whisker_xxxxx' );

require_once WHISKER_EXAMPLE_PLUGIN_PATH . 'includes/class-whisker-api.php';
require_once WHISKER_EXAMPLE_PLUGIN_PATH . 'includes/class-whisker-license.php';
require_once WHISKER_EXAMPLE_PLUGIN_PATH . 'includes/class-whisker-admin.php';

/**
 * Bootstrap plugin classes.
 *
 * @return array<string,mixed>
 */
function whisker_example_plugin_bootstrap() {
	static $container = null;

	if ( null !== $container ) {
		return $container;
	}

	$api     = new Whisker_API();
	$license = new Whisker_License( $api );
	$admin   = new Whisker_Admin( $license );

	$container = array(
		'api'     => $api,
		'license' => $license,
		'admin'   => $admin,
	);

	return $container;
}

/**
 * Run plugin initialization.
 *
 * @return void
 */
function whisker_example_plugin_init() {
	$container = whisker_example_plugin_bootstrap();
	$container['admin']->hooks();
}
add_action( 'plugins_loaded', 'whisker_example_plugin_init' );

/**
 * Cron callback for scheduled validation.
 *
 * @return void
 */
function whisker_example_plugin_cron_validate_license() {
	$container = whisker_example_plugin_bootstrap();
	$container['license']->validate( true );
}
add_action( 'whisker_example_plugin_validate_license_event', 'whisker_example_plugin_cron_validate_license' );

/**
 * Register cron schedule for twice daily validation.
 *
 * @return void
 */
function whisker_example_plugin_activate() {
	if ( ! wp_next_scheduled( 'whisker_example_plugin_validate_license_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'whisker_example_plugin_validate_license_event' );
	}
}
register_activation_hook( __FILE__, 'whisker_example_plugin_activate' );

/**
 * Cleanup cron event on deactivation.
 *
 * @return void
 */
function whisker_example_plugin_deactivate() {
	wp_clear_scheduled_hook( 'whisker_example_plugin_validate_license_event' );
}
register_deactivation_hook( __FILE__, 'whisker_example_plugin_deactivate' );

/**
 * Example premium feature notice when license is inactive.
 *
 * @return void
 */
function whisker_example_plugin_maybe_show_inactive_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$container = whisker_example_plugin_bootstrap();

	// Premium features must remain gated behind license checks.
	if ( $container['license']->is_active() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Whisker Example Plugin premium features are disabled until your license is active.', 'whisker-example-plugin' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'whisker_example_plugin_maybe_show_inactive_notice' );

/**
 * Example premium feature hook.
 *
 * @return void
 */
function whisker_example_plugin_premium_feature_boot() {
	$container = whisker_example_plugin_bootstrap();
	if ( ! $container['license']->is_active() ) {
		return;
	}

	// Premium-only logic would run here.
}
add_action( 'admin_init', 'whisker_example_plugin_premium_feature_boot' );

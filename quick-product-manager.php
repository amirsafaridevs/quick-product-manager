<?php
/**
 * Plugin Name:       Quick Product Manager
 * Description:       Manage WooCommerce product price and stock from a single admin table.
 * Version:           1.1.7
 * Requires at least: 5.8
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            Amir Safari
 * Author URI:        https://amirsafaridev.github.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quick-product-manager
 * Requires Plugins:  woocommerce
 * WC requires at least: 5.0
 * WC tested up to:    10.7.0
 *
 * @package QuickProductManager
 */

defined( 'ABSPATH' ) || exit;

define( 'QPM_VERSION', '1.1.7' );
define( 'QPM_PLUGIN_FILE', __FILE__ );
define( 'QPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QPM_REST_NAMESPACE', 'qpm/v1' );

/**
 * Load plugin classes.
 */
function qpm_load_classes() {
	$includes = QPM_PLUGIN_DIR . 'includes/';

	require_once $includes . 'class-qpm-product-query.php';
	require_once $includes . 'class-qpm-product-updater.php';
	require_once $includes . 'class-qpm-rest-controller.php';
	require_once $includes . 'class-qpm-admin-menu.php';
	require_once $includes . 'class-qpm-plugin.php';
}

/**
 * Whether WooCommerce is active.
 *
 * @return bool
 */
function qpm_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Bootstrap plugin.
 */
function qpm_init() {
	qpm_load_classes();
	QPM_Plugin::instance();
}

add_action( 'plugins_loaded', 'qpm_init' );

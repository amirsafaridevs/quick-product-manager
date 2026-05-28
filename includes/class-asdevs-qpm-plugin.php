<?php
/**
 * Main plugin bootstrap.
 *
 * @package ASDevsQuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ASDevs_QPM_Plugin
 */
class ASDevs_QPM_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ASDevs_QPM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ASDevs_QPM_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! asdevs_qpm_is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		new ASDevs_QPM_Admin_Menu();
		new ASDevs_QPM_REST_Controller();

		/**
		 * Fires after ASDevs Quick Product Manager is fully loaded.
		 */
		do_action( 'asdevs_qpm_loaded' );
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ASDevs Quick Product Manager requires WooCommerce to be installed and active.', 'asdevs-quick-product-manager' ); ?></p>
		</div>
		<?php
	}
}

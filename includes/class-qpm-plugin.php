<?php
/**
 * Main plugin bootstrap.
 *
 * @package QuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QPM_Plugin
 */
class QPM_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var QPM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return QPM_Plugin
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
		if ( ! qpm_is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		new QPM_Admin_Menu();
		new QPM_REST_Controller();

		/**
		 * Fires after Quick Product Manager is fully loaded.
		 */
		do_action( 'qpm_loaded' );
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
			<p><?php esc_html_e( 'Quick Product Manager requires WooCommerce to be installed and active.', 'quick-product-manager' ); ?></p>
		</div>
		<?php
	}
}

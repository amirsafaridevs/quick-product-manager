<?php
/**
 * Admin menu and assets.
 *
 * @package QuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QPM_Admin_Menu
 */
class QPM_Admin_Menu {

	/**
	 * Menu slug.
	 */
	const PAGE_SLUG = 'quick-product-manager';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register WooCommerce submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Quick Product Manager', 'quick-product-manager' ),
			__( 'Quick Products', 'quick-product-manager' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quick-product-manager' ) );
		}

		$categories = $this->get_term_options( 'product_cat' );
		$tags       = $this->get_term_options( 'product_tag' );

		include QPM_PLUGIN_DIR . 'admin/views/page.php';
	}

	/**
	 * Enqueue scripts on plugin page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'qpm-admin-style',
			QPM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QPM_VERSION
		);

		wp_enqueue_script(
			'qpm-admin-script',
			QPM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-api-fetch' ),
			QPM_VERSION,
			true
		);

		wp_localize_script(
			'qpm-admin-script',
			'qpmAdmin',
			array(
				'restUrl'   => rest_url( QPM_REST_NAMESPACE ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'perPage'   => QPM_Product_Query::DEFAULT_PER_PAGE,
				'i18n'      => array(
					'saveChanges'      => __( 'Save changes', 'quick-product-manager' ),
					'saving'           => __( 'Saving…', 'quick-product-manager' ),
					'saved'            => __( 'Changes saved.', 'quick-product-manager' ),
					'savedCount'       => __( '%d product(s) updated.', 'quick-product-manager' ),
					'failedCount'      => __( '%d product(s) failed to update.', 'quick-product-manager' ),
					'loadError'        => __( 'Could not load products.', 'quick-product-manager' ),
					'saveError'        => __( 'Could not save changes.', 'quick-product-manager' ),
					'noChanges'        => __( 'No changes to save.', 'quick-product-manager' ),
					'search'           => __( 'Search products…', 'quick-product-manager' ),
					'clearFilters'     => __( 'Clear filters', 'quick-product-manager' ),
					'allTypes'         => __( 'All types', 'quick-product-manager' ),
					'allCategories'    => __( 'All categories', 'quick-product-manager' ),
					'allTags'          => __( 'All tags', 'quick-product-manager' ),
					'allStock'         => __( 'All stock statuses', 'quick-product-manager' ),
					'allVisibility'    => __( 'All visibility', 'quick-product-manager' ),
					'loading'          => __( 'Loading…', 'quick-product-manager' ),
					'noResults'        => __( 'No products found.', 'quick-product-manager' ),
					'manageStock'      => __( 'Manage stock', 'quick-product-manager' ),
					'saleFrom'         => __( 'Sale start', 'quick-product-manager' ),
					'saleTo'           => __( 'Sale end', 'quick-product-manager' ),
					'typeSimple'       => __( 'Simple', 'quick-product-manager' ),
					'typeVariable'     => __( 'Variable', 'quick-product-manager' ),
					'typeGrouped'      => __( 'Grouped', 'quick-product-manager' ),
					'typeExternal'     => __( 'External', 'quick-product-manager' ),
					'stockIn'          => __( 'In stock', 'quick-product-manager' ),
					'stockOut'         => __( 'Out of stock', 'quick-product-manager' ),
					'stockBackorder'   => __( 'On backorder', 'quick-product-manager' ),
					'visVisible'       => __( 'Shop and search results', 'quick-product-manager' ),
					'visCatalog'       => __( 'Shop only', 'quick-product-manager' ),
					'visSearch'        => __( 'Search results only', 'quick-product-manager' ),
					'visHidden'        => __( 'Hidden', 'quick-product-manager' ),
				),
			)
		);
	}

	/**
	 * Get hierarchical term options for selects.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function get_term_options( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();
		foreach ( $terms as $term ) {
			$options[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'depth' => $this->get_term_depth( $term, $terms ),
			);
		}

		return $options;
	}

	/**
	 * Term depth for indentation.
	 *
	 * @param WP_Term $term  Term.
	 * @param array   $terms All terms.
	 * @return int
	 */
	private function get_term_depth( $term, $terms ) {
		$by_id = array();
		foreach ( $terms as $t ) {
			$by_id[ (int) $t->term_id ] = $t;
		}

		$depth  = 0;
		$parent = (int) $term->parent;

		while ( $parent && isset( $by_id[ $parent ] ) ) {
			++$depth;
			$parent = (int) $by_id[ $parent ]->parent;
		}

		return $depth;
	}
}

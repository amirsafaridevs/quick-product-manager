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
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Add body class on plugin admin screen.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen detection only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' qpm-admin-page';
		}
		return $classes;
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
		$brand_taxonomy   = QPM_Product_Query::get_brand_taxonomy();
		$brands           = $brand_taxonomy ? $this->get_term_options( $brand_taxonomy ) : array();
		$product_statuses = $this->get_product_status_options();

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
				'restUrl'      => rest_url( QPM_REST_NAMESPACE ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'perPage'      => QPM_Product_Query::DEFAULT_PER_PAGE,
				'batchSize'    => 10,
				'defaultImage'   => QPM_PLUGIN_URL . 'assets/img/default-product-img.webp',
				'brandTaxonomy'  => QPM_Product_Query::get_brand_taxonomy(),
				'i18n'           => array(
					'savingProgress'   => __( 'Saving changes…', 'quick-product-manager' ),
					/* translators: %d: save progress percentage. */
					'progressPercent'  => __( '%d%% complete', 'quick-product-manager' ),
					'saveChanges'      => __( 'Save changes', 'quick-product-manager' ),
					'saving'           => __( 'Saving…', 'quick-product-manager' ),
					'saved'            => __( 'Changes saved.', 'quick-product-manager' ),
					/* translators: %d: number of products successfully updated. */
					'savedCount'       => __( '%d product(s) updated.', 'quick-product-manager' ),
					/* translators: %d: number of products that failed to update. */
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
					'allBrands'        => __( 'All brands', 'quick-product-manager' ),
					'allStatuses'      => __( 'All statuses', 'quick-product-manager' ),
					'quantity'         => __( 'Quantity', 'quick-product-manager' ),
					'selectAllTooltip' => __( 'Select all', 'quick-product-manager' ),
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
					'bulkEdit'         => __( 'Bulk edit', 'quick-product-manager' ),
					'bulkEditTitle'    => __( 'Bulk edit selected products', 'quick-product-manager' ),
					'bulkApply'        => __( 'Apply to selected', 'quick-product-manager' ),
					'bulkCancel'       => __( 'Cancel', 'quick-product-manager' ),
					'selectAll'        => __( 'Select all matching filters', 'quick-product-manager' ),
					'selectingAll'     => __( 'Selecting all matching products…', 'quick-product-manager' ),
					'selectAllError'   => __( 'Could not select all matching products.', 'quick-product-manager' ),
					/* translators: %d: number of selected products. */
					'selectedCount'    => __( '%d selected', 'quick-product-manager' ),
					'noSelection'      => __( 'Select at least one product.', 'quick-product-manager' ),
					'noBulkFields'     => __( 'Enable at least one field to update.', 'quick-product-manager' ),
					'regularPrice'     => __( 'Regular price', 'quick-product-manager' ),
					'salePrice'        => __( 'Sale price', 'quick-product-manager' ),
					'modeFixed'        => __( 'Fixed amount', 'quick-product-manager' ),
					'modePercent'      => __( 'Percentage', 'quick-product-manager' ),
					'modeSet'          => __( 'Set value', 'quick-product-manager' ),
					'increase'         => __( 'Increase', 'quick-product-manager' ),
					'decrease'         => __( 'Decrease', 'quick-product-manager' ),
					'enable'           => __( 'Update this field', 'quick-product-manager' ),
					'on'               => __( 'On', 'quick-product-manager' ),
					'off'              => __( 'Off', 'quick-product-manager' ),
				),
			)
		);
	}

	/**
	 * Product post status labels for the status filter.
	 *
	 * @return array<string, string>
	 */
	private function get_product_status_options() {
		if ( function_exists( 'wc_get_product_statuses' ) ) {
			$statuses = wc_get_product_statuses();
		} else {
			$statuses = array(
				'publish' => __( 'Published', 'quick-product-manager' ),
				'draft'   => __( 'Draft', 'quick-product-manager' ),
				'pending' => __( 'Pending review', 'quick-product-manager' ),
				'private' => __( 'Private', 'quick-product-manager' ),
				'future'  => __( 'Scheduled', 'quick-product-manager' ),
			);
		}

		$allowed = QPM_Product_Query::get_filterable_post_statuses();
		$options = array();

		foreach ( $allowed as $slug ) {
			if ( isset( $statuses[ $slug ] ) ) {
				$options[ $slug ] = $statuses[ $slug ];
			}
		}

		return $options;
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

<?php
/**
 * Admin menu and assets.
 *
 * @package ASDevsQuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ASDevs_QPM_Admin_Menu
 */
class ASDevs_QPM_Admin_Menu {

	/**
	 * Menu slug.
	 */
	const PAGE_SLUG = 'asdevs-quick-product-manager';

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
			$classes .= ' asdevs-qpm-admin-page';
		}
		return $classes;
	}

	/**
	 * Register WooCommerce submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'ASDevs Quick Product Manager', 'asdevs-quick-product-manager' ),
			__( 'Quick Products', 'asdevs-quick-product-manager' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'asdevs-quick-product-manager' ) );
		}

		$categories = $this->get_term_options( 'product_cat' );
		$brand_taxonomy   = ASDevs_QPM_Product_Query::get_brand_taxonomy();
		$brands           = $brand_taxonomy ? $this->get_term_options( $brand_taxonomy ) : array();
		$product_statuses = $this->get_product_status_options();

		include ASDEVS_QPM_PLUGIN_DIR . 'admin/views/page.php';
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
			'asdevs-qpm-admin-style',
			ASDEVS_QPM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ASDEVS_QPM_VERSION
		);

		wp_enqueue_script(
			'asdevs-qpm-admin-script',
			ASDEVS_QPM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-api-fetch' ),
			ASDEVS_QPM_VERSION,
			true
		);

		wp_localize_script(
			'asdevs-qpm-admin-script',
			'asdevsQpmAdmin',
			array(
				'restUrl'      => rest_url( ASDEVS_QPM_REST_NAMESPACE ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'perPage'      => ASDevs_QPM_Product_Query::DEFAULT_PER_PAGE,
				'batchSize'    => 10,
				'defaultImage'   => ASDEVS_QPM_PLUGIN_URL . 'assets/img/default-product-img.webp',
				'brandTaxonomy'  => ASDevs_QPM_Product_Query::get_brand_taxonomy(),
				'i18n'           => array(
					'savingProgress'   => __( 'Saving changes…', 'asdevs-quick-product-manager' ),
					/* translators: %d: save progress percentage. */
					'progressPercent'  => __( '%d%% complete', 'asdevs-quick-product-manager' ),
					'saveChanges'      => __( 'Save changes', 'asdevs-quick-product-manager' ),
					'saving'           => __( 'Saving…', 'asdevs-quick-product-manager' ),
					'saved'            => __( 'Changes saved.', 'asdevs-quick-product-manager' ),
					/* translators: %d: number of products successfully updated. */
					'savedCount'       => __( '%d product(s) updated.', 'asdevs-quick-product-manager' ),
					/* translators: %d: number of products that failed to update. */
					'failedCount'      => __( '%d product(s) failed to update.', 'asdevs-quick-product-manager' ),
					'loadError'        => __( 'Could not load products.', 'asdevs-quick-product-manager' ),
					'saveError'        => __( 'Could not save changes.', 'asdevs-quick-product-manager' ),
					'noChanges'        => __( 'No changes to save.', 'asdevs-quick-product-manager' ),
					'search'           => __( 'Search products…', 'asdevs-quick-product-manager' ),
					'clearFilters'     => __( 'Clear filters', 'asdevs-quick-product-manager' ),
					'allTypes'         => __( 'All types', 'asdevs-quick-product-manager' ),
					'allCategories'    => __( 'All categories', 'asdevs-quick-product-manager' ),
					'allTags'          => __( 'All tags', 'asdevs-quick-product-manager' ),
					'allStock'         => __( 'All stock statuses', 'asdevs-quick-product-manager' ),
					'allBrands'        => __( 'All brands', 'asdevs-quick-product-manager' ),
					'allStatuses'      => __( 'All statuses', 'asdevs-quick-product-manager' ),
					'quantity'         => __( 'Quantity', 'asdevs-quick-product-manager' ),
					'selectAllTooltip' => __( 'Select all', 'asdevs-quick-product-manager' ),
					'loading'          => __( 'Loading…', 'asdevs-quick-product-manager' ),
					'noResults'        => __( 'No products found.', 'asdevs-quick-product-manager' ),
					'manageStock'      => __( 'Manage stock', 'asdevs-quick-product-manager' ),
					'saleFrom'         => __( 'Sale start', 'asdevs-quick-product-manager' ),
					'saleTo'           => __( 'Sale end', 'asdevs-quick-product-manager' ),
					'typeSimple'       => __( 'Simple', 'asdevs-quick-product-manager' ),
					'typeVariable'     => __( 'Variable', 'asdevs-quick-product-manager' ),
					'typeGrouped'      => __( 'Grouped', 'asdevs-quick-product-manager' ),
					'typeExternal'     => __( 'External', 'asdevs-quick-product-manager' ),
					'stockIn'          => __( 'In stock', 'asdevs-quick-product-manager' ),
					'stockOut'         => __( 'Out of stock', 'asdevs-quick-product-manager' ),
					'stockBackorder'   => __( 'On backorder', 'asdevs-quick-product-manager' ),
					'visVisible'       => __( 'Shop and search results', 'asdevs-quick-product-manager' ),
					'visCatalog'       => __( 'Shop only', 'asdevs-quick-product-manager' ),
					'visSearch'        => __( 'Search results only', 'asdevs-quick-product-manager' ),
					'visHidden'        => __( 'Hidden', 'asdevs-quick-product-manager' ),
					'bulkEdit'         => __( 'Bulk edit', 'asdevs-quick-product-manager' ),
					'bulkEditTitle'    => __( 'Bulk edit selected products', 'asdevs-quick-product-manager' ),
					'bulkApply'        => __( 'Apply to selected', 'asdevs-quick-product-manager' ),
					'bulkCancel'       => __( 'Cancel', 'asdevs-quick-product-manager' ),
					'selectAll'        => __( 'Select all matching filters', 'asdevs-quick-product-manager' ),
					'selectingAll'     => __( 'Selecting all matching products…', 'asdevs-quick-product-manager' ),
					'selectAllError'   => __( 'Could not select all matching products.', 'asdevs-quick-product-manager' ),
					/* translators: %d: number of selected products. */
					'selectedCount'    => __( '%d selected', 'asdevs-quick-product-manager' ),
					'noSelection'      => __( 'Select at least one product.', 'asdevs-quick-product-manager' ),
					'noBulkFields'     => __( 'Enable at least one field to update.', 'asdevs-quick-product-manager' ),
					'regularPrice'     => __( 'Regular price', 'asdevs-quick-product-manager' ),
					'salePrice'        => __( 'Sale price', 'asdevs-quick-product-manager' ),
					'modeFixed'        => __( 'Fixed amount', 'asdevs-quick-product-manager' ),
					'modePercent'      => __( 'Percentage', 'asdevs-quick-product-manager' ),
					'modeSet'          => __( 'Set value', 'asdevs-quick-product-manager' ),
					'increase'         => __( 'Increase', 'asdevs-quick-product-manager' ),
					'decrease'         => __( 'Decrease', 'asdevs-quick-product-manager' ),
					'enable'           => __( 'Update this field', 'asdevs-quick-product-manager' ),
					'on'               => __( 'On', 'asdevs-quick-product-manager' ),
					'off'              => __( 'Off', 'asdevs-quick-product-manager' ),
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
				'publish' => __( 'Published', 'asdevs-quick-product-manager' ),
				'draft'   => __( 'Draft', 'asdevs-quick-product-manager' ),
				'pending' => __( 'Pending review', 'asdevs-quick-product-manager' ),
				'private' => __( 'Private', 'asdevs-quick-product-manager' ),
				'future'  => __( 'Scheduled', 'asdevs-quick-product-manager' ),
			);
		}

		$allowed = ASDevs_QPM_Product_Query::get_filterable_post_statuses();
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

<?php
/**
 * REST API controller.
 *
 * @package QuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QPM_REST_Controller
 */
class QPM_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			QPM_REST_NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_collection_params(),
			)
		);

		register_rest_route(
			QPM_REST_NAMESPACE,
			'/products/selectable',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_selectable' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_filter_params(),
			)
		);

		register_rest_route(
			QPM_REST_NAMESPACE,
			'/products/batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'batch_update' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * GET /products
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_products( $request ) {
		$query  = new QPM_Product_Query();
		$result = $query->get_products( $request->get_params() );

		return rest_ensure_response( $result );
	}

	/**
	 * GET /products/selectable — all editable rows for current filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_selectable( $request ) {
		$query  = new QPM_Product_Query();
		$result = $query->get_selectable_rows( $request->get_params() );

		return rest_ensure_response( $result );
	}

	/**
	 * POST /products/batch
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function batch_update( $request ) {
		$body    = $request->get_json_params();
		$changes = isset( $body['changes'] ) ? $body['changes'] : array();

		$updater = new QPM_Product_Updater();
		$result  = $updater->apply_batch( $changes );

		return rest_ensure_response( $result );
	}

	/**
	 * Collection query params.
	 *
	 * @return array
	 */
	private function get_filter_params() {
		return array(
			'search'              => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type'                => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category'            => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'stock_status'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'brand'               => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'brand_taxonomy'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_status'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Collection query params.
	 *
	 * @return array
	 */
	private function get_collection_params() {
		return array_merge(
			$this->get_filter_params(),
			array(
			'page'                => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'            => array(
				'type'              => 'integer',
				'default'           => QPM_Product_Query::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
			)
		);
	}
}

<?php
/**
 * Product list query for REST.
 *
 * @package QuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QPM_Product_Query
 */
class QPM_Product_Query {

	/**
	 * Default items per page (products, not rows).
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Query products and build flat rows.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	public function get_products( $params ) {
		$page     = max( 1, absint( $params['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $params['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );

		$args = $this->build_wc_query_args( $params );
		$args['limit']    = $per_page;
		$args['page']     = $page;
		$args['paginate'] = true;

		$result = wc_get_products( $args );

		$items = array();

		if ( ! empty( $result->products ) ) {
			foreach ( $result->products as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				$rows = $this->product_to_rows( $product );
				foreach ( $rows as $row ) {
					$items[] = apply_filters( 'qpm_product_row', $row, $product );
				}
			}
		}

		return array(
			'items'        => $items,
			'total'        => (int) $result->total,
			'total_pages'  => (int) $result->max_num_pages,
			'page'         => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * All editable rows matching filters (ignores pagination).
	 *
	 * @param array $params Request params (filters only).
	 * @return array{ items: array[], total: int }
	 */
	public function get_selectable_rows( $params ) {
		$args = $this->build_wc_query_args( $params );
		$args['limit']    = -1;
		$args['paginate'] = false;
		unset( $args['page'] );

		$products = wc_get_products( $args );
		$items    = array();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				$rows = $this->product_to_rows( $product );
				foreach ( $rows as $row ) {
					$row = apply_filters( 'qpm_product_row', $row, $product );
					if ( empty( $row['readonly'] ) ) {
						$items[] = $row;
					}
				}
			}
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
		);
	}

	/**
	 * Base WooCommerce product query args from request filters.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	private function build_wc_query_args( $params ) {
		$args = array(
			'orderby'  => 'title',
			'order'    => 'ASC',
			'return'   => 'objects',
			'status'   => $this->resolve_status_filter( $params ),
		);

		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		if ( ! empty( $params['type'] ) ) {
			$type = sanitize_text_field( $params['type'] );
			if ( in_array( $type, array( 'simple', 'variable', 'grouped', 'external' ), true ) ) {
				$args['type'] = array( $type );
			}
		}

		if ( ! empty( $params['category'] ) ) {
			$term_id  = absint( $params['category'] );
			$term_ids = array( $term_id );

			$children = get_term_children( $term_id, 'product_cat' );
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$term_ids = array_merge( $term_ids, array_map( 'absint', $children ) );
			}

			$args['product_category_id'] = array_unique( $term_ids );
		}

		if ( ! empty( $params['stock_status'] ) ) {
			$status = sanitize_text_field( $params['stock_status'] );
			if ( in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$args['stock_status'] = $status;
			}
		}

		if ( ! empty( $params['brand'] ) ) {
			$taxonomy = self::resolve_brand_taxonomy( $params );
			$brand_id = absint( $params['brand'] );
			if ( $brand_id && $taxonomy ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( $brand_id ),
					),
				);
			}
		}

		return $args;
	}

	/**
	 * Convert product to one or more row DTOs.
	 *
	 * @param WC_Product $product Product.
	 * @return array[]
	 */
	private function product_to_rows( $product ) {
		$rows = array();

		if ( $product->is_type( 'variable' ) ) {
			$rows[] = $this->build_row_dto( $product, 'parent', true );

			$children = $product->get_children();
			foreach ( $children as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation && $variation->is_type( 'variation' ) ) {
					$rows[] = $this->build_row_dto( $variation, 'variation', false );
				}
			}
			return $rows;
		}

		$rows[] = $this->build_row_dto( $product, 'product', false );
		return $rows;
	}

	/**
	 * Build row DTO from product.
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $row_type  parent|product|variation.
	 * @param bool       $readonly  Non-editable row.
	 * @return array
	 */
	private function build_row_dto( $product, $row_type, $readonly ) {
		$parent_id = $product->get_parent_id();
		$group_id  = $parent_id ? $parent_id : $product->get_id();

		if ( 'parent' === $row_type ) {
			$group_id = $product->get_id();
		}

		$title = $product->get_name();

		if ( 'variation' === $row_type && $product->is_type( 'variation' ) ) {
			$formatted = wc_get_formatted_variation( $product, true, false, true );
			if ( $formatted ) {
				$title = $formatted;
			}
		}

		$sale_from = $product->get_date_on_sale_from();
		$sale_to   = $product->get_date_on_sale_to();

		$edit_url = get_edit_post_link( $product->get_id(), 'raw' );
		if ( ! $edit_url ) {
			$edit_url = admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' );
		}

		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent_id ) {
			$parent_product = wc_get_product( $parent_id );
			if ( $parent_product ) {
				$image_id = $parent_product->get_image_id();
			}
		}
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		$post_status       = $product->get_status();
		$status_obj        = get_post_status_object( $post_status );
		$post_status_label = ( $status_obj && ! empty( $status_obj->label ) ) ? $status_obj->label : $post_status;

		return array(
			'id'                 => $product->get_id(),
			'parent_id'          => $parent_id,
			'group_id'           => $group_id,
			'row_type'           => $row_type,
			'readonly'           => $readonly,
			'title'              => $title,
			'edit_url'           => $edit_url,
			'image_url'          => $image_url ? $image_url : '',
			'sku'                => $product->get_sku( 'edit' ),
			'manage_stock'       => $product->get_manage_stock( 'edit' ),
			'stock_quantity'     => $product->get_stock_quantity( 'edit' ),
			'stock_status'       => $product->get_stock_status( 'edit' ),
			'regular_price'      => $product->get_regular_price( 'edit' ),
			'sale_price'         => $product->get_sale_price( 'edit' ),
			'date_on_sale_from'  => $sale_from ? $sale_from->date( 'Y-m-d' ) : '',
			'date_on_sale_to'    => $sale_to ? $sale_to->date( 'Y-m-d' ) : '',
			'product_type'       => $product->get_type(),
			'post_status'        => $post_status,
			'post_status_label'  => $post_status_label,
		);
	}

	/**
	 * Allowed post statuses for the status filter.
	 *
	 * @return string[]
	 */
	public static function get_filterable_post_statuses() {
		return array( 'publish', 'draft', 'pending', 'private', 'future' );
	}

	/**
	 * Detect the product brand taxonomy used on this site.
	 *
	 * @return string Empty if none found.
	 */
	public static function get_brand_taxonomy() {
		$candidates = array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'brand', 'pa_brand' );

		foreach ( $candidates as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Resolve brand taxonomy from request (falls back to site default).
	 *
	 * @param array $params Request params.
	 * @return string
	 */
	private static function resolve_brand_taxonomy( $params ) {
		if ( ! empty( $params['brand_taxonomy'] ) ) {
			$taxonomy = sanitize_text_field( $params['brand_taxonomy'] );
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return self::get_brand_taxonomy();
	}

	/**
	 * Post statuses included when the filter is "All statuses".
	 *
	 * @return string[]
	 */
	public static function get_all_post_statuses() {
		$statuses = array();

		foreach ( self::get_filterable_post_statuses() as $slug ) {
			if ( self::user_can_view_product_status( $slug ) ) {
				$statuses[] = $slug;
			}
		}

		return $statuses;
	}

	/**
	 * Whether the current user may list products with the given status.
	 *
	 * @param string $status Post status slug.
	 * @return bool
	 */
	private static function user_can_view_product_status( $status ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		switch ( $status ) {
			case 'publish':
				return current_user_can( 'edit_products' );
			case 'private':
				return current_user_can( 'read_private_products' );
			case 'draft':
			case 'pending':
			case 'future':
				return current_user_can( 'edit_products' );
			default:
				return false;
		}
	}

	/**
	 * Resolve WC product status query from request params.
	 *
	 * @param array $params Request params.
	 * @return string[]
	 */
	private function resolve_status_filter( $params ) {
		if ( empty( $params['post_status'] ) ) {
			return self::get_all_post_statuses();
		}

		$post_status = sanitize_text_field( $params['post_status'] );
		if ( in_array( $post_status, self::get_filterable_post_statuses(), true ) ) {
			if ( self::user_can_view_product_status( $post_status ) ) {
				return array( $post_status );
			}
			return array();
		}

		return self::get_all_post_statuses();
	}
}

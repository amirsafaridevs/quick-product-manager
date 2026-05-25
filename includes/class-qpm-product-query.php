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
	const DEFAULT_PER_PAGE = 30;

	/**
	 * Query products and build flat rows.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	public function get_products( $params ) {
		$page     = max( 1, absint( $params['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $params['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'title',
			'order'    => 'ASC',
			'return'   => 'objects',
			'status'   => array( 'publish' ),
		);

		if ( current_user_can( 'manage_woocommerce' ) ) {
			$args['status'][] = 'private';
		}

		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		if ( ! empty( $params['type'] ) ) {
			$type = sanitize_text_field( $params['type'] );
			if ( in_array( $type, array( 'simple', 'variable', 'grouped', 'external' ), true ) ) {
				$args['type'] = $type;
			}
		}

		if ( ! empty( $params['category'] ) ) {
			$args['category'] = array( absint( $params['category'] ) );
		}

		if ( ! empty( $params['tag'] ) ) {
			$args['tag'] = array( absint( $params['tag'] ) );
		}

		if ( ! empty( $params['stock_status'] ) ) {
			$status = sanitize_text_field( $params['stock_status'] );
			if ( in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$args['stock_status'] = $status;
			}
		}

		if ( ! empty( $params['catalog_visibility'] ) ) {
			$visibility = sanitize_text_field( $params['catalog_visibility'] );
			if ( in_array( $visibility, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
				$args['catalog_visibility'] = $visibility;
			}
		}

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

		return array(
			'id'                 => $product->get_id(),
			'parent_id'          => $parent_id,
			'group_id'           => $group_id,
			'row_type'           => $row_type,
			'readonly'           => $readonly,
			'title'              => $title,
			'sku'                => $product->get_sku( 'edit' ),
			'manage_stock'       => $product->get_manage_stock( 'edit' ),
			'stock_quantity'     => $product->get_stock_quantity( 'edit' ),
			'stock_status'       => $product->get_stock_status( 'edit' ),
			'regular_price'      => $product->get_regular_price( 'edit' ),
			'sale_price'         => $product->get_sale_price( 'edit' ),
			'date_on_sale_from'  => $sale_from ? $sale_from->date( 'Y-m-d' ) : '',
			'date_on_sale_to'    => $sale_to ? $sale_to->date( 'Y-m-d' ) : '',
			'product_type'       => $product->get_type(),
		);
	}
}

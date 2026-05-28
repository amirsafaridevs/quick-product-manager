<?php
/**
 * Batch product updates.
 *
 * @package ASDevsQuickProductManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ASDevs_QPM_Product_Updater
 */
class ASDevs_QPM_Product_Updater {

	/**
	 * Apply batch changes.
	 *
	 * @param array $changes Array of change objects.
	 * @return array
	 */
	public function apply_batch( $changes ) {
		$updated = array();
		$failed  = array();

		if ( ! is_array( $changes ) ) {
			return array(
				'updated' => $updated,
				'failed'  => array(
					array(
						'id'      => 0,
						'message' => __( 'Invalid request body.', 'asdevs-quick-product-manager' ),
					),
				),
			);
		}

		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) || empty( $change['id'] ) ) {
				continue;
			}

			$id      = absint( $change['id'] );
			$product = wc_get_product( $id );

			if ( ! $product ) {
				$failed[] = array(
					'id'      => $id,
					'message' => __( 'Product not found.', 'asdevs-quick-product-manager' ),
				);
				continue;
			}

			if ( ! $this->is_editable( $product ) ) {
				$failed[] = array(
					'id'      => $id,
					'message' => __( 'This product row cannot be edited.', 'asdevs-quick-product-manager' ),
				);
				continue;
			}

			try {
				/**
				 * Fires before a product is updated via ASDevs Quick Product Manager.
				 *
				 * @param WC_Product $product Product object.
				 * @param array      $change  Change payload.
				 */
				do_action( 'asdevs_qpm_before_product_save', $product, $change );

				$this->apply_change( $product, $change );
				$product->save();

				$updated[] = $id;
			} catch ( Exception $e ) {
				$failed[] = array(
					'id'      => $id,
					'message' => $e->getMessage(),
				);
			}
		}

		return array(
			'updated' => $updated,
			'failed'  => $failed,
		);
	}

	/**
	 * Whether product can be edited in the table.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private function is_editable( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			return true;
		}
		if ( $product->is_type( 'simple' ) || $product->is_type( 'external' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Apply partial change to product.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $change  Change fields.
	 */
	private function apply_change( $product, $change ) {
		if ( array_key_exists( 'sku', $change ) ) {
			$product->set_sku( wc_clean( $change['sku'] ) );
		}

		if ( array_key_exists( 'manage_stock', $change ) ) {
			$product->set_manage_stock( (bool) $change['manage_stock'] );
		}

		if ( array_key_exists( 'stock_quantity', $change ) ) {
			$qty = '' === $change['stock_quantity'] || null === $change['stock_quantity']
				? null
				: wc_stock_amount( $change['stock_quantity'] );
			$product->set_stock_quantity( $qty );
		}

		if ( array_key_exists( 'regular_price', $change ) ) {
			$product->set_regular_price( wc_format_decimal( $change['regular_price'] ) );
		}

		if ( array_key_exists( 'sale_price', $change ) ) {
			$sale = $change['sale_price'];
			if ( '' === $sale || null === $sale ) {
				$product->set_sale_price( '' );
				$product->set_date_on_sale_from( null );
				$product->set_date_on_sale_to( null );
			} else {
				$product->set_sale_price( wc_format_decimal( $sale ) );
			}
		}

		if ( array_key_exists( 'date_on_sale_from', $change ) ) {
			$this->set_sale_date( $product, 'from', $change['date_on_sale_from'] );
		}

		if ( array_key_exists( 'date_on_sale_to', $change ) ) {
			$this->set_sale_date( $product, 'to', $change['date_on_sale_to'] );
		}

		if ( $product->get_manage_stock() ) {
			$product->set_stock_status( $product->get_stock_quantity() > 0 ? 'instock' : 'outofstock' );
		}
	}

	/**
	 * Set sale schedule date.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $which   from|to.
	 * @param string     $value   Date Y-m-d or empty.
	 */
	private function set_sale_date( $product, $which, $value ) {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			if ( 'from' === $which ) {
				$product->set_date_on_sale_from( null );
			} else {
				$product->set_date_on_sale_to( null );
			}
			return;
		}

		$timestamp = strtotime( $value . ' 00:00:00' );
		if ( ! $timestamp ) {
			return;
		}

		$datetime = new WC_DateTime( '@' . $timestamp, new DateTimeZone( 'UTC' ) );
		$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );

		if ( 'from' === $which ) {
			$product->set_date_on_sale_from( $datetime );
		} else {
			$product->set_date_on_sale_to( $datetime );
		}
	}
}

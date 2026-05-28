<?php
/**
 * Admin page template.
 *
 * @package ASDevsQuickProductManager
 * @var array $categories Category options.
 * @var array $brands            Brand options.
 * @var array $product_statuses  Post status options (slug => label).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap asdevs-qpm-wrap">
	<h1 class="asdevs-qpm-page-title"><?php esc_html_e( 'ASDevs Quick Product Manager', 'asdevs-quick-product-manager' ); ?></h1>

	<div id="asdevs-qpm-notice" class="asdevs-qpm-notice" role="status" aria-live="polite" hidden></div>

	<div id="asdevs-qpm-app" class="asdevs-qpm-app">
		<div class="asdevs-qpm-toolbar">
			<label class="asdevs-qpm-mdc-check asdevs-qpm-toolbar__select-all">
				<input
					type="checkbox"
					id="asdevs-qpm-select-all"
					title="<?php esc_attr_e( 'Select all', 'asdevs-quick-product-manager' ); ?>"
				/>
			</label>

			<label class="asdevs-qpm-mdc-field asdevs-qpm-mdc-field--search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'asdevs-quick-product-manager' ); ?></span>
				<input type="search" id="asdevs-qpm-search" class="asdevs-qpm-mdc-input" placeholder="<?php esc_attr_e( 'Search products…', 'asdevs-quick-product-manager' ); ?>" autocomplete="off" />
			</label>

			<select id="asdevs-qpm-filter-type" class="asdevs-qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All types', 'asdevs-quick-product-manager' ); ?></option>
				<option value="simple"><?php esc_html_e( 'Simple', 'asdevs-quick-product-manager' ); ?></option>
				<option value="variable"><?php esc_html_e( 'Variable', 'asdevs-quick-product-manager' ); ?></option>
				<option value="grouped"><?php esc_html_e( 'Grouped', 'asdevs-quick-product-manager' ); ?></option>
				<option value="external"><?php esc_html_e( 'External', 'asdevs-quick-product-manager' ); ?></option>
			</select>

			<select id="asdevs-qpm-filter-category" class="asdevs-qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All categories', 'asdevs-quick-product-manager' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat['id'] ); ?>">
						<?php echo esc_html( str_repeat( '— ', (int) $cat['depth'] ) . $cat['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="asdevs-qpm-filter-stock" class="asdevs-qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All stock', 'asdevs-quick-product-manager' ); ?></option>
				<option value="instock"><?php esc_html_e( 'In stock', 'asdevs-quick-product-manager' ); ?></option>
				<option value="outofstock"><?php esc_html_e( 'Out of stock', 'asdevs-quick-product-manager' ); ?></option>
				<option value="onbackorder"><?php esc_html_e( 'On backorder', 'asdevs-quick-product-manager' ); ?></option>
			</select>

			<select id="asdevs-qpm-filter-status" class="asdevs-qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All statuses', 'asdevs-quick-product-manager' ); ?></option>
				<?php foreach ( $product_statuses as $status_slug => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_slug ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="asdevs-qpm-filter-brand" class="asdevs-qpm-mdc-select"<?php echo empty( $brands ) ? ' disabled' : ''; ?>>
				<option value=""><?php esc_html_e( 'All brands', 'asdevs-quick-product-manager' ); ?></option>
				<?php foreach ( $brands as $brand ) : ?>
					<option value="<?php echo esc_attr( $brand['id'] ); ?>">
						<?php echo esc_html( $brand['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="button" id="asdevs-qpm-clear-filters" class="asdevs-qpm-mdc-btn asdevs-qpm-mdc-btn--text">
				<?php esc_html_e( 'Clear', 'asdevs-quick-product-manager' ); ?>
			</button>

			<div class="asdevs-qpm-toolbar__spacer" aria-hidden="true"></div>

			<span id="asdevs-qpm-selected-count" class="asdevs-qpm-selected-count" hidden></span>
			<button type="button" id="asdevs-qpm-bulk-edit" class="asdevs-qpm-mdc-btn asdevs-qpm-mdc-btn--tonal" disabled>
				<?php esc_html_e( 'Bulk edit', 'asdevs-quick-product-manager' ); ?>
			</button>
			<button type="button" id="asdevs-qpm-save" class="asdevs-qpm-mdc-btn asdevs-qpm-mdc-btn--filled" disabled>
				<?php esc_html_e( 'Save changes', 'asdevs-quick-product-manager' ); ?>
			</button>
		</div>

		<div class="asdevs-qpm-list-panel">
			<div id="asdevs-qpm-list" class="asdevs-qpm-list">
				<div class="asdevs-qpm-list__state"><?php esc_html_e( 'Loading…', 'asdevs-quick-product-manager' ); ?></div>
			</div>
			<div id="asdevs-qpm-scroll-sentinel" class="asdevs-qpm-scroll-sentinel" aria-hidden="true"></div>
		</div>
	</div>

	<!-- Progress overlay -->
	<div id="asdevs-qpm-overlay" class="asdevs-qpm-overlay" hidden>
		<div class="asdevs-qpm-mdc-dialog asdevs-qpm-mdc-dialog--progress" role="dialog" aria-modal="true" aria-labelledby="asdevs-qpm-modal-title">
			<h2 id="asdevs-qpm-modal-title" class="asdevs-qpm-mdc-dialog__title"><?php esc_html_e( 'Saving changes…', 'asdevs-quick-product-manager' ); ?></h2>
			<div class="asdevs-qpm-mdc-linear-progress" aria-hidden="true">
				<div id="asdevs-qpm-progress-bar" class="asdevs-qpm-mdc-linear-progress__bar" style="width: 0%"></div>
			</div>
			<p id="asdevs-qpm-progress-text" class="asdevs-qpm-mdc-dialog__subtitle">0%</p>
		</div>
	</div>

	<!-- Bulk edit dialog -->
	<div id="asdevs-qpm-bulk-overlay" class="asdevs-qpm-overlay" hidden>
		<div class="asdevs-qpm-mdc-dialog asdevs-qpm-mdc-dialog--bulk" role="dialog" aria-modal="true" aria-labelledby="asdevs-qpm-bulk-title">
			<h2 id="asdevs-qpm-bulk-title" class="asdevs-qpm-mdc-dialog__title asdevs-qpm-mdc-dialog__title--bulk"><?php esc_html_e( 'Bulk edit selected products', 'asdevs-quick-product-manager' ); ?></h2>
			<div class="asdevs-qpm-bulk-form">
				<?php
				$bulk_fields = array(
					'manage_stock'  => __( 'Manage stock', 'asdevs-quick-product-manager' ),
					'stock'         => __( 'Quantity', 'asdevs-quick-product-manager' ),
					'regular_price' => __( 'Regular price', 'asdevs-quick-product-manager' ),
					'sale_price'    => __( 'Sale price', 'asdevs-quick-product-manager' ),
					'sale_from'     => __( 'Sale start', 'asdevs-quick-product-manager' ),
					'sale_to'       => __( 'Sale end', 'asdevs-quick-product-manager' ),
				);
				foreach ( $bulk_fields as $key => $label ) :
					$is_price = in_array( $key, array( 'regular_price', 'sale_price' ), true );
					$is_stock = 'stock' === $key;
					$is_manage = 'manage_stock' === $key;
					$is_date = in_array( $key, array( 'sale_from', 'sale_to' ), true );
					?>
					<div class="asdevs-qpm-bulk-row" data-field="<?php echo esc_attr( $key ); ?>">
						<label class="asdevs-qpm-mdc-switch asdevs-qpm-mdc-switch--sm">
							<input type="checkbox" class="asdevs-qpm-bulk-enable" data-field="<?php echo esc_attr( $key ); ?>" />
							<span class="asdevs-qpm-mdc-switch__track"></span>
						</label>
						<span class="asdevs-qpm-bulk-row__label"><?php echo esc_html( $label ); ?></span>
						<div class="asdevs-qpm-bulk-row__controls" hidden>
							<?php if ( $is_price ) : ?>
								<select class="asdevs-qpm-mdc-select asdevs-qpm-mdc-select--sm asdevs-qpm-bulk-mode" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="fixed"><?php esc_html_e( 'Fixed amount', 'asdevs-quick-product-manager' ); ?></option>
									<option value="percent"><?php esc_html_e( 'Percentage', 'asdevs-quick-product-manager' ); ?></option>
								</select>
								<select class="asdevs-qpm-mdc-select asdevs-qpm-mdc-select--sm asdevs-qpm-bulk-direction" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="increase"><?php esc_html_e( 'Increase', 'asdevs-quick-product-manager' ); ?></option>
									<option value="decrease"><?php esc_html_e( 'Decrease', 'asdevs-quick-product-manager' ); ?></option>
								</select>
								<input type="number" class="asdevs-qpm-mdc-input asdevs-qpm-mdc-input--sm asdevs-qpm-bulk-value" data-field="<?php echo esc_attr( $key ); ?>" min="0" step="0.01" placeholder="0" />
							<?php elseif ( $is_stock ) : ?>
								<input type="number" class="asdevs-qpm-mdc-input asdevs-qpm-mdc-input--sm asdevs-qpm-bulk-value" data-field="<?php echo esc_attr( $key ); ?>" min="0" step="1" placeholder="0" />
							<?php elseif ( $is_manage ) : ?>
								<select class="asdevs-qpm-mdc-select asdevs-qpm-mdc-select--sm asdevs-qpm-bulk-manage-value" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="1"><?php esc_html_e( 'On', 'asdevs-quick-product-manager' ); ?></option>
									<option value="0"><?php esc_html_e( 'Off', 'asdevs-quick-product-manager' ); ?></option>
								</select>
							<?php elseif ( $is_date ) : ?>
								<input type="date" class="asdevs-qpm-mdc-input asdevs-qpm-mdc-input--sm asdevs-qpm-bulk-date" data-field="<?php echo esc_attr( $key ); ?>" />
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="asdevs-qpm-mdc-dialog__actions asdevs-qpm-mdc-dialog__actions--bulk">
				<button type="button" id="asdevs-qpm-bulk-cancel" class="asdevs-qpm-mdc-btn asdevs-qpm-mdc-btn--text asdevs-qpm-mdc-btn--sm"><?php esc_html_e( 'Cancel', 'asdevs-quick-product-manager' ); ?></button>
				<button type="button" id="asdevs-qpm-bulk-apply" class="asdevs-qpm-mdc-btn asdevs-qpm-mdc-btn--filled asdevs-qpm-mdc-btn--sm"><?php esc_html_e( 'Apply to selected', 'asdevs-quick-product-manager' ); ?></button>
			</div>
		</div>
	</div>
</div>

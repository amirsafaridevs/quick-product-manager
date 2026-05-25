<?php
/**
 * Admin page template.
 *
 * @package QuickProductManager
 * @var array $categories Category options.
 * @var array $brands            Brand options.
 * @var array $product_statuses  Post status options (slug => label).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap qpm-wrap">
	<h1 class="qpm-page-title"><?php esc_html_e( 'Quick Product Manager', 'quick-product-manager' ); ?></h1>

	<div id="qpm-notice" class="qpm-notice" role="status" aria-live="polite" hidden></div>

	<div id="qpm-app" class="qpm-app">
		<div class="qpm-toolbar">
			<label class="qpm-mdc-check qpm-toolbar__select-all">
				<input
					type="checkbox"
					id="qpm-select-all"
					title="<?php esc_attr_e( 'Select all', 'quick-product-manager' ); ?>"
				/>
			</label>

			<label class="qpm-mdc-field qpm-mdc-field--search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'quick-product-manager' ); ?></span>
				<input type="search" id="qpm-search" class="qpm-mdc-input" placeholder="<?php esc_attr_e( 'Search products…', 'quick-product-manager' ); ?>" autocomplete="off" />
			</label>

			<select id="qpm-filter-type" class="qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All types', 'quick-product-manager' ); ?></option>
				<option value="simple"><?php esc_html_e( 'Simple', 'quick-product-manager' ); ?></option>
				<option value="variable"><?php esc_html_e( 'Variable', 'quick-product-manager' ); ?></option>
				<option value="grouped"><?php esc_html_e( 'Grouped', 'quick-product-manager' ); ?></option>
				<option value="external"><?php esc_html_e( 'External', 'quick-product-manager' ); ?></option>
			</select>

			<select id="qpm-filter-category" class="qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All categories', 'quick-product-manager' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat['id'] ); ?>">
						<?php echo esc_html( str_repeat( '— ', (int) $cat['depth'] ) . $cat['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="qpm-filter-stock" class="qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All stock', 'quick-product-manager' ); ?></option>
				<option value="instock"><?php esc_html_e( 'In stock', 'quick-product-manager' ); ?></option>
				<option value="outofstock"><?php esc_html_e( 'Out of stock', 'quick-product-manager' ); ?></option>
				<option value="onbackorder"><?php esc_html_e( 'On backorder', 'quick-product-manager' ); ?></option>
			</select>

			<select id="qpm-filter-status" class="qpm-mdc-select">
				<option value=""><?php esc_html_e( 'All statuses', 'quick-product-manager' ); ?></option>
				<?php foreach ( $product_statuses as $status_slug => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_slug ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="qpm-filter-brand" class="qpm-mdc-select"<?php echo empty( $brands ) ? ' disabled' : ''; ?>>
				<option value=""><?php esc_html_e( 'All brands', 'quick-product-manager' ); ?></option>
				<?php foreach ( $brands as $brand ) : ?>
					<option value="<?php echo esc_attr( $brand['id'] ); ?>">
						<?php echo esc_html( $brand['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="button" id="qpm-clear-filters" class="qpm-mdc-btn qpm-mdc-btn--text">
				<?php esc_html_e( 'Clear', 'quick-product-manager' ); ?>
			</button>

			<div class="qpm-toolbar__spacer" aria-hidden="true"></div>

			<span id="qpm-selected-count" class="qpm-selected-count" hidden></span>
			<button type="button" id="qpm-bulk-edit" class="qpm-mdc-btn qpm-mdc-btn--tonal" disabled>
				<?php esc_html_e( 'Bulk edit', 'quick-product-manager' ); ?>
			</button>
			<button type="button" id="qpm-save" class="qpm-mdc-btn qpm-mdc-btn--filled" disabled>
				<?php esc_html_e( 'Save changes', 'quick-product-manager' ); ?>
			</button>
		</div>

		<div class="qpm-list-panel">
			<div id="qpm-list" class="qpm-list">
				<div class="qpm-list__state"><?php esc_html_e( 'Loading…', 'quick-product-manager' ); ?></div>
			</div>
			<div id="qpm-scroll-sentinel" class="qpm-scroll-sentinel" aria-hidden="true"></div>
		</div>
	</div>

	<!-- Progress overlay -->
	<div id="qpm-overlay" class="qpm-overlay" hidden>
		<div class="qpm-mdc-dialog qpm-mdc-dialog--progress" role="dialog" aria-modal="true" aria-labelledby="qpm-modal-title">
			<h2 id="qpm-modal-title" class="qpm-mdc-dialog__title"><?php esc_html_e( 'Saving changes…', 'quick-product-manager' ); ?></h2>
			<div class="qpm-mdc-linear-progress" aria-hidden="true">
				<div id="qpm-progress-bar" class="qpm-mdc-linear-progress__bar" style="width: 0%"></div>
			</div>
			<p id="qpm-progress-text" class="qpm-mdc-dialog__subtitle">0%</p>
		</div>
	</div>

	<!-- Bulk edit dialog -->
	<div id="qpm-bulk-overlay" class="qpm-overlay" hidden>
		<div class="qpm-mdc-dialog qpm-mdc-dialog--bulk" role="dialog" aria-modal="true" aria-labelledby="qpm-bulk-title">
			<h2 id="qpm-bulk-title" class="qpm-mdc-dialog__title qpm-mdc-dialog__title--bulk"><?php esc_html_e( 'Bulk edit selected products', 'quick-product-manager' ); ?></h2>
			<div class="qpm-bulk-form">
				<?php
				$bulk_fields = array(
					'manage_stock'  => __( 'Manage stock', 'quick-product-manager' ),
					'stock'         => __( 'Quantity', 'quick-product-manager' ),
					'regular_price' => __( 'Regular price', 'quick-product-manager' ),
					'sale_price'    => __( 'Sale price', 'quick-product-manager' ),
					'sale_from'     => __( 'Sale start', 'quick-product-manager' ),
					'sale_to'       => __( 'Sale end', 'quick-product-manager' ),
				);
				foreach ( $bulk_fields as $key => $label ) :
					$is_price = in_array( $key, array( 'regular_price', 'sale_price' ), true );
					$is_stock = 'stock' === $key;
					$is_manage = 'manage_stock' === $key;
					$is_date = in_array( $key, array( 'sale_from', 'sale_to' ), true );
					?>
					<div class="qpm-bulk-row" data-field="<?php echo esc_attr( $key ); ?>">
						<label class="qpm-mdc-switch qpm-mdc-switch--sm">
							<input type="checkbox" class="qpm-bulk-enable" data-field="<?php echo esc_attr( $key ); ?>" />
							<span class="qpm-mdc-switch__track"></span>
						</label>
						<span class="qpm-bulk-row__label"><?php echo esc_html( $label ); ?></span>
						<div class="qpm-bulk-row__controls" hidden>
							<?php if ( $is_price ) : ?>
								<select class="qpm-mdc-select qpm-mdc-select--sm qpm-bulk-mode" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="fixed"><?php esc_html_e( 'Fixed amount', 'quick-product-manager' ); ?></option>
									<option value="percent"><?php esc_html_e( 'Percentage', 'quick-product-manager' ); ?></option>
								</select>
								<select class="qpm-mdc-select qpm-mdc-select--sm qpm-bulk-direction" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="increase"><?php esc_html_e( 'Increase', 'quick-product-manager' ); ?></option>
									<option value="decrease"><?php esc_html_e( 'Decrease', 'quick-product-manager' ); ?></option>
								</select>
								<input type="number" class="qpm-mdc-input qpm-mdc-input--sm qpm-bulk-value" data-field="<?php echo esc_attr( $key ); ?>" min="0" step="0.01" placeholder="0" />
							<?php elseif ( $is_stock ) : ?>
								<input type="number" class="qpm-mdc-input qpm-mdc-input--sm qpm-bulk-value" data-field="<?php echo esc_attr( $key ); ?>" min="0" step="1" placeholder="0" />
							<?php elseif ( $is_manage ) : ?>
								<select class="qpm-mdc-select qpm-mdc-select--sm qpm-bulk-manage-value" data-field="<?php echo esc_attr( $key ); ?>">
									<option value="1"><?php esc_html_e( 'On', 'quick-product-manager' ); ?></option>
									<option value="0"><?php esc_html_e( 'Off', 'quick-product-manager' ); ?></option>
								</select>
							<?php elseif ( $is_date ) : ?>
								<input type="date" class="qpm-mdc-input qpm-mdc-input--sm qpm-bulk-date" data-field="<?php echo esc_attr( $key ); ?>" />
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="qpm-mdc-dialog__actions qpm-mdc-dialog__actions--bulk">
				<button type="button" id="qpm-bulk-cancel" class="qpm-mdc-btn qpm-mdc-btn--text qpm-mdc-btn--sm"><?php esc_html_e( 'Cancel', 'quick-product-manager' ); ?></button>
				<button type="button" id="qpm-bulk-apply" class="qpm-mdc-btn qpm-mdc-btn--filled qpm-mdc-btn--sm"><?php esc_html_e( 'Apply to selected', 'quick-product-manager' ); ?></button>
			</div>
		</div>
	</div>
</div>

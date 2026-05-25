<?php
/**
 * Admin page template.
 *
 * @package QuickProductManager
 * @var array $categories Category options.
 * @var array $tags       Tag options.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap qpm-wrap">
	<h1><?php esc_html_e( 'Quick Product Manager', 'quick-product-manager' ); ?></h1>

	<div id="qpm-notice" class="qpm-notice" role="status" aria-live="polite" hidden></div>

	<div id="qpm-app" class="qpm-app">
		<div class="qpm-toolbar">
			<div class="qpm-toolbar__row">
				<label class="qpm-field qpm-field--search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search', 'quick-product-manager' ); ?></span>
					<input type="search" id="qpm-search" class="qpm-input" placeholder="<?php esc_attr_e( 'Search products…', 'quick-product-manager' ); ?>" autocomplete="off" />
				</label>

				<select id="qpm-filter-type" class="qpm-select">
					<option value=""><?php esc_html_e( 'All types', 'quick-product-manager' ); ?></option>
					<option value="simple"><?php esc_html_e( 'Simple', 'quick-product-manager' ); ?></option>
					<option value="variable"><?php esc_html_e( 'Variable', 'quick-product-manager' ); ?></option>
					<option value="grouped"><?php esc_html_e( 'Grouped', 'quick-product-manager' ); ?></option>
					<option value="external"><?php esc_html_e( 'External', 'quick-product-manager' ); ?></option>
				</select>

				<select id="qpm-filter-category" class="qpm-select">
					<option value=""><?php esc_html_e( 'All categories', 'quick-product-manager' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat['id'] ); ?>">
							<?php echo esc_html( str_repeat( '— ', (int) $cat['depth'] ) . $cat['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select id="qpm-filter-tag" class="qpm-select">
					<option value=""><?php esc_html_e( 'All tags', 'quick-product-manager' ); ?></option>
					<?php foreach ( $tags as $tag ) : ?>
						<option value="<?php echo esc_attr( $tag['id'] ); ?>">
							<?php echo esc_html( $tag['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select id="qpm-filter-stock" class="qpm-select">
					<option value=""><?php esc_html_e( 'All stock statuses', 'quick-product-manager' ); ?></option>
					<option value="instock"><?php esc_html_e( 'In stock', 'quick-product-manager' ); ?></option>
					<option value="outofstock"><?php esc_html_e( 'Out of stock', 'quick-product-manager' ); ?></option>
					<option value="onbackorder"><?php esc_html_e( 'On backorder', 'quick-product-manager' ); ?></option>
				</select>

				<select id="qpm-filter-visibility" class="qpm-select">
					<option value=""><?php esc_html_e( 'All visibility', 'quick-product-manager' ); ?></option>
					<option value="visible"><?php esc_html_e( 'Shop and search results', 'quick-product-manager' ); ?></option>
					<option value="catalog"><?php esc_html_e( 'Shop only', 'quick-product-manager' ); ?></option>
					<option value="search"><?php esc_html_e( 'Search results only', 'quick-product-manager' ); ?></option>
					<option value="hidden"><?php esc_html_e( 'Hidden', 'quick-product-manager' ); ?></option>
				</select>

				<button type="button" id="qpm-clear-filters" class="button button-secondary">
					<?php esc_html_e( 'Clear filters', 'quick-product-manager' ); ?>
				</button>
			</div>
		</div>

		<div class="qpm-actions qpm-actions--top">
			<button type="button" id="qpm-save-top" class="button button-primary" disabled>
				<?php esc_html_e( 'Save changes', 'quick-product-manager' ); ?>
			</button>
		</div>

		<div id="qpm-table-scroll" class="qpm-table-scroll">
			<table class="widefat striped qpm-table">
				<thead>
					<tr>
						<th class="qpm-col-id"><?php esc_html_e( 'ID', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-title"><?php esc_html_e( 'Title', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-sku"><?php esc_html_e( 'SKU', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-stock"><?php esc_html_e( 'Stock', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-price"><?php esc_html_e( 'Regular price', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-price"><?php esc_html_e( 'Sale price', 'quick-product-manager' ); ?></th>
						<th class="qpm-col-schedule"><?php esc_html_e( 'Sale schedule', 'quick-product-manager' ); ?></th>
					</tr>
				</thead>
				<tbody id="qpm-tbody">
					<tr class="qpm-row qpm-row--loading">
						<td colspan="7"><?php esc_html_e( 'Loading…', 'quick-product-manager' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div id="qpm-scroll-sentinel" class="qpm-scroll-sentinel" aria-hidden="true"></div>
		</div>

		<div class="qpm-actions qpm-actions--bottom">
			<button type="button" id="qpm-save-bottom" class="button button-primary" disabled>
				<?php esc_html_e( 'Save changes', 'quick-product-manager' ); ?>
			</button>
		</div>
	</div>
</div>

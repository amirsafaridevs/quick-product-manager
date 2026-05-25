=== Quick Product Manager ===
Contributors: amirsafaridevs
Tags: woocommerce, products, stock, price, bulk edit
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WooCommerce product prices and stock from a single admin table.

== Description ==

Quick Product Manager adds a spreadsheet-style screen under **WooCommerce → Quick Products** so you can update prices, stock, SKUs, and sale schedules without opening each product edit page.

= Features =

* Product table with ID, title, SKU, stock, regular price, sale price, and sale schedule
* Variable products: parent row plus variation rows, visually grouped
* Filters: search, product type, category, stock status, post status, and brands (when supported)
* Infinite scroll with server-side pagination
* AJAX save: only modified rows are sent to the server
* Bulk edit for selected products (prices, stock, sale dates)
* Visual feedback for unsaved changes
* Translation-ready (`quick-product-manager` text domain)

= Requirements =

* WordPress 5.8 or later
* [WooCommerce](https://woocommerce.com/) 5.0 or later
* PHP 7.4 or later
* User capability: `manage_woocommerce`

== Installation ==

1. Upload the `quick-product-manager` folder to `/wp-content/plugins/`, or install from the WordPress Plugins screen.
2. Activate **Quick Product Manager** through the **Plugins** menu.
3. Ensure WooCommerce is installed and active.
4. Open **WooCommerce → Quick Products**.

== Frequently Asked Questions ==

= Does this replace the WooCommerce products list? =

No. It is an additional tool focused on fast price and stock updates. You can still use the standard WooCommerce product screens.

= Can I edit variable product variations? =

Yes. Each variation appears on its own row. The parent variable product row is read-only for price and stock fields.

= Who can use this screen? =

Users with the `manage_woocommerce` capability (typically Shop Manager and Administrator roles).

== Screenshots ==

1. Product table with filters and save actions.

== Changelog ==

= 1.1.7 =
* Bulk edit for selected products
* Brand and post status filters
* Save progress modal and batch saving
* UI and performance improvements

= 1.0.0 =
* Initial release: product table, filters, AJAX save, variable product support

== Upgrade Notice ==

= 1.1.7 =
Bulk edit, new filters, and improved save experience.

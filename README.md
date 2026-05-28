# ASDevs Quick Product Manager

A WordPress plugin for WooCommerce stores that puts your entire product catalog in one admin screen, so you can update prices and stock without opening each product edit page.

## Features

- **WooCommerce submenu** — **WooCommerce → Quick Products**
- **Product table** — ID, title, SKU, stock (with manage-stock toggle), regular price, sale price, and sale schedule
- **Variable products** — Parent row plus one row per variation, visually grouped
- **Filters** — Search, product type, category, tag, stock status, catalog visibility
- **Infinite scroll** — Server-side pagination (30 products per page) with scroll-to-load
- **AJAX save** — Top and bottom **Save changes** buttons; only modified rows are sent to the server
- **Visual feedback** — Edited rows highlight with a light green background until saved
- **Translations** — Text domain `asdevs-quick-product-manager` (English default); `.pot` in `languages/`

## Requirements

- WordPress 5.8 or later
- [WooCommerce](https://woocommerce.com/) installed and active
- PHP 7.4 or later (recommended: PHP 8.0+)
- User capability: `manage_woocommerce`

## Installation

1. Clone into `wp-content/plugins/asdevs-quick-product-manager/`:

   ```bash
   git clone https://github.com/amirsafaridevs/asdevs-quick-product-manager.git
   ```

2. Activate **ASDevs Quick Product Manager** under **Plugins**.
3. Open **WooCommerce → Quick Products**.

## REST API

Namespace: `asdevs-qpm/v1` (requires logged-in user with `manage_woocommerce` and valid `X-WP-Nonce`).

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/wp-json/asdevs-qpm/v1/products` | List products (query: `page`, `per_page`, `search`, `type`, `category`, `tag`, `stock_status`, `catalog_visibility`) |
| POST | `/wp-json/asdevs-qpm/v1/products/batch` | Batch update `{ "changes": [ { "id": 123, "regular_price": "10" } ] }` |

## Development

| Branch | Purpose |
|--------|---------|
| `main` | Stable releases |
| `develop` | Active development |

### Translators

Copy `languages/asdevs-quick-product-manager.pot` to `asdevs-quick-product-manager-{locale}.po` / `.mo`, or run:

```bash
wp i18n make-pot . languages/asdevs-quick-product-manager.pot --domain=asdevs-quick-product-manager
```

### Manual testing

1. Submenu under WooCommerce; page loads when WC is active.
2. Simple product: edit SKU, stock, prices, schedule → save → verify in WC product editor.
3. Variable product: variations save independently; parent row is read-only.
4. Search and filters narrow results; scrolling loads more pages.
5. Network tab: save payload contains only dirty product IDs and changed fields.
6. Dirty rows use light green background until saved.

## License

GPL v2 or later.

## Repository

https://github.com/amirsafaridevs/asdevs-quick-product-manager

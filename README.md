# Woo Party Chef

WordPress plugin for bourgini.com that renders the **Chef's Dinner Party (CDP) vergelijker**: an interactive set planner and comparison with live WooCommerce prices, sale prices, product images and PFAS-vrij labels.

Built from the Claude Design handoff "CDP Vergelijker" (October 2026). Deliberately a separate plugin, not part of [Woo Card Chef](https://github.com/sramatlov/woo-card-chef); it only borrows Woo Card Chef's PFAS-vrij badge (styling, leaf icon and ACF field).

- **Current version:** 1.0.0
- **Requires:** WordPress 6.0+, PHP 7.4+, WooCommerce 6.0+
- **Target stack:** Hello Elementor, Elementor Pro, WooCommerce, WP Rocket (Remove Unused CSS, Delay JS), Imagify, Kinsta

## What it does

Visitors pick a finish (Wit / Glazed Grey) and a number of persons (1-12). The section:

1. recommends the matching set, plus extension sets above 8 persons;
2. shows the total price, struck-through regular total and savings;
3. shows the product photo and PFAS-vrij label of the recommended product;
4. compares all sets (4, 5, 6, 8 persons and +1 extension station): a table on desktop, stacked cards below 640px;
5. lists what is included in every set.

Recommendation rules (from the design handoff):

| Persons | Advice | Planner image |
|---|---|---|
| 1 | Extension set only | Extension set |
| 2-8 | Smallest set with at least that many places | That set |
| 9-12 | 8-person set + (n - 8) extension sets | 8-person set |

Extra lines: 2-3 persons explain that the smallest set is for 4; 7 persons offer "6-person set + 1 extension set" as an alternative.

## Installation

1. Upload `woo-party-chef-v1.0.0-install.zip` via *Plugins > Add New > Upload*, or copy `woo-party-chef/` to `wp-content/plugins/`.
2. Activate. WooCommerce must be active.
3. Add an Elementor **Shortcode** widget to the campaign container:

```
[woo_party_chef]
```

The Elementor container provides the section title, intro, width, padding and background.

## Shortcode attributes

| Attribute | Values | Default | Purpose |
|---|---|---|---|
| `default_color` | `grey`, `wit` | `grey` | Initially selected finish |
| `persons` | 1-12 | `4` | Initial number of persons |
| `show_planner` | `yes`, `no` | `yes` | Planner card |
| `show_prices` | `yes`, `no` | `yes` | Prices and totals |
| `show_sale` | `yes`, `no` | `yes` | Strike-through prices, discount badges, savings |
| `discount` | `amount`, `percentage` | `amount` | Badge format: `-€20` or `-9%` |
| `standalone` | `yes`, `no` | `no` | Own background and padding (outside a styled container) |
| `anchor` | HTML id | `kies-jouw-chefs-dinner-party` | Section id for anchor links |
| `image_grey` / `image_wit` | attachment ID | none | Fallback image for products without a featured image |

With `show_sale="no"` the actual current price is still shown, only without sale ornaments. The visitor never sees a price higher than what they will pay.

## Live data

Products are configured in `WOOPC_Products::get_catalogue()`:

| Variant | SKU | Slug fallback |
|---|---|---|
| Wit 4p | 16.4005.00.00 | chefs-dinner-party-gourmetstel |
| Wit 5p | 16.4006.00.00 | chefs-dinner-party-gourmetstel-5-personen |
| Wit 6p | 16.4007.00.00 | chefs-dinner-party-gourmetstel-6-personen |
| Wit 8p | 16.4009.00.00 | chefs-dinner-party-gourmetstel-8-personen-pfas-vrij |
| Wit uitbreiding | none | chefs-dinner-party-uitbreidingsset |
| Grey 4p | 16.4045.00.00 | chefs-dinner-party-glazed-grey-gourmetstel |
| Grey 5p | 16.4046.00.00 | chefs-dinner-party-gourmetstel-glazed-grey-5-personen-pfas-vrij |
| Grey 6p | 16.4047.00.00 | chefs-dinner-party-gourmetstel-glazed-grey-6-personen-pfas-vrij |
| Grey 8p | 16.4048.00.00 | chefs-dinner-party-gourmetstel-glazed-grey-8-personen-pfas-vrij |
| Grey uitbreiding | 16.4041.00.00 | chefs-dinner-party-glazed-grey-uitbreidingsset |

Per product the plugin reads live from WooCommerce:

- display price and regular price via `wc_get_price_to_display()` (follows the shop's tax display setting);
- permalink;
- featured image (`get_image_id()`), rendered with `wp_get_attachment_image()` and `srcset`;
- PFAS flag from the ACF true/false field `badge_pfas_vrij`, the same field Woo Card Chef uses.

The plugin contains **no prices**. A finish is only offered when all five of its products are published and have a price; otherwise totals would be wrong. If no finish is complete, visitors see nothing and users with `edit_products` see a notice.

Resolved product IDs are cached in the transient `woopc_cdp_product_ids_v1` (12 hours, or 15 minutes while a product is missing).

## Caching (WP Rocket)

WP Rocket clears a product's own page when the product is saved, but not other pages that show its price. Woo Party Chef therefore:

1. remembers each page that renders the shortcode (option `woopc_cdp_pages`, max. 20, not autoloaded);
2. queues a purge when one of the ten products is created, saved, published, unpublished or trashed, and after WooCommerce's daily `woocommerce_scheduled_sales` job;
3. at the end of the request, calls `rocket_clean_post()` once per remembered published page, so bulk edits purge only once.

Not covered: prices written directly to the database without a WooCommerce product save (some imports/ERP connectors). Those appear after the normal cache lifespan.

Front-end rules for WP Rocket:

- The full default state (Glazed Grey, 4 persons) is rendered server-side, so the section is complete before the delayed JavaScript runs.
- State is written to `data-*`, `aria-pressed` and `hidden` attributes, never to JS-added classes, so Remove Unused CSS cannot strip the styling.
- The stylesheet is excluded from Remove Unused CSS (`rocket_rucss_external_exclusions`) and `.woopc(.*)` is safelisted (`rocket_rucss_safelist`).
- CSS is flat (no nesting) and every selector has at least two classes to beat the Elementor kit and Hello Elementor button/link styles.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `woopc_cdp_catalogue` | filter | Change SKUs, slugs, labels or swatches |
| `woopc_cdp_purge_post_ids` | filter | Change which pages are purged after a price change |
| `woopc_cdp_pages_purged` | action | Purge other caches (for example Kinsta) for the same pages |

## Code structure

```
woo-party-chef/
├── woo-party-chef.php            Plugin header, constants, HPOS declaration, bootstrap
├── readme.txt                    WordPress readme and changelog
├── includes/
│   ├── class-products.php        Catalogue, ID resolution, live price/image data, recommendation logic
│   ├── class-cache-purger.php    Page registry and targeted WP Rocket purging
│   └── class-shortcode.php       [woo_party_chef], assets, server-side markup
└── assets/
    ├── woo-party-chef.css        Styles (flat, attribute-driven state)
    └── woo-party-chef.js         Progressive enhancement, no dependencies
```

**Keep in sync:** `WOOPC_Products::compute_state()` (PHP) and `compute()` in `woo-party-chef.js` contain the same recommendation logic. PHP renders the first state and JS renders every state after an interaction. Any change to rules or copy must be made in both. See [TESTING.md](TESTING.md) for the parity check.

## Documentation

- [DECISIONS_LOG.md](DECISIONS_LOG.md): why the plugin is built this way
- [TESTING.md](TESTING.md): what was tested and the staging checklist
- [woo-party-chef/readme.txt](woo-party-chef/readme.txt): changelog

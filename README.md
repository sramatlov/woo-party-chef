# Woo Party Chef

WordPress plugin for bourgini.com that renders the **Chef's Dinner Party (CDP) vergelijker**: an interactive set planner and comparison with live WooCommerce prices, sale prices, product images and PFAS-vrij labels.

Built from the Claude Design handoff "CDP Vergelijker" (October 2026). Deliberately a separate plugin, not part of [Woo Card Chef](https://github.com/sramatlov/woo-card-chef); it only borrows Woo Card Chef's PFAS-vrij badge (styling, leaf icon and ACF field).

- **Current version:** 1.2.0
- **Requires:** WordPress 6.0+, PHP 7.4+, WooCommerce 6.0+
- **Target stack:** Hello Elementor, Elementor Pro, WooCommerce, WP Rocket (Remove Unused CSS, Delay JS), Imagify, Kinsta

## What it does

Visitors pick a finish (Wit / Glazed Grey) and a number of persons (1-12). The section:

1. recommends the matching set, plus extension sets for 9-11 persons or a second 4-person set for 12;
2. shows the total price, struck-through regular total and savings;
3. shows the product photo and PFAS-vrij label of the recommended product;
4. compares all sets (4, 5, 6, 8 persons and +1 extension station): a table on desktop, stacked cards below 760px of available width (also in narrow Elementor containers);
5. lists what is included in every set.

Recommendation rules (from the design handoff):

| Persons | Advice | Planner image |
|---|---|---|
| 1 | Extension set only | Extension set |
| 2-8 | Smallest set with at least that many places | That set |
| 9-11 | 8-person set + (n - 8) extension sets | 8-person set |
| 12 | 8-person set + 4-person set | 8-person set |

Extra lines: 2-3 persons explain that the smallest set is for 4; 7 persons offer "6-person set + 1 extension set" as an alternative.

## Installation

1. Upload `woo-party-chef-v1.2.0-install.zip` via *Plugins > Add New > Upload*, or copy `woo-party-chef/` to `wp-content/plugins/`.
2. Activate. WooCommerce must be active.
3. Add an Elementor **Shortcode** widget to the campaign container:

```
[woo_party_chef]
```

The Elementor container provides the section title, intro, width, padding and background.

To update an existing installation, upload the new ZIP through the same plugin
upload screen and choose **Replace current with uploaded** when WordPress shows
the installed and uploaded versions. After the update succeeds, use WP Rocket's
**Purge this URL** on the campaign page and verify both desktop and mobile.
Version 1.1.1 fixes a zero-width shortcode widget in centred Elementor containers.
Version 1.1.2 keeps purchase-link fonts unchanged on hover and keyboard focus
when Elementor's global link styles apply. Version 1.2.0 recommends an
8-person set plus a 4-person set for 12 persons. The shortcode remains
`[woo_party_chef]`. See [TESTING.md](TESTING.md) for the recorded staging
installation and checks.

After updating from 1.0.0, clear the campaign page cache once so the new markup
and versioned assets replace the existing cached page. For a planner above the
fold, use `[woo_party_chef image_loading="eager"]`; the default `auto` lets
WordPress choose loading priority. Use `image_loading="lazy"` for a deliberately
below-fold planner.

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
| `image_loading` | `auto`, `eager`, `lazy` | `auto` | Active photo priority; alternate photos are always lazy |

With `show_sale="no"` the actual current price is still shown, only without sale ornaments. The visitor never sees a price higher than what they will pay.

With `show_prices="no"`, price and regular-price fields are omitted from the
browser configuration as well as the visible markup. Duplicate section anchors
receive `-2`, `-3`, etc.; the first public anchor is preserved. Internal label IDs
are unique per shortcode instance. Selection buttons expose `aria-pressed`, and
changed advice is announced in a polite live region without prices when hidden.
The desktop comparison exposes table semantics, with each product as one logical
row for assistive technology while retaining the existing visual columns.

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

Resolved product IDs are cached in the transient `woopc_cdp_product_ids_v2` (12 hours, or 15 minutes while a product is missing). The cache includes a catalogue signature and validates its ID shape before reuse. Malformed catalogue entries, missing required references, non-finite prices and non-HTTP(S) product URLs are rejected before rendering. Browser configuration is validated independently before interaction is enabled.

## Caching (WP Rocket)

WP Rocket clears a product's own page when the product is saved, but not other pages that show its price. Woo Party Chef therefore:

1. remembers each page that renders the shortcode, even when no complete finish can be rendered yet (option `woopc_cdp_pages`, max. 20, not autoloaded);
2. queues a purge when one of the ten products is created, saved, published, unpublished or trashed, and after WooCommerce's daily `woocommerce_scheduled_sales` job;
3. at the end of the request, calls `rocket_clean_post()` once per remembered published page, so bulk edits purge only once;
4. uses WP Rocket's native Kinsta bridge when registered, or calls the Kinsta MU plugin's targeted `initiate_purge( $page_id, 'post' )` fallback when that bridge is absent. It does not flush the entire site. The existing `woopc_cdp_pages_purged` action remains available for other cache providers.

The Kinsta integration uses the same post-purge contract as [WP Rocket's Kinsta adapter](https://github.com/wp-media/wp-rocket/blob/develop/inc/ThirdParty/Hostings/Kinsta.php). Clearing host/edge caches still requires an operational Kinsta MU plugin; verify the real integration on staging. See [Kinsta caching documentation](https://kinsta.com/docs/wordpress-hosting/caching/).

Not covered: prices written directly to the database without a WooCommerce product save (some imports/ERP connectors). Those appear after the normal cache lifespan.

Front-end rules for WP Rocket:

- The full default state (Glazed Grey, 4 persons) is rendered server-side. Only this plugin's small script carries `data-nowprocket`, so it is excluded from Delay JS while still loading with `defer`; other scripts are unaffected. See [WP Rocket's exclusion documentation](https://docs.wp-rocket.me/article/1655-troubleshoot-delay-javascript-execution-issues).
- The first-visit stylesheet is discovered from page content and Elementor data before head output. Nested templates missed by that discovery print the stylesheet immediately before the component as a fallback.
- Image priority defaults to WordPress's loading heuristics. The active photo is rendered first; hidden alternatives use lazy loading and low priority. Complete wrappers are hidden, preserving Imagify picture markup.
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

## Development checks and packaging

`npm test` requires PHP 7.4+ and Node 20+. It checks syntax, rendering, cache
contracts, malformed configuration and 224 PHP/JS calculation states. For browser
checks, run `npm ci`, `npx playwright install chromium`, then
`npm run test:browser`. These development dependencies are not shipped with the
plugin. GitHub Actions runs the PHP suite on PHP 7.4 and 8.3 and runs the browser
suite separately.

Build the installable ZIP with `pwsh -File tools/build-install.ps1`. Only the
`woo-party-chef/` runtime directory is included.

## Documentation

- [DECISIONS_LOG.md](DECISIONS_LOG.md): why the plugin is built this way
- [TESTING.md](TESTING.md): what was tested and the staging checklist
- [woo-party-chef/readme.txt](woo-party-chef/readme.txt): changelog

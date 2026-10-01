=== Woo Party Chef ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Chef's Dinner Party set planner and comparison for bourgini.com, with live WooCommerce prices.

== Usage ==

Place an Elementor Shortcode widget in the campaign container:

    [woo_party_chef]

The container provides the section title, intro, width, padding and background.

Attributes (all optional):

* default_color  grey|wit           Default color. Default: grey
* persons        1-12               Default number of persons. Default: 4
* show_planner   yes|no             Planner card. Default: yes
* show_prices    yes|no             Prices and totals. Default: yes
* show_sale      yes|no             Strike-through prices and savings. Default: yes
* discount       amount|percentage  Discount badge style. Default: amount
* standalone     yes|no             Own background and padding. Default: no
* anchor         HTML id            Default: kies-jouw-chefs-dinner-party
* image_grey     attachment ID      Fallback for Grey products without a featured image
* image_wit      attachment ID      Fallback for Wit products without a featured image

== Live data ==

Products are looked up by SKU (Wit extension set: by slug, it has no SKU).
Price, sale price, permalink and image always come from WooCommerce. Prices
follow the shop's tax display setting. The planner image is the featured image
of the recommended product (extension set for 1 person, the 8-person set for
9-12). The PFAS-vrij badge uses the same ACF field as Woo Card Chef
(badge_pfas_vrij) on that same product.

A color is only shown when all five of its products are published and have a
price. If no color is complete, visitors see nothing and editors see a notice.

Change SKUs or slugs with the `woopc_cdp_catalogue` filter.

== Caching (WP Rocket) ==

The plugin remembers which pages render the shortcode. When one of the ten
products is saved, published, unpublished or trashed, or when WooCommerce's
daily scheduled-sales job runs, those pages are purged with rocket_clean_post()
at the end of the request (once, also for bulk edits).

Not covered: price changes written directly to the database without a
WooCommerce product save (some imports/ERP connectors). Those show after the
normal cache lifespan.

Hooks:
* woopc_cdp_purge_post_ids (filter) - pages to purge
* woopc_cdp_pages_purged (action)   - hook other caches (e.g. Kinsta) here

The stylesheet is excluded from Remove Unused CSS and .woopc selectors are
safelisted. Styling uses data/ARIA attributes only, never JS-added classes.
The full default state is rendered server-side, so the section is complete
before WP Rocket's delayed JavaScript runs.

== Changelog ==

= 1.0.0 =
* Initial release: planner, comparison table/cards and features box from the
  Claude Design handoff, with live WooCommerce prices, product images and
  PFAS flags per recommended set, and targeted WP Rocket purging.

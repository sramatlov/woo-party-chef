# Decisions Log - Woo Party Chef

## v1.1.0 (1 October 2026)

Implemented the explicitly selected review items: 2, 3, 8, 9, 10, 11, 12,
13, 14, 15 and 16. Product availability rules, recommendation choices, price
breakdown, visible purchase-button copy, PFAS feature copy and discount colors
remain outside this release's scope.

- Empty shortcode pages enter the purge registry before catalogue validation.
- WP Rocket's registered native Kinsta bridge takes priority. A guarded MU-plugin
  post-purge fallback supports Kinsta without that bridge; no blanket site flush.
- Preserve the visual comparison columns and expose each product as a logical
  table row with named column headers for assistive technology. Add pressed
  selection state and one polite advice announcement per interaction.
- Preserve the first requested public anchor; suffix subsequent collisions and
  generate unique internal label IDs for every instance.
- Switch to cards below 760px of component width, with a viewport fallback.
- Supersede the original delayed-script decision: exclude only this small
  interactive script from Delay JS and retain deferred loading. Keep server
  rendering and the existing RUCSS exclusions.
- Discover first-visit CSS from normal content and Elementor data; print late
  styles immediately before nested components when necessary.
- Delegate active-image priority to WordPress by default, with explicit
  `image_loading="eager|lazy"` overrides. Render the active image first and hide
  entire wrappers to preserve Imagify picture layout.
- Validate both server catalogue/cache data and browser configuration. Include a
  catalogue signature in the ID transient and omit amounts entirely when prices
  are disabled.
- Commit the PHP/JS parity harness and targeted render/cache/browser regression
  checks, with a PHP 7.4/8.3 CI matrix.

### Decision: Elementor editor re-renders via Elementor's hook, not a MutationObserver
**Chose:** Initialise re-rendered widgets through `elementorFrontend.hooks` (`frontend/element_ready/global`), registered only when `elementorFrontend.isEditMode()` is true.
**Rejected:** A document-wide `MutationObserver` that stays active on every visitor page.
**Why:** Only the editor replaces shortcode markup after load. A permanent observer runs a query for every DOM insertion (sliders, mini cart, lazyload) on the live campaign page for a case visitors never hit.

### Decision: browser config validation must not hardcode server limits
**Chose:** Validate `max` as a positive integer from the server and log a `console.warn` when a config is rejected.
**Rejected:** Requiring `max === 12` and failing silently.
**Why:** `WOOPC_Products::MAX_PERSONS` is the single source of truth. A hardcoded copy would silently disable all interaction after a server-side change; the warning makes any rejection traceable while the server-rendered state stays usable.

## v1.0.0 (October 2026)

### Decision: separate plugin, not a Woo Card Chef widget
**Chose:** A standalone plugin with a shortcode, placed in an Elementor Shortcode widget.
**Rejected:** An Elementor widget inside Woo Card Chef; a static Elementor HTML widget; a Code Snippets / mu-plugin file.
**Why:** The comparator is a campaign section for one product family, not a reusable shop widget. An HTML widget cannot run PHP, so it cannot read live prices. A real plugin gives its own versioning and assets. Woo Card Chef elements are reused by copying their contract (PFAS badge CSS, leaf icon, ACF field `badge_pfas_vrij`), not by depending on Woo Card Chef classes, so the plugin also works without Woo Card Chef.

### Decision: live prices server-side, kept fresh by a targeted cache purge
**Chose:** Render prices server-side from WooCommerce and purge only the pages that show the comparator when one of its products changes, including scheduled sales.
**Rejected:** Fetching prices client-side from the WooCommerce Store API, alone or as a backup.
**Why:** Bourgini sets prices and sales via WooCommerce product saves and its scheduled sales, which the purge covers. WP Rocket delays JavaScript until the first interaction, so a client-side refresh would show a stale price first and then visibly jump, and would cost a request per visit. Known gap: prices written directly to the database are only picked up when the cache expires.

### Decision: products by SKU, slug fallback, CDP only
**Chose:** A fixed catalogue of ten products, identified by SKU, with the product slug as a fallback (the Wit extension set has no SKU). Changeable through the `woopc_cdp_catalogue` filter.
**Rejected:** A settings page or shortcode attributes to configure any product family.
**Why:** Fastest route to a live, correct campaign page. A configurable comparator can be added if a second product family needs one.

### Decision: hide an incomplete finish instead of showing partial data
**Chose:** A finish is offered only when all five of its products are published and priced. With no complete finish, visitors see nothing and editors see a notice.
**Why:** The recommendation combines prices (set + extension sets). One missing product would make totals and savings wrong. Showing nothing is safer than showing a made-up price.

### Decision: `show_sale="no"` shows the actual price
**Chose:** Without sale display, show the current price without strike-through and badges.
**Rejected:** The prototype's behaviour of showing the regular price.
**Why:** Otherwise the comparator would show a higher price than the visitor pays at checkout.

### Decision: planner image follows the recommended product
**Chose:** Show the featured image and PFAS flag of the recommended product: the extension set for 1 person, the 8-person set for 9-12 persons. Each distinct image is rendered once server-side; JS toggles `hidden` by attachment ID.
**Rejected:** One image per finish (the design handoff); swapping `src`/`srcset` on a single `<img>` in JS.
**Why:** The visitor sees the actual product being recommended. Toggling server-rendered images keeps Imagify `<picture>` markup and WP Rocket lazyload intact, while hidden lazy images are not downloaded until shown.

### Decision: state via attributes, logic duplicated in PHP and JS
**Chose:** Server-render the complete default state. JS updates only text, `href`, `data-*`, `aria-pressed` and `hidden`. The recommendation logic lives in both PHP and JS, with a parity check.
**Rejected:** A JS-only render; state classes added by JS.
**Why:** WP Rocket's delayed JS and Remove Unused CSS. A JS-only render would leave the section empty until the first interaction, and JS-added classes are stripped from the used CSS. The duplication is the price for both and is covered by the parity check in TESTING.md.

### Decision: small deviations from the design handoff
- Power uses a Dutch thousands separator: "2.500 W" instead of "2500 W".
- The image follows the recommended product instead of the finish (see above).
- The table's label column uses 102px / 78px for the header / CTA rows (instead of 104 / 80) so its rows line up exactly with the 2px-bordered columns.
- Column numbers are `<button>` elements so a column can also be selected by keyboard; the visual design is unchanged.

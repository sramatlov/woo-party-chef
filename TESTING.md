# Testing - Woo Party Chef

## v1.1.0: reproducible checks (1 October 2026)

Run `npm test` with PHP and Node on PATH. The checked-in harness uses strict
WordPress/WooCommerce function doubles: PHP warnings become exceptions. It covers
60 PHP assertions, 224 PHP/JS calculation parity states (two finishes, persons
0-13, every prices/sale/discount combination), and 16 browser-config validations
(including a different server-defined person limit staying valid).
Hidden-price parity states contain no amounts in their browser input.

The rendering/cache assertions cover empty-page registration, excluded
editor/preview requests, first-visit normal/Elementor CSS discovery, late CSS,
unique IDs, accessible markup, image priority, hidden amounts, malformed
catalogues/ID caches/shortcode attributes, unsafe URLs, non-numeric/non-finite
prices, scheduled sales and batched purges. Separate-process tests cover hosting
without WP Rocket. The native Kinsta-bridge test verifies there is no duplicate
fallback purge. Existing solo/7-person/11-person advice and visible purchase/PFAS
copy remain covered as regression boundaries.

For browser checks:

```
npm ci
npx playwright install chromium
npm run test:browser
```

The browser suite checks 36 assertions: the first interaction, keyboard selection,
live announcements, independent instances, picture-wrapper visibility,
person-count bounds, layouts at 320/375/640/700/800/1280px, a 600px container on
desktop, hidden-price and comparison-only modes, malformed neighbours and
Elementor editor re-renders through a simulated `frontend/element_ready/global`
hook without duplicate handlers. It uses the real PHP markup,
CSS and JS with synthetic product photos. This verifies the component, not the
real Elementor/Imagify/WP Rocket integration.

Local verification: PHP 8.3, Node 24 and headless installed Chrome; all above
checks passed. `WOOPC_BROWSER_CHANNEL=chrome` selects an installed Chrome instead
of Playwright's downloaded Chromium. CI is configured for PHP 7.4/8.3 and Chromium;
remote CI and actual hosting integrations require their own run.

### Required staging checks after installation

1. Clear the existing campaign page cache once to activate the new markup/assets.
2. Render a new shortcode page with incomplete products, then restore a product:
   verify the registered page refreshes through the real cache chain.
3. Change a product price and start/end a scheduled sale; verify WP Rocket and
   Kinsta/edge responses show updated amounts. Confirm the MU plugin is present.
4. With Delay JS and RUCSS enabled, verify the first click responds and all states
   retain their styles; unrelated scripts should retain their existing settings.
5. Verify real Imagify pictures and WP Rocket lazy loading while switching product
   photos; use `image_loading="eager"` for an above-fold planner where appropriate.
6. Check Elementor preview, narrow containers and multiple instances on the actual
   site; inspect the semantic comparison and announcements with a screen reader.
7. Inspect HTML with `show_prices="no"`: neither markup nor `data-config` should
   contain amounts. Confirm the original recommendation and visible copy.

## v1.0.0: tested locally (1 October 2026)

Environment: PHP 8.3 CLI with WordPress/WooCommerce function stubs, the prototype's prices as test data (in the test harness only, not in the plugin), and the Claude Browser at 1280px and 375px.

| Check | Result |
|---|---|
| `php -l` on all PHP files, `node --check` on the JS | Pass |
| PHP `compute_state()` vs JS `compute()`: 2 finishes × persons 0-13 × 3 display combinations (84 states, all fields including image key) | 0 mismatches |
| Server render: complete default state (Glazed Grey, 4 persons), only the active product image visible | Pass |
| Interaction: finish switch, stepper 1-12, column/card pick, "+1" column, n=7 alternative, n≥9 extension link | Pass |
| Image and PFAS label follow the recommended product (Grey 4→5→6→8→11→1, Wit 1→2) | Pass |
| Desktop table matches the design; rows line up | Pass |
| Mobile (375px): cards instead of table, no horizontal scroll | Pass |

## Not yet tested: staging checklist

1. **SKUs:** all ten products are found. If not, an editor sees the notice "producten zijn niet gevonden".
2. **Prices:** every column and the advice total match the product pages, including an active sale.
3. **Sale purge:** change the price of one CDP product and confirm the campaign page shows the new price on the next uncached visit.
4. **Scheduled sale:** plan a sale to start shortly and confirm the comparator updates after the WooCommerce cron runs.
5. **Kinsta:** confirm the server cache of the campaign page also refreshes after step 3. If not, hook a Kinsta purge into `woopc_cdp_pages_purged`.
6. **Images:** the featured images of the ten products look consistent enough that switching sets does not jump visually.
7. **PFAS-vrij:** the label shows exactly for products with `badge_pfas_vrij` enabled and matches the Woo Card Chef gallery badge.
8. **WP Rocket Remove Unused CSS:** after regenerating used CSS, active column, sale prices and dots are still styled. Check both finishes and a sale column.
9. **WP Rocket Delay JS:** the section is complete before interaction. Note whether the first click (finish or +/−) is lost while the JS loads.
10. **Elementor editor:** the shortcode preview renders with styles.
11. **Fonts:** MontserratVar and Roboto Flex from the site are used. The plugin loads no fonts itself.

## Historical v1.0.0 parity check

The original parity check rendered every state in PHP and JS and compared all fields. Its harness lived in the session scratchpad and was not committed. It has been replaced by `npm test` in v1.1.0. The original recreation procedure was:

1. PHP: define `ABSPATH`, stub `apply_filters()`, require `includes/class-products.php`, and print `WOOPC_Products::compute_state()` for each finish × persons × settings combination as JSON.
2. JS: load `assets/woo-party-chef.js` in a Node `vm` with a stub `document`, expose `compute()`, and compare its output field by field (camelCase in JS, snake_case in PHP).

# Testing - Woo Party Chef

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

## Re-running the parity check

The parity check renders every state in PHP and JS and compares all fields. Any change to the recommendation rules or copy must keep it at 0 mismatches. The harness lived in the session scratchpad and was not committed. To recreate it:

1. PHP: define `ABSPATH`, stub `apply_filters()`, require `includes/class-products.php`, and print `WOOPC_Products::compute_state()` for each finish × persons × settings combination as JSON.
2. JS: load `assets/woo-party-chef.js` in a Node `vm` with a stub `document`, expose `compute()`, and compare its output field by field (camelCase in JS, snake_case in PHP).

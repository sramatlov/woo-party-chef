<?php
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/kinsta-bridge.php';
require __DIR__ . '/../woo-party-chef/includes/class-products.php';
require __DIR__ . '/../woo-party-chef/includes/class-cache-purger.php';
require __DIR__ . '/../woo-party-chef/includes/class-shortcode.php';

$checks = 0;
function check( bool $ok, string $message ): void {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
}
function config_from_html( string $html ): array {
	preg_match( '/data-config="([^"]+)"/', $html, $match );
	return json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
}

$catalogue = WOOPC_Products::get_catalogue();
$id = 0;
foreach ( $catalogue as $color => $config ) {
	foreach ( $config['products'] as $key => $ref ) {
		$product = new WC_Product( ++$id );
		$product->sku = $ref['sku']; $product->slug = $ref['slug'];
		$product->price = 'ext' === $key ? 39.95 : 79.95 + (int) $key * 20;
		$product->regular = $product->price + ( 'ext' === $key ? 5.50 : 20 );
		$GLOBALS['test_products'][$id] = $product;
		$GLOBALS['test_skus'][$ref['sku']] = $id;
		$GLOBALS['test_slugs'][$ref['slug']] = $id;
	}
}
$colors = WOOPC_Products::get_colors_data();
check( array( 'wit', 'grey', 'deluxe' ) === array_keys( $colors ), 'The catalogue offers Wit, Grey and the optional Deluxe line.' );
check( 250 === $colors['grey']['watt'] && 'standard' === $colors['wit']['line'] && 480 === $colors['deluxe']['watt'] && 'deluxe' === $colors['deluxe']['line'], 'Each line carries its own station power.' );
$deluxe_six = WOOPC_Products::compute_state( $colors['deluxe'], 6, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) );
check( '6 kookstations van 480 W, samen 2.880 W.' === $deluxe_six['text'] && 'Chef’s Dinner Party Deluxe voor 6 personen' === $deluxe_six['title'], 'Deluxe advice uses 480 W per station.' );
check( 'Eén kookstation van 480 W met eigen keramisch bord, bakplaat en twee pannetjes. Later uit te breiden tot een complete set.' === WOOPC_Products::compute_state( $colors['deluxe'], 1, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) )['text'], 'Deluxe extension copy names the pannetjes.' );
check( '9 kookstations van 250 W, samen 2.250 W. Elke uitbreidingsset voegt één eigen kookstation met bord en bakplaat toe.' === WOOPC_Products::compute_state( $colors['grey'], 9, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) )['text'], 'Standard advice copy must be unchanged.' );
$settings = array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false );
$discount_color = $colors['grey'];
$discount_color['items']['4']['price'] = 115.00;
$discount_color['items']['4']['regular'] = 124.99;
$discount_color['items']['ext']['price'] = 14.00;
$discount_color['items']['ext']['regular'] = 39.99;
$four_discount = WOOPC_Products::compute_state( $discount_color, 4, $settings );
$solo_discount = WOOPC_Products::compute_state( $discount_color, 1, $settings );
check( 'Je bespaart €10' === $four_discount['save'] && '-€10' === $four_discount['cols']['4']['badge'], 'The screenshot four-person saving must match the rounded euro badge.' );
check( 'Je bespaart €26' === $solo_discount['save'] && '-€26' === $solo_discount['cols']['ext']['badge'], 'The screenshot extension saving must match the rounded euro badge.' );
check( '€ 115,00' === $four_discount['total'] && '€ 124,99' === $four_discount['total_was'] && '€ 14,00' === $solo_discount['total'] && '€ 39,99' === $solo_discount['total_was'], 'Rounding savings must preserve exact current and reference prices.' );
check( '€0' === WOOPC_Products::format_discount_eur( 0.49 ) && '€1' === WOOPC_Products::format_discount_eur( 0.50 ) && '€1.235' === WOOPC_Products::format_discount_eur( 1234.50 ), 'Euro discounts round half up and retain Dutch thousands separators like Woo Card Chef.' );
$combined_color = $discount_color;
$combined_color['items']['ext']['regular'] = 19.49;
check( 'Je bespaart €31' === WOOPC_Products::compute_state( $combined_color, 10, $settings )['save'], 'Combined savings must round once after summing the 20.00 and two 5.49 euro discounts.' );
$eleven = WOOPC_Products::compute_state( $colors['grey'], 11, $settings );
check( '8' === $eleven['image'] && 'https://example.test/product/9' === $eleven['cta_url'], 'Existing purchase target must be unchanged.' );
check( 'Voeg 3 uitbreidingssets toe →' === $eleven['ext_label'], 'Extension CTA copy must be unchanged.' );
check( '€ 359,80' === $eleven['total'], 'The existing set-plus-three-extensions total must remain correct.' );
check( 'Liever precies 7 plekken? Kies de set voor 6 personen met 1 uitbreidingsset voor € 239,90.' === WOOPC_Products::compute_state( $colors['grey'], 7, $settings )['alt'], 'The seven-person alternative must remain unchanged.' );
check( 'ext' === WOOPC_Products::compute_state( $colors['grey'], 1, $settings )['image'], 'Solo advice must be unchanged.' );
$twelve = WOOPC_Products::compute_state( $colors['grey'], 12, $settings );
check( '8 personen + 4 personen' === $twelve['title'] && '€ 399,90' === $twelve['total'] && '€ 439,90' === $twelve['total_was'], 'Twelve persons need an 8-person plus a 4-person set.' );
check( $twelve['cols']['8']['active'] && $twelve['cols']['4']['active'] && ! $twelve['cols']['ext']['active'], 'Twelve persons activate both set columns, not the extension.' );
check( '8' === $twelve['image'] && 'https://example.test/product/9' === $twelve['cta_url'] && 'https://example.test/product/6' === $twelve['ext_url'], 'Twelve persons buy the 8-person set and link the 4-person set.' );
check( 'Voeg de set voor 4 personen toe →' === $twelve['ext_label'] && ! $twelve['has_ext'] && ! $twelve['has_spare'] && array( 'guest' ) === array_values( array_unique( $twelve['dots'] ) ) && 12 === count( $twelve['dots'] ), 'Twelve persons show twelve set places without extension dots.' );

WOOPC_Cache_Purger::init();
WOOPC_Shortcode::init();
$first = WOOPC_Shortcode::render( array( 'image_loading' => 'eager' ) );
$second = WOOPC_Shortcode::render( array() );
$hidden = WOOPC_Shortcode::render( array( 'show_prices' => 'no' ) );
$no_planner = WOOPC_Shortcode::render( array( 'show_planner' => 'no' ) );
$classic = WOOPC_Shortcode::render( array( 'layout' => 'classic' ) );
$with_deluxe = WOOPC_Shortcode::render( array( 'deluxe' => 'yes' ) );
$deluxe_wit = WOOPC_Shortcode::render( array( 'deluxe' => 'yes', 'default_color' => 'wit' ) );
check( false === strpos( $first, 'Deluxe' ) && array( 'wit', 'grey' ) === array_keys( config_from_html( $first )['colors'] ), 'Without deluxe="yes" the planner must not mention Deluxe.' );
check( false !== strpos( $first, 'data-color="grey"' ) && false === strpos( $first, 'data-line="deluxe"' ) && 1 === substr_count( $first, 'class="woopc__features"' ), 'Without Deluxe the default color and single feature box are unchanged.' );
check( array( 'deluxe', 'wit', 'grey' ) === array_keys( config_from_html( $with_deluxe )['colors'] ), 'deluxe="yes" adds Deluxe as the first color.' );
preg_match_all( '/data-pick-color="([a-z]+)"/', $with_deluxe, $buttons );
check( array( 'deluxe', 'wit', 'grey', 'deluxe', 'wit', 'grey' ) === $buttons[1], 'Deluxe buttons come first in both color pickers.' );
check( false !== strpos( $with_deluxe, 'data-color="deluxe"' ) && false !== strpos( $with_deluxe, 'data-pick-color="deluxe" aria-pressed="true"' ), 'Deluxe is selected by default when enabled.' );
check( false !== strpos( $deluxe_wit, 'data-color="wit"' ), 'An explicit default_color still wins over the Deluxe default.' );
check( false !== strpos( $with_deluxe, '<div class="woopc__features" data-line="deluxe">' ) && false !== strpos( $with_deluxe, '<div class="woopc__features" data-line="standard" hidden>' ), 'The Deluxe feature box is visible and the standard box hidden while Deluxe is selected.' );
check( false !== strpos( $with_deluxe, 'In elke Chef’s Dinner Party Deluxe' ) && false !== strpos( $with_deluxe, 'Draaibare serveerschaal' ), 'Deluxe has its own feature copy.' );
check( false !== strpos( $first, '<span class="woopc__intro" data-ref="intro" hidden></span>' ) && false === strpos( $first, 'klassieke Chef’s Dinner Party' ), 'Without Deluxe the advice box shows no version explanation.' );
foreach ( config_from_html( $first )['colors'] as $color ) { check( '' === $color['intro'], 'Without Deluxe no version explanation reaches the browser.' ); }
check( 1 === preg_match( '/<span class="woopc__intro" data-ref="intro">Grillen én gourmetten tegelijk/', $with_deluxe ), 'The Deluxe advice explains grilling and gourmetting.' );
check( false !== strpos( $deluxe_wit, '<span class="woopc__intro" data-ref="intro">De klassieke Chef’s Dinner Party in fris wit.' ), 'With Deluxe offered, Wit explains the classic version.' );
check( false !== strpos( $with_deluxe, 'data-ref="watt">3.840 W<' ) && false !== strpos( $deluxe_wit, 'data-ref="watt">2.000 W<' ), 'Comparison power follows the selected line.' );
check( false !== strpos( $first, 'data-layout="auto"' ), 'Existing shortcodes must enable automatic planner sizing by default.' );
check( false !== strpos( $classic, 'data-layout="classic"' ), 'The classic shortcode setting must expose a direct layout rollback.' );
check( false !== strpos( WOOPC_Shortcode::render( array( 'layout' => 'invalid' ) ), 'data-layout="auto"' ), 'Unknown layout settings must fall back to auto.' );
check( false !== strpos( $first, 'PFAS-vrije keramische anti-aanbaklaag' ), 'PFAS copy is outside this change.' );
check( false !== strpos( $first, '>Bekijk deze set</a>' ), 'Visible CTA copy is outside this change.' );
check( false !== strpos( $first, 'role="table"' ) && false !== strpos( $first, 'role="columnheader"' ), 'Comparison needs table headers.' );
check( false !== strpos( $first, 'role="status"' ) && false !== strpos( $first, 'aria-pressed="true"' ), 'Selection and advice need accessible state.' );
preg_match_all( '/\sid="([^"]+)"/', $first . $second . $hidden . $no_planner, $matches );
check( count( $matches[1] ) === count( array_unique( $matches[1] ) ), 'Repeated shortcodes must have unique IDs.' );
check( false !== strpos( $first, 'id="kies-jouw-chefs-dinner-party"' ), 'First public anchor must be preserved.' );
check( false !== strpos( $second, 'id="kies-jouw-chefs-dinner-party-2"' ), 'Duplicate public anchors need suffixes.' );
check( false !== strpos( $first, 'loading="eager" fetchpriority="high"' ), 'Explicit above-fold photo needs eager/high.' );
check( 9 === substr_count( $first, 'loading="lazy" fetchpriority="low"' ), 'Alternate photos need lazy/low.' );
check( false === strpos( $second, 'fetchpriority="high"' ), 'Auto priority must remain under WordPress control.' );
check( false === strpos( $hidden, '€' ), 'Hidden-price HTML must contain no amounts.' );
foreach ( config_from_html( $hidden )['colors'] as $color ) {
	foreach ( $color['items'] as $item ) { check( ! array_key_exists( 'price', $item ) && ! array_key_exists( 'regular', $item ), 'Hidden-price configuration must omit amounts.' ); }
}

$GLOBALS['test_options'] = array();
$backup = $GLOBALS['test_products'];
$GLOBALS['test_products'] = array();
check( '' === WOOPC_Shortcode::render( array() ), 'Missing products must preserve the existing empty output.' );
check( array( 42 ) === WOOPC_Cache_Purger::get_pages(), 'Empty shortcode pages must be remembered.' );
$GLOBALS['test_products'] = $backup;
foreach ( array( 'test_admin', 'test_ajax', 'test_preview' ) as $flag ) {
	$GLOBALS['test_options'] = array(); $GLOBALS[$flag] = true;
	WOOPC_Shortcode::render( array() );
	check( array() === WOOPC_Cache_Purger::get_pages(), 'Editor/preview renders must not change the page registry.' );
	$GLOBALS[$flag] = false;
}

$GLOBALS['test_options'] = array();
$GLOBALS['test_styles'] = array();
$GLOBALS['test_post']->post_content = '[woo_party_chef]';
WOOPC_Shortcode::register_assets();
check( 'enqueued' === $GLOBALS['test_styles']['woo-party-chef'], 'First shortcode visit must enqueue head CSS.' );
$GLOBALS['test_styles'] = array();
$GLOBALS['test_post']->post_content = '';
$GLOBALS['test_meta'][42]['_elementor_data'] = '{"shortcode":"[woo_party_chef]"}';
WOOPC_Shortcode::register_assets();
check( 'enqueued' === $GLOBALS['test_styles']['woo-party-chef'], 'First Elementor visit must enqueue head CSS.' );
$GLOBALS['test_styles'] = array(); $GLOBALS['test_meta'] = array();
WOOPC_Shortcode::register_assets();
check( 'registered' === $GLOBALS['test_styles']['woo-party-chef'], 'Unrelated pages must not enqueue CSS.' );
$GLOBALS['test_head'] = true;
$late = WOOPC_Shortcode::render( array() );
check( false !== strpos( $late, '<!-- test style printed -->' ), 'Late nested shortcode must print its stylesheet before the component.' );
$GLOBALS['test_head'] = false;
$tag = WOOPC_Shortcode::script_tag( '<script src="planner.js"></script>', 'woo-party-chef' );
check( false !== strpos( $tag, 'data-nowprocket defer' ), 'Planner script needs selective Delay JS exclusion and defer.' );
check( 1 === substr_count( WOOPC_Shortcode::script_tag( '<script defer src="planner.js"></script>', 'woo-party-chef' ), ' defer' ), 'Defer must not be duplicated.' );
check( '<script src="other.js"></script>' === WOOPC_Shortcode::script_tag( '<script src="other.js"></script>', 'other' ), 'Other script tags must be unchanged.' );

$purger = new Test_Kinsta_Purger();
$GLOBALS['kinsta_cache'] = (object) array( 'kinsta_cache_purge' => $purger );
$GLOBALS['test_options'][WOOPC_Cache_Purger::PAGES_OPTION] = array( 42, 43, 99 );
WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::flush(); WOOPC_Cache_Purger::flush();
check( array( array( 42, 'post' ), array( 43, 'post' ) ) === $purger->calls, 'Kinsta fallback must purge published pages once.' );
check( array( 42, 43 ) === $GLOBALS['test_rocket_calls'], 'WP Rocket must purge published pages once.' );
$purger->calls = array();
add_action( 'after_rocket_clean_post', array( new \WP_Rocket\ThirdParty\Hostings\Kinsta(), 'clean_kinsta_post_cache' ) );
WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::flush();
check( array( array( 42, 'post' ), array( 43, 'post' ) ) === $purger->calls, 'Native Kinsta bridge must not get duplicate fallback purges.' );
unset( $GLOBALS['wp_filter']['after_rocket_clean_post'] );
unset( $GLOBALS['kinsta_cache'] );
WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::flush();
check( true, 'Non-Kinsta hosting must remain supported.' );
do_action( 'woocommerce_scheduled_sales' );
$previous = count( $GLOBALS['test_rocket_calls'] ); WOOPC_Cache_Purger::flush();
check( $previous + 2 === count( $GLOBALS['test_rocket_calls'] ), 'Scheduled sales must refresh comparator pages.' );
WOOPC_Cache_Purger::on_product_changed( 1, $GLOBALS['test_products'][1] );
check( false === get_transient( WOOPC_Products::IDS_TRANSIENT ), 'Catalogue saves must invalidate ID resolution.' );
WOOPC_Cache_Purger::flush();

foreach ( array( null, 'broken', array( 'grey' => null ), array( 'constructor' => $catalogue['grey'] ), array( 'grey' => array_replace( $catalogue['grey'], array( 'products' => array() ) ) ) ) as $bad ) {
	add_filter( 'woopc_cdp_catalogue', static function () use ( $bad ) { return $bad; } );
	check( array() === WOOPC_Products::get_colors_data(), 'Malformed catalogue must fail closed without PHP errors.' );
	unset( $GLOBALS['test_filters']['woopc_cdp_catalogue'] );
}
$changed = $catalogue;
$changed['grey']['products']['4']['sku'] = 'new-reference';
$changed['grey']['products']['4']['slug'] = 'new-reference';
add_filter( 'woopc_cdp_catalogue', static function () use ( $changed ) { return $changed; } );
check( 2 === count( WOOPC_Products::get_colors_data() ), 'Catalogue changes must bypass stale ID caches.' );
unset( $GLOBALS['test_filters']['woopc_cdp_catalogue'] );
$valid_ids = WOOPC_Products::get_product_ids();
$GLOBALS['test_transients'][WOOPC_Products::IDS_TRANSIENT]['ids']['grey']['4'] = array( 9 );
check( $valid_ids === WOOPC_Products::get_product_ids(), 'Corrupted transient IDs must be resolved again.' );
$GLOBALS['test_products'][1]->price = INF;
check( 2 === count( WOOPC_Products::get_colors_data() ), 'Non-finite prices must not reach JSON.' );
$GLOBALS['test_products'][1]->price = 0.001;
check( 2 === count( WOOPC_Products::get_colors_data() ), 'Sub-cent prices rounded to zero must not produce invalid discount calculations.' );
$GLOBALS['test_products'][1]->price = array( 123 );
check( 2 === count( WOOPC_Products::get_colors_data() ), 'Non-numeric price types must not be silently cast to a price.' );
$GLOBALS['test_products'][1]->price = 159.95;
$GLOBALS['test_bad_url'] = true;
check( array() === WOOPC_Products::get_colors_data(), 'Unsafe product URLs must not reach JavaScript href updates.' );
$GLOBALS['test_bad_url'] = false;
WOOPC_Shortcode::render( array( 'persons' => array(), 'anchor' => new stdClass(), 'default_color' => null ) );
check( true, 'Non-scalar shortcode attributes must not cause warnings.' );
add_filter( 'shortcode_atts_woo_party_chef', static function () { return 'broken'; } );
WOOPC_Shortcode::render( array() );
check( true, 'Malformed shortcode filters must fall back to defaults.' );
unset( $GLOBALS['test_filters']['shortcode_atts_woo_party_chef'] );
$saved_pages = $GLOBALS['test_options'];
$GLOBALS['test_options'][WOOPC_Cache_Purger::PAGES_OPTION] = array( 42, array(), new stdClass(), 0, 42 );
check( array( 42 ) === WOOPC_Cache_Purger::get_pages(), 'Malformed page registry values must be ignored and IDs deduplicated.' );
$GLOBALS['test_options'][WOOPC_Cache_Purger::PAGES_OPTION] = new stdClass();
check( array() === WOOPC_Cache_Purger::get_pages(), 'Non-array page registry must fail closed.' );
$GLOBALS['test_options'] = $saved_pages;

// Production has no Deluxe products yet: missing optional products keep the long ID cache.
$deluxe_ids = array();
foreach ( $catalogue['deluxe']['products'] as $ref ) {
	$deluxe_ids[] = $GLOBALS['test_skus'][ $ref['sku'] ];
	unset( $GLOBALS['test_skus'][ $ref['sku'] ], $GLOBALS['test_slugs'][ $ref['slug'] ] );
}
WOOPC_Products::get_product_ids( true );
check( 12 * HOUR_IN_SECONDS === ( $GLOBALS['test_transient_ttl'][ WOOPC_Products::IDS_TRANSIENT ] ?? null ), 'Missing Deluxe products must not shorten the ID cache.' );
check( false === strpos( WOOPC_Shortcode::render( array( 'deluxe' => 'yes' ) ), 'data-pick-color="deluxe"' ), 'deluxe="yes" without Deluxe products falls back to the standard lines.' );
foreach ( $catalogue['deluxe']['products'] as $ref ) {
	$restored = array_shift( $deluxe_ids );
	$GLOBALS['test_skus'][ $ref['sku'] ] = $restored;
	$GLOBALS['test_slugs'][ $ref['slug'] ] = $restored;
}
WOOPC_Products::get_product_ids( true );

$states = array();
$states[] = array( 'color' => $combined_color, 'persons' => 10, 'config' => array( 'showPrices' => true, 'showSale' => true, 'discountPct' => false, 'max' => 12 ), 'expected' => WOOPC_Products::compute_state( $combined_color, 10, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) ) );
foreach ( array( 4, 1, 10, 12 ) as $discount_persons ) {
	$states[] = array( 'color' => $discount_color, 'persons' => $discount_persons, 'config' => array( 'showPrices' => true, 'showSale' => true, 'discountPct' => false, 'max' => 12 ), 'expected' => WOOPC_Products::compute_state( $discount_color, $discount_persons, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) ) );
}
foreach ( array( 0.49, 0.50, 1234.50 ) as $discount_amount ) {
	$boundary_color = $discount_color;
	$boundary_color['items']['4']['price'] = 100.00;
	$boundary_color['items']['4']['regular'] = 100.00 + $discount_amount;
	$states[] = array( 'color' => $boundary_color, 'persons' => 4, 'config' => array( 'showPrices' => true, 'showSale' => true, 'discountPct' => false, 'max' => 12 ), 'expected' => WOOPC_Products::compute_state( $boundary_color, 4, array( 'show_prices' => true, 'show_sale' => true, 'discount_pct' => false ) ) );
}
foreach ( $colors as $color ) {
	for ( $persons = 0; $persons <= 13; ++$persons ) {
		foreach ( array( false, true ) as $prices ) {
			foreach ( array( false, true ) as $sale ) {
				foreach ( array( false, true ) as $pct ) {
					$settings = array( 'show_prices' => $prices, 'show_sale' => $sale, 'discount_pct' => $pct );
					$browser_color = $color;
					if ( ! $prices ) {
						foreach ( $browser_color['items'] as &$item ) { unset( $item['price'], $item['regular'] ); } unset( $item );
					}
					$states[] = array( 'color' => $browser_color, 'persons' => $persons, 'config' => array( 'showPrices' => $prices, 'showSale' => $sale, 'discountPct' => $pct, 'max' => 12 ), 'expected' => WOOPC_Products::compute_state( $color, $persons, $settings ) );
				}
			}
		}
	}
}
echo json_encode( array( 'checks' => $checks, 'states' => $states, 'html' => array( 'first' => $first, 'second' => $second, 'hidden' => $hidden, 'noPlanner' => $no_planner, 'classic' => $classic, 'deluxe' => $with_deluxe ), 'config' => config_from_html( $first ), 'deluxeConfig' => config_from_html( $with_deluxe ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

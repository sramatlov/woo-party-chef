<?php
/**
 * Chef's Dinner Party product catalogue and recommendation logic.
 *
 * Products are configured by SKU (with a slug fallback for products without a
 * SKU) and resolved to IDs once. Prices, permalinks, images and the PFAS flag
 * are always read live from WooCommerce, so promotions show automatically.
 *
 * The recommendation logic in compute_state() is mirrored 1:1 in
 * assets/woo-party-chef.js. The PHP copy renders the default state for
 * visitors before WP Rocket's delayed JavaScript runs; keep both in sync.
 *
 * @package Woo_Party_Chef
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalogue, price data and recommendation state.
 */
class WOOPC_Products {

	/**
	 * Transient holding the resolved product IDs (IDs only, never objects).
	 */
	const IDS_TRANSIENT = 'woopc_cdp_product_ids_v2';

	/**
	 * Set sizes in persons. 'ext' is the single-station extension set.
	 */
	const SIZES = array( 4, 5, 6, 8 );

	/**
	 * Power per cooking station in watts.
	 */
	const WATT_PER_STATION = 250;

	/**
	 * Highest selectable number of persons.
	 */
	const MAX_PERSONS = 12;

	/**
	 * Returns the configured catalogue.
	 *
	 * Filterable through `woopc_cdp_catalogue` so SKUs or slugs can be changed
	 * without editing the plugin.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_catalogue(): array {
		$catalogue = array(
			'wit'  => array(
				'label'    => 'Wit',
				'name'     => 'Chef’s Dinner Party Wit',
				'swatch'   => '#FFFFFF',
				'products' => array(
					'4'   => array( 'sku' => '16.4005.00.00', 'slug' => 'chefs-dinner-party-gourmetstel' ),
					'5'   => array( 'sku' => '16.4006.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-5-personen' ),
					'6'   => array( 'sku' => '16.4007.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-6-personen' ),
					'8'   => array( 'sku' => '16.4009.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-8-personen-pfas-vrij' ),
					'ext' => array( 'sku' => '', 'slug' => 'chefs-dinner-party-uitbreidingsset' ),
				),
			),
			'grey' => array(
				'label'    => 'Glazed Grey',
				'name'     => 'Chef’s Dinner Party Glazed Grey',
				'swatch'   => '#8a8d90',
				'products' => array(
					'4'   => array( 'sku' => '16.4045.00.00', 'slug' => 'chefs-dinner-party-glazed-grey-gourmetstel' ),
					'5'   => array( 'sku' => '16.4046.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-glazed-grey-5-personen-pfas-vrij' ),
					'6'   => array( 'sku' => '16.4047.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-glazed-grey-6-personen-pfas-vrij' ),
					'8'   => array( 'sku' => '16.4048.00.00', 'slug' => 'chefs-dinner-party-gourmetstel-glazed-grey-8-personen-pfas-vrij' ),
					'ext' => array( 'sku' => '16.4041.00.00', 'slug' => 'chefs-dinner-party-glazed-grey-uitbreidingsset' ),
				),
			),
		);

		$filtered = apply_filters( 'woopc_cdp_catalogue', $catalogue );
		if ( ! is_array( $filtered ) ) {
			return array();
		}

		$validated = array();
		foreach ( $filtered as $color => $config ) {
			if ( ! is_string( $color ) || sanitize_key( $color ) !== $color || '' === $color || in_array( $color, array( '__proto__', 'constructor', 'prototype' ), true ) || ! is_array( $config ) ) {
				continue;
			}
			if ( ! is_string( $config['label'] ?? null ) || ! is_string( $config['name'] ?? null ) || ! is_array( $config['products'] ?? null ) ) {
				continue;
			}
			$label = sanitize_text_field( $config['label'] );
			$name  = sanitize_text_field( $config['name'] );
			if ( '' === $label || '' === $name ) {
				continue;
			}
			$products = array();
			foreach ( array_merge( array_map( 'strval', self::SIZES ), array( 'ext' ) ) as $key ) {
				$ref = $config['products'][ $key ] ?? null;
				if ( ! is_array( $ref ) || ! is_string( $ref['sku'] ?? '' ) || ! is_string( $ref['slug'] ?? '' ) ) {
					break;
				}
				$sku  = sanitize_text_field( $ref['sku'] ?? '' );
				$slug = sanitize_title( $ref['slug'] ?? '' );
				if ( '' === $sku && '' === $slug ) {
					break;
				}
				$products[ $key ] = array( 'sku' => $sku, 'slug' => $slug );
			}
			if ( count( $products ) !== count( self::SIZES ) + 1 ) {
				continue;
			}
			$validated[ $color ] = array(
				'label'    => $label,
				'name'     => $name,
				'swatch'   => is_string( $config['swatch'] ?? null ) ? ( sanitize_hex_color( $config['swatch'] ) ?: '#FFFFFF' ) : '#FFFFFF',
				'products' => $products,
			);
		}
		return $validated;
	}

	/**
	 * Resolves every configured product to a product ID (0 when not found).
	 *
	 * @param bool $refresh Ignore the transient and resolve again.
	 * @return array<string, array<string, int>> color => key => product ID.
	 */
	public static function get_product_ids( bool $refresh = false ): array {
		$catalogue = self::get_catalogue();
		$signature = md5( (string) wp_json_encode( $catalogue ) );
		if ( ! $refresh ) {
			$cached = get_transient( self::IDS_TRANSIENT );
			if ( is_array( $cached ) && ( $cached['signature'] ?? null ) === $signature && self::valid_cached_ids( $cached['ids'] ?? null, $catalogue ) ) {
				return $cached['ids'];
			}
		}

		$ids = array();
		$complete = ! empty( $catalogue );
		foreach ( $catalogue as $color => $config ) {
			foreach ( (array) ( $config['products'] ?? array() ) as $key => $ref ) {
				$ids[ $color ][ (string) $key ] = self::resolve_product_id( (array) $ref );
				$complete = $complete && $ids[ $color ][ (string) $key ] > 0;
			}
		}

		// Short lifetime when something is missing so a fixed SKU shows up quickly.
		set_transient( self::IDS_TRANSIENT, array( 'signature' => $signature, 'ids' => $ids ), $complete ? 12 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );

		return $ids;
	}

	/** Rejects corrupted ID caches and catalogue changes. */
	private static function valid_cached_ids( $ids, array $catalogue ): bool {
		if ( ! is_array( $ids ) || array_keys( $ids ) !== array_keys( $catalogue ) ) {
			return false;
		}
		foreach ( $catalogue as $color => $config ) {
			if ( ! is_array( $ids[ $color ] ) || array_keys( $ids[ $color ] ) !== array_keys( $config['products'] ) ) {
				return false;
			}
			foreach ( $ids[ $color ] as $id ) {
				if ( ! is_int( $id ) || $id < 0 ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Returns a flat list of all resolved product IDs.
	 *
	 * @return int[]
	 */
	public static function get_flat_product_ids(): array {
		$flat = array();
		foreach ( self::get_product_ids() as $keys ) {
			foreach ( $keys as $id ) {
				if ( $id > 0 ) {
					$flat[] = (int) $id;
				}
			}
		}
		return array_values( array_unique( $flat ) );
	}

	/**
	 * Whether a product (by ID, SKU or slug) belongs to the catalogue.
	 *
	 * @param \WC_Product $product Product to test.
	 * @return bool
	 */
	public static function is_catalogue_product( \WC_Product $product ): bool {
		if ( in_array( $product->get_id(), self::get_flat_product_ids(), true ) ) {
			return true;
		}

		$sku  = (string) $product->get_sku();
		$slug = (string) $product->get_slug();
		foreach ( self::get_catalogue() as $config ) {
			foreach ( (array) ( $config['products'] ?? array() ) as $ref ) {
				if ( ( '' !== $sku && $sku === ( $ref['sku'] ?? '' ) ) || ( '' !== $slug && $slug === ( $ref['slug'] ?? '' ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolves one catalogue reference: SKU first, then product slug.
	 *
	 * @param array<string, string> $ref Reference with 'sku' and 'slug'.
	 * @return int
	 */
	private static function resolve_product_id( array $ref ): int {
		$sku = (string) ( $ref['sku'] ?? '' );
		if ( '' !== $sku ) {
			$id = (int) wc_get_product_id_by_sku( $sku );
			if ( $id > 0 ) {
				return $id;
			}
		}

		$slug = (string) ( $ref['slug'] ?? '' );
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $post instanceof \WP_Post ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}

	/**
	 * Builds the live data for every complete color.
	 *
	 * A color is only offered when all five of its products exist, are
	 * published and have a price. A half-filled table would give wrong totals.
	 *
	 * @param array<string, int> $image_overrides color => attachment ID.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_colors_data( array $image_overrides = array() ): array {
		$ids       = self::get_product_ids();
		$catalogue = self::get_catalogue();
		$data      = array();

		if ( function_exists( 'wc_prime_caches_for_products' ) ) {
			// Not available in older WooCommerce versions; purely a performance nicety.
			wc_prime_caches_for_products( self::get_flat_product_ids() );
		}

		foreach ( $catalogue as $color => $config ) {
			$items    = array();
			$complete = true;

			foreach ( array_keys( (array) ( $config['products'] ?? array() ) ) as $key ) {
				$key     = (string) $key;
				$product = wc_get_product( $ids[ $color ][ $key ] ?? 0 );
				$item    = $product instanceof \WC_Product ? self::get_item_data( $product ) : null;
				if ( null === $item ) {
					$complete = false;
					break;
				}
				$items[ $key ] = $item;
			}

			if ( ! $complete || ! isset( $items['ext'] ) || count( $items ) !== count( self::SIZES ) + 1 ) {
				continue;
			}

			// Products without a featured image fall back to the color's override
			// image, then to the 4-person set's image.
			$fallback = (int) ( $image_overrides[ $color ] ?? 0 );
			if ( $fallback <= 0 ) {
				$fallback = $items['4']['image'];
			}
			foreach ( $items as $key => $item ) {
				if ( $item['image'] <= 0 ) {
					$items[ $key ]['image'] = $fallback;
				}
			}

			$data[ $color ] = array(
				'label'  => (string) $config['label'],
				'name'   => (string) $config['name'],
				'swatch' => (string) $config['swatch'],
				'items'  => $items,
			);
		}

		return $data;
	}

	/**
	 * Returns prices, URL, image and PFAS flag for one product, or null when unusable.
	 *
	 * Prices follow the shop's tax display setting, like the price WooCommerce
	 * itself shows on the product page.
	 *
	 * @param \WC_Product $product Product.
	 * @return array{price: float, regular: float, url: string, image: int, pfas: bool}|null
	 */
	private static function get_item_data( \WC_Product $product ): ?array {
		if ( 'publish' !== $product->get_status() ) {
			return null;
		}

		$display_price   = wc_get_price_to_display( $product );
		$display_regular = wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
		if ( ! is_numeric( $display_price ) || ! is_numeric( $display_regular ) ) {
			return null;
		}
		$price   = (float) $display_price;
		$regular = (float) $display_regular;

		if ( ! is_finite( $price ) || ! is_finite( $regular ) || round( $price, 2 ) <= 0 || $price > PHP_FLOAT_MAX / self::MAX_PERSONS || $regular > PHP_FLOAT_MAX / self::MAX_PERSONS ) {
			return null;
		}
		if ( $regular < $price ) {
			$regular = $price;
		}
		$url = esc_url_raw( (string) get_permalink( $product->get_id() ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return null;
		}

		return array(
			'price'   => round( $price, 2 ),
			'regular' => round( $regular, 2 ),
			'url'     => $url,
			'image'   => (int) $product->get_image_id(),
			// Same ACF true/false field as Woo Card Chef's PFAS-vrij badge.
			'pfas'    => '1' === (string) get_post_meta( $product->get_id(), 'badge_pfas_vrij', true ),
		);
	}

	/**
	 * Computes the planner/comparison state for a color and person count.
	 *
	 * Mirrored in assets/woo-party-chef.js (compute()). Keep both in sync.
	 *
	 * @param array<string, mixed> $color    One entry from get_colors_data().
	 * @param int                  $persons  Number of persons (1-12).
	 * @param array<string, mixed> $settings show_prices, show_sale, discount_pct.
	 * @return array<string, mixed>
	 */
	public static function compute_state( array $color, int $persons, array $settings ): array {
		$n          = max( 1, min( self::MAX_PERSONS, $persons ) );
		$items      = $color['items'];
		$show_price = ! empty( $settings['show_prices'] );
		$show_sale  = ! empty( $settings['show_sale'] );
		$pct        = ! empty( $settings['discount_pct'] );

		if ( $n <= 8 ) {
			$set = 8;
			foreach ( self::SIZES as $size ) {
				if ( $size >= $n ) {
					$set = $size;
					break;
				}
			}
			$ext = 0;
		} else {
			$set = 8;
			$ext = $n - 8;
		}

		// 12 persons: a second 4-person set instead of four single stations.
		$set2 = 0;
		if ( 12 === $n ) {
			$set2 = 4;
			$ext  = 0;
		}

		$solo = 1 === $n;
		if ( $solo ) {
			$set = 0;
			$ext = 1;
		}

		$spare     = $set + $set2 + $ext - $n;
		$total     = $show_price ? ( $solo ? 0.0 : $items[ (string) $set ]['price'] ) + ( $set2 ? $items[ (string) $set2 ]['price'] : 0.0 ) + $ext * $items['ext']['price'] : 0.0;
		$total_was = $show_price ? ( $solo ? 0.0 : $items[ (string) $set ]['regular'] ) + ( $set2 ? $items[ (string) $set2 ]['regular'] : 0.0 ) + $ext * $items['ext']['regular'] : 0.0;
		$on_sale   = $show_price && $show_sale && $total < $total_was - 0.004;
		$stations  = $set + $set2 + $ext;

		if ( $solo ) {
			$title = $color['name'] . ' uitbreidingsset';
			$text  = 'Eén kookstation van 250 W met eigen keramisch bord en bakplaat. Later uit te breiden tot een complete set.';
		} else {
			$title = $set2 ? $set . ' personen + ' . $set2 . ' personen' : ( $ext ? $set . ' personen + ' . self::plural_ext( $ext ) : $color['name'] . ' voor ' . $set . ' personen' );
			$text  = $stations . ' kookstations van 250 W, samen ' . self::format_watt( $stations * self::WATT_PER_STATION ) . '.';
			if ( $spare > 0 ) {
				$text .= ' Je houdt ' . ( 1 === $spare ? 'één plek' : $spare . ' plekken' ) . ' over voor een extra gast.';
			}
			if ( $set2 ) {
				$text .= ' Twee complete sets: één voor ' . $set . ' en één voor ' . $set2 . ' personen.';
			} elseif ( $ext ) {
				$text .= ' Elke uitbreidingsset voegt één eigen kookstation met bord en bakplaat toe.';
			}
		}

		$alt = '';
		if ( 7 === $n ) {
			$alt = 'Liever precies 7 plekken? Kies de set voor 6 personen met 1 uitbreidingsset'
				. ( $show_price ? ' voor ' . self::format_eur( $items['6']['price'] + $items['ext']['price'] ) : '' ) . '.';
		} elseif ( $n >= 2 && $n <= 3 ) {
			$alt = 'De kleinste set is voor 4 personen. Zo heb je meteen ruimte als er iemand aanschuift.';
		}

		$dots = array();
		for ( $i = 0; $i < $set; $i++ ) {
			$dots[] = $i < $n ? 'guest' : 'spare';
		}
		for ( $i = 0; $i < $set2; $i++ ) {
			$dots[] = 'guest';
		}
		for ( $i = 0; $i < $ext; $i++ ) {
			$dots[] = 'ext';
		}

		$cols = array();
		foreach ( array_merge( array_map( 'strval', self::SIZES ), array( 'ext' ) ) as $key ) {
			$item         = $items[ $key ];
			$col_sale     = $show_price && $show_sale && $item['price'] < $item['regular'] - 0.004;
			$diff         = $show_price ? $item['regular'] - $item['price'] : 0.0;
			$cols[ $key ] = array(
				'active' => 'ext' === $key ? $ext > 0 : ( (int) $key === $set || (int) $key === $set2 ),
				'sale'   => $col_sale,
				'price'  => $show_price ? self::format_eur( $item['price'] ) : '',
				'was'    => $show_price ? self::format_eur( $item['regular'] ) : '',
				'badge'  => ! $show_price ? '' : ( $pct
					? '-' . (int) round( $diff / $item['regular'] * 100 ) . '%'
					: '-€' . (int) round( $diff ) ),
				'url'    => $item['url'],
			);
		}

		return array(
			'n'         => $n,
			'title'     => $title,
			'text'      => $text,
			'alt'       => $alt,
			'dots'      => $dots,
			'has_ext'   => $ext > 0,
			'has_spare' => $spare > 0,
			'ext_link'  => ( $ext > 0 || $set2 > 0 ) && ! $solo,
			'ext_label' => $set2 ? 'Voeg de set voor ' . $set2 . ' personen toe →' : 'Voeg ' . self::plural_ext( $ext ) . ' toe →',
			'ext_url'   => $set2 ? $items[ (string) $set2 ]['url'] : $items['ext']['url'],
			'total'     => $show_price ? self::format_eur( $total ) : '',
			'total_was' => $show_price ? self::format_eur( $total_was ) : '',
			'save'      => $show_price ? 'Je bespaart ' . self::format_eur( $total_was - $total ) : '',
			'on_sale'   => $on_sale,
			'cta_url'   => $solo ? $items['ext']['url'] : $items[ (string) $set ]['url'],
			// Product shown in the planner: the main purchase (8-set for 9-12 persons).
			'image'     => $solo ? 'ext' : (string) $set,
			'cols'      => $cols,
		);
	}

	/**
	 * "1 uitbreidingsset" / "N uitbreidingssets".
	 *
	 * @param int $count Count.
	 * @return string
	 */
	private static function plural_ext( int $count ): string {
		return 1 === $count ? '1 uitbreidingsset' : $count . ' uitbreidingssets';
	}

	/**
	 * Formats watts with a Dutch thousands separator ("3.000 W").
	 *
	 * @param int $watt Watts.
	 * @return string
	 */
	public static function format_watt( int $watt ): string {
		return number_format( $watt, 0, ',', '.' ) . ' W';
	}

	/**
	 * Formats an amount as "€ 1.234,56" (design spec; mirrored in JS).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_eur( float $amount ): string {
		return '€ ' . number_format( max( 0.0, $amount ), 2, ',', '.' );
	}
}

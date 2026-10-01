<?php
/**
 * Keeps cached comparison pages in sync with WooCommerce prices.
 *
 * WP Rocket clears a product's own page when it is saved, but not other pages
 * that show its price. This class remembers which pages render the shortcode
 * and clears exactly those pages when a catalogue product changes, including
 * scheduled sales that start or end through WooCommerce's cron.
 *
 * @package Woo_Party_Chef
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page registry and targeted cache purging.
 */
class WOOPC_Cache_Purger {

	/**
	 * Option holding the IDs of pages that render the shortcode.
	 */
	const PAGES_OPTION = 'woopc_cdp_pages';

	/**
	 * Upper bound for remembered pages.
	 */
	const MAX_PAGES = 20;

	/**
	 * Whether a purge is queued for the end of this request.
	 *
	 * @var bool
	 */
	private static $queued = false;

	/**
	 * Registers hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_changed' ), 20, 2 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product_changed' ), 20, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_changed' ), 20, 3 );

		// Scheduled sales save each product (caught above); this is a cheap
		// once-a-day safety net in case a sale is applied without a save.
		add_action( 'woocommerce_scheduled_sales', array( __CLASS__, 'queue' ), 20 );

		add_action( 'shutdown', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Remembers a page that renders the shortcode.
	 *
	 * Called on a frontend render, which on a cached site only happens when the
	 * page is (re)built, so the option is written rarely.
	 *
	 * @param int $post_id Page ID.
	 */
	public static function remember_page( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		$pages = self::get_pages();
		if ( in_array( $post_id, $pages, true ) ) {
			return;
		}

		$pages[] = $post_id;
		$pages   = array_slice( $pages, -self::MAX_PAGES );

		if ( false === get_option( self::PAGES_OPTION, false ) ) {
			add_option( self::PAGES_OPTION, $pages, '', false );
		} else {
			update_option( self::PAGES_OPTION, $pages, false );
		}
	}

	/**
	 * Returns remembered page IDs.
	 *
	 * @return int[]
	 */
	public static function get_pages(): array {
		$stored = get_option( self::PAGES_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return self::sanitize_page_ids( $stored );
	}

	/** Ignore corrupt values and deduplicate page IDs before any cache operation. */
	private static function sanitize_page_ids( array $values ): array {
		return array_values( array_unique( array_filter( array_map(
			static function ( $id ): int {
				return is_scalar( $id ) ? absint( $id ) : 0;
			},
			$values
		) ) ) );
	}

	/**
	 * Queues a purge when a catalogue product is created or saved.
	 *
	 * @param int        $product_id Product ID.
	 * @param mixed|null $product    Product object (WooCommerce passes it since 3.x).
	 */
	public static function on_product_changed( $product_id, $product = null ): void {
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( $product_id );
		}
		if ( ! $product instanceof \WC_Product || ! WOOPC_Products::is_catalogue_product( $product ) ) {
			return;
		}

		// SKU, slug or status may have changed: resolve the IDs again next time.
		delete_transient( WOOPC_Products::IDS_TRANSIENT );
		self::queue();
	}

	/**
	 * Queues a purge when a catalogue product is published, unpublished or trashed.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_status_changed( $new_status, $old_status, $post ): void {
		if ( $new_status === $old_status || ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		self::on_product_changed( $post->ID );
	}

	/**
	 * Queues a purge for the end of the request (bulk edits purge once).
	 */
	public static function queue(): void {
		self::$queued = true;
	}

	/**
	 * Clears the cache of every remembered page that is still published.
	 */
	public static function flush(): void {
		if ( ! self::$queued ) {
			return;
		}
		self::$queued = false;

		$pages = array_values(
			array_filter(
				self::get_pages(),
				static function ( int $id ): bool {
					return 'publish' === get_post_status( $id );
				}
			)
		);

		/**
		 * Filters the page IDs whose cache is cleared after a price change.
		 *
		 * @param int[] $pages Page IDs.
		 */
		$filtered = apply_filters( 'woopc_cdp_purge_post_ids', $pages );
		$pages    = is_array( $filtered ) ? self::sanitize_page_ids( $filtered ) : array();
		$rocket_kinsta = function_exists( 'rocket_clean_post' ) && self::rocket_handles_kinsta();

		foreach ( $pages as $page_id ) {
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $page_id );
			}
			clean_post_cache( $page_id );
			if ( ! $rocket_kinsta ) {
				self::purge_kinsta_page( $page_id );
			}
		}

		/**
		 * Fires after comparison pages were purged. Hook other page caches here.
		 *
		 * @param int[] $pages Page IDs.
		 */
		do_action( 'woopc_cdp_pages_purged', $pages );
	}

	/** WP Rocket's native Kinsta bridge already purges each cleaned post. */
	private static function rocket_handles_kinsta(): bool {
		global $wp_filter;
		$hook = $wp_filter['after_rocket_clean_post'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return false;
		}
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$handler = $callback['function'] ?? null;
				if ( is_array( $handler ) && isset( $handler[0], $handler[1] ) && $handler[0] instanceof \WP_Rocket\ThirdParty\Hostings\Kinsta && 'clean_kinsta_post_cache' === $handler[1] ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Targeted Kinsta MU-plugin fallback; never flushes the entire site. */
	private static function purge_kinsta_page( int $page_id ): void {
		global $kinsta_cache;
		if ( ! is_object( $kinsta_cache ) || ! isset( $kinsta_cache->kinsta_cache_purge ) ) {
			return;
		}
		$purger = $kinsta_cache->kinsta_cache_purge;
		if ( is_object( $purger ) && is_callable( array( $purger, 'initiate_purge' ) ) ) {
			$purger->initiate_purge( $page_id, 'post' );
		}
	}
}

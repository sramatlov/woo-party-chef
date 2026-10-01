<?php
/**
 * Plugin Name:       Woo Party Chef
 * Description:       Chef's Dinner Party set planner and comparison with live WooCommerce prices. Shortcode: [woo_party_chef].
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            S15 Webdesign
 * Author URI:        https://vaneekerenindustries.nl
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-party-chef
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 *
 * @package Woo_Party_Chef
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOOPC_VERSION', '1.1.0' );
define( 'WOOPC_FILE', __FILE__ );
define( 'WOOPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOPC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declares HPOS compatibility. The plugin never touches orders.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once WOOPC_DIR . 'includes/class-products.php';
require_once WOOPC_DIR . 'includes/class-cache-purger.php';
require_once WOOPC_DIR . 'includes/class-shortcode.php';

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		WOOPC_Cache_Purger::init();
		WOOPC_Shortcode::init();
	}
);

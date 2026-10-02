<?php
/** Minimal deterministic WordPress/WooCommerce doubles; never shipped in the plugin ZIP. */
define( 'ABSPATH', __DIR__ );
define( 'WOOPC_VERSION', '1.2.0' );
define( 'WOOPC_URL', 'https://example.test/plugins/woo-party-chef/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );
set_error_handler( static function ( $severity, $message, $file, $line ): bool {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
	return false;
} );

class WP_Post {
	public $ID;
	public $post_content;
	public $post_type = 'page';
	public function __construct( int $id, string $content = '' ) { $this->ID = $id; $this->post_content = $content; }
}
class WP_Hook { public $callbacks = array(); }
class WC_Product {
	public $id;
	public $status = 'publish';
	public $price = 99.95;
	public $regular = 119.95;
	public $sku = '';
	public $slug = '';
	public function __construct( int $id ) { $this->id = $id; }
	public function get_id(): int { return $this->id; }
	public function get_status(): string { return $this->status; }
	public function get_regular_price() { return $this->regular; }
	public function get_image_id(): int { return $this->id; }
	public function get_sku(): string { return $this->sku; }
	public function get_slug(): string { return $this->slug; }
	public function is_in_stock(): bool { return false; }
	public function is_purchasable(): bool { return false; }
}
class Test_Kinsta_Purger {
	public $calls = array();
	public function initiate_purge( $id, $type ): void { $this->calls[] = array( $id, $type ); }
}

$GLOBALS['test_filters'] = array();
$GLOBALS['wp_filter'] = array();
$GLOBALS['test_actions'] = array();
$GLOBALS['test_options'] = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_products'] = array();
$GLOBALS['test_skus'] = array();
$GLOBALS['test_slugs'] = array();
$GLOBALS['test_meta'] = array();
$GLOBALS['test_styles'] = array();
$GLOBALS['test_scripts'] = array();
$GLOBALS['test_rocket_calls'] = array();
$GLOBALS['test_clean_calls'] = array();
$GLOBALS['test_post'] = new WP_Post( 42 );
$GLOBALS['test_admin'] = false;
$GLOBALS['test_ajax'] = false;
$GLOBALS['test_preview'] = false;
$GLOBALS['test_head'] = false;
$GLOBALS['test_bad_url'] = false;

function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['test_filters'][$tag][$priority][] = array( $callback, $accepted );
}
function apply_filters( $tag, $value, ...$args ) {
	$groups = $GLOBALS['test_filters'][$tag] ?? array(); ksort( $groups );
	foreach ( $groups as $callbacks ) {
		foreach ( $callbacks as $pair ) { $value = call_user_func_array( $pair[0], array_slice( array_merge( array( $value ), $args ), 0, $pair[1] ) ); }
	}
	return $value;
}
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
	if ( ! isset( $GLOBALS['wp_filter'][$tag] ) ) { $GLOBALS['wp_filter'][$tag] = new WP_Hook(); }
	$GLOBALS['wp_filter'][$tag]->callbacks[$priority][] = array( 'function' => $callback, 'accepted_args' => $accepted );
}
function do_action( $tag, ...$args ) {
	$GLOBALS['test_actions'][$tag] = ( $GLOBALS['test_actions'][$tag] ?? 0 ) + 1;
	$groups = $GLOBALS['wp_filter'][$tag]->callbacks ?? array(); ksort( $groups );
	foreach ( $groups as $callbacks ) {
		foreach ( $callbacks as $pair ) { call_user_func_array( $pair['function'], array_slice( $args, 0, $pair['accepted_args'] ) ); }
	}
}
function did_action( $tag ) { return 'wp_head' === $tag ? (int) $GLOBALS['test_head'] : ( $GLOBALS['test_actions'][$tag] ?? 0 ); }
function add_shortcode( $tag, $callback ) {}
function get_transient( $key ) { return $GLOBALS['test_transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['test_transients'][$key] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['test_transients'][$key] ); }
function get_option( $key, $default = false ) { return $GLOBALS['test_options'][$key] ?? $default; }
function add_option( $key, $value, $unused = '', $autoload = false ) { $GLOBALS['test_options'][$key] = $value; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['test_options'][$key] = $value; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ); }
function sanitize_title( $v ) { return trim( preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $v ) ), '-' ); }
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function sanitize_hex_color( $v ) { return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $v ) ? $v : null; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return esc_attr( $v ); }
function esc_html__( $v, $domain ) { return esc_html( $v ); }
function esc_url_raw( $v, $protocols = array() ) { return preg_match( '#^https?://#', $v ) ? $v : ''; }
function esc_url( $v ) { return esc_attr( esc_url_raw( $v ) ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unique_id( $prefix = '' ) { static $counter = 0; return $prefix . ++$counter; }
function shortcode_atts( $defaults, $atts, $tag ) { return apply_filters( 'shortcode_atts_' . $tag, array_replace( $defaults, array_intersect_key( $atts, $defaults ) ) ); }
function current_user_can( $cap ) { return false; }
function disabled( $v ) { if ( $v ) { echo ' disabled'; } }
function is_admin() { return $GLOBALS['test_admin']; }
function wp_doing_ajax() { return $GLOBALS['test_ajax']; }
function is_preview() { return $GLOBALS['test_preview']; }
function is_singular() { return true; }
function get_queried_object_id() { return $GLOBALS['test_post']->ID; }
function get_post( $id ) { return $GLOBALS['test_post']; }
function get_post_status( $id ) { return 99 === $id ? 'draft' : 'publish'; }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['test_meta'][$id][$key] ?? ( 'badge_pfas_vrij' === $key ? '0' : '' ); }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function get_page_by_path( $slug, $output, $type ) { return isset( $GLOBALS['test_slugs'][$slug] ) ? new WP_Post( $GLOBALS['test_slugs'][$slug] ) : null; }
function wc_get_product_id_by_sku( $sku ) { return $GLOBALS['test_skus'][$sku] ?? 0; }
function wc_get_product( $id ) { return $GLOBALS['test_products'][$id] ?? false; }
function wc_get_price_to_display( $product, $args = array() ) { return $args['price'] ?? $product->price; }
function get_permalink( $id ) { return $GLOBALS['test_bad_url'] ? 'javascript:alert(1)' : 'https://example.test/product/' . $id; }
function wp_register_style( $handle, $src, $deps, $version ) { $GLOBALS['test_styles'][$handle] = 'registered'; }
function wp_register_script( ...$args ) { $GLOBALS['test_scripts']['registered'] = $args; }
function wp_script_add_data( $handle, $key, $value ) { $GLOBALS['test_scripts'][$key] = $value; }
function wp_style_is( $handle, $status ) { return 'registered' === $status ? isset( $GLOBALS['test_styles'][$handle] ) : ( $GLOBALS['test_styles'][$handle] ?? '' ) === $status; }
function wp_enqueue_style( $handle ) { $GLOBALS['test_styles'][$handle] = 'enqueued'; }
function wp_enqueue_script( $handle ) { $GLOBALS['test_scripts']['enqueued'] = $handle; }
function wp_print_styles( $handles ) { foreach ( $handles as $handle ) { echo '<!-- test style printed -->'; $GLOBALS['test_styles'][$handle] = 'done'; } }
function clean_post_cache( $id ) { $GLOBALS['test_clean_calls'][] = $id; }
if ( ! defined( 'WOOPC_TEST_NO_ROCKET' ) ) {
	function rocket_clean_post( $id ) {
		$GLOBALS['test_rocket_calls'][] = $id;
		do_action( 'after_rocket_clean_post', new WP_Post( $id ) );
	}
}
function wp_get_attachment_image( $id, $size, $icon, $attr ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect x="40" y="80" width="520" height="440" rx="40" fill="#eeeeee"/><text x="300" y="310" text-anchor="middle" font-family="Arial" font-size="40" fill="#444444">Test product ' . $id . '</text></svg>';
	$html = '<img src="data:image/svg+xml;base64,' . base64_encode( $svg ) . '" width="600" height="600"';
	foreach ( $attr as $key => $value ) { $html .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	return '<picture>' . $html . '></picture>';
}

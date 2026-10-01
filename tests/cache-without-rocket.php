<?php
define( 'WOOPC_TEST_NO_ROCKET', true );
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../woo-party-chef/includes/class-products.php';
require __DIR__ . '/../woo-party-chef/includes/class-cache-purger.php';
$purger = new Test_Kinsta_Purger();
$GLOBALS['kinsta_cache'] = (object) array( 'kinsta_cache_purge' => $purger );
$GLOBALS['test_options'][WOOPC_Cache_Purger::PAGES_OPTION] = array( 42, 99 );
WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::flush();
if ( array( array( 42, 'post' ) ) !== $purger->calls || array( 42 ) !== $GLOBALS['test_clean_calls'] ) {
	throw new RuntimeException( 'Kinsta must also receive a targeted purge without WP Rocket.' );
}
unset( $GLOBALS['kinsta_cache'] );
WOOPC_Cache_Purger::queue(); WOOPC_Cache_Purger::flush();
echo "PASS: Kinsta-only and object-cache-only hosting.\n";

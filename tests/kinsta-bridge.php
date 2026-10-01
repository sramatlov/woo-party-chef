<?php
namespace WP_Rocket\ThirdParty\Hostings;

/** Mirrors WP Rocket's documented post-cache bridge contract. */
class Kinsta {
	public function clean_kinsta_post_cache( $post ): void {
		$GLOBALS['kinsta_cache']->kinsta_cache_purge->initiate_purge( $post->ID, 'post' );
	}
}

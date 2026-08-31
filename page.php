<?php
/**
 * Router for page.twig.
 *
 * Timber templates carry the markup; these files exist only because WordPress
 * routes to PHP. Keep them thin — logic belongs in inc/, markup in templates/.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Timber\Timber::render( 'page.twig', Timber\Timber::context() );

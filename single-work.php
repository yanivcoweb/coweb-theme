<?php
/**
 * Router for single-work.twig.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Timber\Timber::render( 'single-work.twig', Timber\Timber::context() );

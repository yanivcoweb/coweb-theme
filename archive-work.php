<?php
/**
 * Router for archive-work.twig.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Timber\Timber::render( 'archive-work.twig', Timber\Timber::context() );

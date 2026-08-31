<?php
/**
 * Router for search.twig.
 *
 * Timber templates carry the markup; these files exist only because WordPress
 * routes to PHP. Keep them thin — logic belongs in inc/, markup in templates/.
 *
 * The two extra context values are page-specific by definition, so they are
 * added here rather than in the global context in inc/timber.php, which is
 * built on every request.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context = Timber\Timber::context();

$context['search_query'] = get_search_query();
$context['result_count'] = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );

Timber\Timber::render( 'search.twig', $context );

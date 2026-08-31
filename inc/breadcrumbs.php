<?php
/**
 * Breadcrumb trail, derived from the request.
 *
 * Generated, never authored — an ACF breadcrumb field goes stale the moment a
 * page is moved under a different parent, and nobody notices for months.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the trail for the current request.
 *
 * The last entry is the current page and deliberately carries a null url —
 * the template renders it as plain text, because a link to the page you are
 * already on is noise for everyone and a trap for screen reader users.
 *
 * @return array<int,array{title:string,url:?string}>
 */
function coweb_breadcrumbs(): array {
	$crumbs = array(
		array(
			'title' => 'בית',
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post_id   = get_queried_object_id();
		$post_type = (string) get_post_type( $post_id );

		if ( 'page' === $post_type ) {
			foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor_id ) {
				$crumbs[] = array(
					'title' => get_the_title( $ancestor_id ),
					'url'   => get_permalink( $ancestor_id ),
				);
			}
		} elseif ( 'post' === $post_type ) {
			$blog_id = (int) get_option( 'page_for_posts' );

			if ( $blog_id ) {
				$crumbs[] = array(
					'title' => get_the_title( $blog_id ),
					'url'   => get_permalink( $blog_id ),
				);
			}

			$categories = get_the_category( $post_id );

			if ( $categories ) {
				$crumbs[] = array(
					'title' => $categories[0]->name,
					'url'   => get_category_link( $categories[0] ),
				);
			}
		} else {
			$archive = get_post_type_archive_link( $post_type );
			$object  = get_post_type_object( $post_type );

			if ( $archive && $object ) {
				$crumbs[] = array(
					'title' => $object->labels->name,
					'url'   => $archive,
				);
			}
		}

		$crumbs[] = array(
			'title' => get_the_title( $post_id ),
			'url'   => null,
		);
	} elseif ( is_archive() ) {
		$crumbs[] = array(
			'title' => wp_strip_all_tags( get_the_archive_title() ),
			'url'   => null,
		);
	} elseif ( is_search() ) {
		$crumbs[] = array(
			'title' => 'תוצאות חיפוש',
			'url'   => null,
		);
	} elseif ( is_404() ) {
		$crumbs[] = array(
			'title' => 'הדף לא נמצא',
			'url'   => null,
		);
	}

	return $crumbs;
}

<?php
/**
 * Custom post types.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `work` — case studies.
 *
 * Body copy comes from ACF, not the editor, so the type supports only title,
 * thumbnail and excerpt. The excerpt is what `project-card` shows on the work
 * index; it is not auto-generated from a body that doesn't exist.
 */
add_action(
	'init',
	static function (): void {
		register_post_type(
			'work',
			array(
				'labels'        => array(
					'name'               => __( 'Work', 'coweb' ),
					'singular_name'      => __( 'Project', 'coweb' ),
					'add_new_item'       => __( 'Add project', 'coweb' ),
					'edit_item'          => __( 'Edit project', 'coweb' ),
					'search_items'       => __( 'Search projects', 'coweb' ),
					'not_found'          => __( 'No projects yet', 'coweb' ),
					'menu_name'          => __( 'Work', 'coweb' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-portfolio',
				'menu_position' => 20,
				'supports'      => array( 'title', 'thumbnail', 'excerpt', 'page-attributes' ),
				'rewrite'       => array(
					'slug'       => 'work',
					'with_front' => false,
				),
				'show_in_rest'  => true,
			)
		);

		register_taxonomy(
			'work_tag',
			'work',
			array(
				'labels'            => array(
					'name'          => __( 'Project tags', 'coweb' ),
					'singular_name' => __( 'Project tag', 'coweb' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'work-tag',
					'with_front' => false,
				),
			)
		);
	}
);

/**
 * Filter chips for the work archive: "all" plus every tag actually in use.
 *
 * Terms with no posts are excluded — a filter that leads to an empty result is
 * a dead end, and hiding it is cheaper than explaining it.
 *
 * @return array<int,array{label:string,url:string,active:bool}>
 */
function coweb_work_filters(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'work_tag',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$current = is_tax( 'work_tag' ) ? (int) get_queried_object_id() : 0;

	$filters = array(
		array(
			'label'  => 'הכל',
			'url'    => (string) get_post_type_archive_link( 'work' ),
			'active' => 0 === $current,
		),
	);

	foreach ( $terms as $term ) {
		$filters[] = array(
			'label'  => $term->name,
			'url'    => (string) get_term_link( $term ),
			'active' => $term->term_id === $current,
		);
	}

	return $filters;
}

/**
 * The same chip row for the blog, built from core categories.
 *
 * It lives beside coweb_work_filters() rather than in a blog-specific file
 * because the two are one idea — Figma draws the same `tag-chip` row on
 * `work-index`, `blog-index` and `blog-category`, and the day one of them
 * changes shape the other has to follow.
 *
 * @return array<int,array{label:string,url:string,active:bool}>
 */
function coweb_post_filters(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$current = is_category() ? (int) get_queried_object_id() : 0;

	// The posts page if one is assigned, the front page otherwise — a blog
	// that has not been given its own page still needs somewhere to send
	// "all".
	$blog_id  = (int) get_option( 'page_for_posts' );
	$blog_url = $blog_id ? (string) get_permalink( $blog_id ) : home_url( '/' );

	$filters = array(
		array(
			'label'  => 'הכל',
			'url'    => $blog_url,
			'active' => 0 === $current,
		),
	);

	foreach ( $terms as $term ) {
		$filters[] = array(
			'label'  => $term->name,
			'url'    => (string) get_term_link( $term ),
			'active' => $term->term_id === $current,
		);
	}

	return $filters;
}

/**
 * The three articles under "עוד בנושא" on a single post.
 *
 * Derived, never authored — Figma `related-posts` (21:195). An ACF relationship
 * field would be a third list to keep in sync per post, and the day one of the
 * chosen articles is unpublished nothing tells the editor; this query simply
 * stops returning it.
 *
 * Same category first, because that is what "עוד בנושא" claims. A post in a
 * thin category would otherwise show one card beside two gaps, so the row is
 * topped up with the most recent posts from anywhere — still relevant enough
 * to be worth a click, and it keeps the grid honest. `post__not_in` is applied
 * to both passes, so the article you are reading can never appear under itself
 * and the top-up can never repeat a card.
 *
 * @param int $post_id The article being read.
 * @param int $count   How many cards the row holds.
 * @return array<int,\Timber\Post>
 */
function coweb_related_posts( int $post_id, int $count = 3 ): array {
	$exclude = array( $post_id );
	$found   = array();

	$category_ids = wp_get_post_categories( $post_id );

	$query = static function ( array $args ) use ( &$exclude, &$found ): void {
		$ids = get_posts(
			$args + array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'post__not_in'        => $exclude,
			)
		);

		foreach ( $ids as $id ) {
			$exclude[] = (int) $id;
			$found[]   = (int) $id;
		}
	};

	if ( $category_ids ) {
		$query(
			array(
				'numberposts'  => $count,
				'category__in' => $category_ids,
			)
		);
	}

	if ( count( $found ) < $count ) {
		$query( array( 'numberposts' => $count - count( $found ) ) );
	}

	if ( ! $found ) {
		return array();
	}

	// `orderby => post__in` keeps the two passes in the order they were found:
	// same-category cards first, the top-up after them. Without it WordPress
	// re-sorts by date and shuffles a topped-up row.
	return Timber\Timber::get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $found,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $found ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	)->to_array();
}

/**
 * Reading time in whole minutes, from the rendered content.
 *
 * Derived for the same reason the description and the breadcrumbs are: an
 * authored "7 minutes" is wrong the first time someone edits a paragraph, and
 * nobody ever notices.
 *
 * `str_word_count()` is useless here — it counts ASCII letters, so a Hebrew
 * article comes back as zero words. Splitting on whitespace is crude but it is
 * crude in the same way for every language, and the output is a rounded
 * estimate either way. 200 words a minute is the usual figure for adult
 * reading of continuous prose; a code block inflates it, which is the right
 * direction to be wrong in.
 *
 * Never returns 0 — "0 דקות קריאה" reads as an error, not as a short post.
 *
 * @param int $post_id Post to measure.
 * @return int Minutes, at least 1.
 */
function coweb_reading_time( int $post_id ): int {
	$content = (string) get_post_field( 'post_content', $post_id );
	$text    = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );

	if ( '' === $text ) {
		return 1;
	}

	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

	return max( 1, (int) ceil( count( (array) $words ) / 200 ) );
}

add_filter(
	'timber/twig/functions',
	static function ( array $functions ): array {
		$functions['work_filters'] = array( 'callable' => 'coweb_work_filters' );
		$functions['post_filters'] = array( 'callable' => 'coweb_post_filters' );

		// Both derived from the post rather than authored beside it — see the
		// docblocks. They sit here with the filter chips because all four are
		// "what the query already knows, shaped for a template".
		$functions['related_posts'] = array( 'callable' => 'coweb_related_posts' );
		$functions['reading_time']  = array( 'callable' => 'coweb_reading_time' );

		return $functions;
	}
);

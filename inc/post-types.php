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

add_filter(
	'timber/twig/functions',
	static function ( array $functions ): array {
		$functions['work_filters'] = array( 'callable' => 'coweb_work_filters' );

		return $functions;
	}
);

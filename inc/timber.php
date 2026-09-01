<?php
/**
 * Timber bootstrap and the global Twig context.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Timber arrives either as a Composer dependency or as the Timber plugin.
 * If neither is present every template fails, so say so in the admin instead
 * of white-screening the front end.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		if ( ! class_exists( 'Timber\Timber' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'The CoWeb theme requires Timber 2.x. Activate the Timber plugin, or run composer install in the theme directory.', 'coweb' )
					);
				}
			);

			return;
		}

		Timber\Timber::init();

		/*
		 * Timber 2.x still honours $dirname, but it defaults to `views`.
		 * Our templates live in `templates/`, so without this every render
		 * silently returns an empty string — a 200 with a blank body, no
		 * error, no warning. Cost an hour the first time.
		 */
		Timber\Timber::$dirname = array( 'templates' );
	},
	5
);

/**
 * The name of the current archive.
 *
 * Timber fills `title` for a post-type archive but not for a category or the
 * posts index, so `archive.twig` — which is only `index.twig` with a different
 * query — fell through to index.twig's own default and headed every category
 * with the word "בלוג". The category name was in the <title> tag the whole
 * time, which is how it went unnoticed.
 *
 * is_home() is the one case get_the_archive_title() does not answer at all.
 *
 * @return string
 */
function coweb_archive_title(): string {
	if ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );

		return $blog_id ? get_the_title( $blog_id ) : 'בלוג';
	}

	return wp_strip_all_tags( get_the_archive_title() );
}

/**
 * Values every template needs. Anything page-specific belongs in the template
 * that renders it, not here — this context is built on every request.
 *
 * @param array<string,mixed> $context Timber context.
 * @return array<string,mixed>
 */
add_filter(
	'timber/context',
	static function ( array $context ): array {
		$context['primary_nav'] = has_nav_menu( 'primary' )
			? Timber\Timber::get_menu( 'primary' )
			: null;

		// Site-wide ACF options: header CTA, contact details, footer links.
		$context['options'] = function_exists( 'get_fields' )
			? ( get_fields( 'option' ) ?: array() )
			: array();

		$context['home_url'] = home_url( '/' );

		// Set for every archive-shaped request, not only the ones Timber
		// already covers, so index.twig's heading never has to guess.
		if ( is_home() || is_archive() ) {
			$context['title'] = coweb_archive_title();
		}

		return $context;
	}
);

/**
 * Timber appends an English "Read More" link to every excerpt by default, in a
 * `.read-more` class the stylesheet has no rule for — so it renders in the
 * browser's default link colour, which is not a token, on a Hebrew-only site.
 *
 * It is switched off rather than translated. Every excerpt on this theme sits
 * inside a card or list item whose title is already a link to the same URL, so
 * a second link adds a duplicate tab stop and a second identical destination
 * for a screen reader — the AA claim is easier to keep without it.
 *
 * @param array<string,mixed> $defaults Timber excerpt defaults.
 * @return array<string,mixed>
 */
add_filter(
	'timber/post/excerpt/defaults',
	static function ( array $defaults ): array {
		$defaults['read_more']            = '';
		$defaults['always_add_read_more'] = false;

		return $defaults;
	}
);

/**
 * Expose the handful of WordPress functions the templates legitimately need.
 * Twig calls them through `function()`, so this is only about making the
 * allow-list explicit rather than reaching for arbitrary PHP from a template.
 */
add_filter(
	'timber/twig/functions',
	static function ( array $functions ): array {
		$functions['current_user_can'] = array( 'callable' => 'current_user_can' );

		// A function rather than a context value: section partials are included
		// `with … only`, so context is deliberately unreachable from inside
		// them. Threading breadcrumbs through every include to serve the one
		// partial that needs them would be worse.
		$functions['breadcrumbs'] = array( 'callable' => 'coweb_breadcrumbs' );

		return $functions;
	}
);

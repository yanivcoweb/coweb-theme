<?php
/**
 * Front-end assets: one stylesheet, one small script, no jQuery.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache-bust from the file's own mtime so a deploy never serves stale CSS and
 * we never hand-edit a version string.
 *
 * @param string $relative Path relative to the theme root.
 */
function coweb_asset_version( string $relative ): string {
	$path = COWEB_DIR . '/' . ltrim( $relative, '/' );

	return file_exists( $path ) ? (string) filemtime( $path ) : COWEB_VERSION;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'coweb-fonts',
			'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans+Hebrew:wght@400;500;600;700&display=swap',
			array(),
			null // Google versions the URL itself; adding ours busts their cache for nothing.
		);

		wp_enqueue_style(
			'coweb',
			COWEB_URI . '/assets/css/main.css',
			array( 'coweb-fonts' ),
			coweb_asset_version( 'assets/css/main.css' )
		);

		// The mobile menu is the only interactive element that needs script.
		wp_enqueue_script(
			'coweb-menu',
			COWEB_URI . '/assets/js/menu.js',
			array(),
			coweb_asset_version( 'assets/js/menu.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// The TOC only exists on article pages, so it only ships there.
		if ( is_singular( 'post' ) ) {
			wp_enqueue_script(
				'coweb-toc',
				COWEB_URI . '/assets/js/toc.js',
				array(),
				coweb_asset_version( 'assets/js/toc.js' ),
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
);

/**
 * Preconnect to the font CDN so the render-blocking stylesheet resolves sooner.
 *
 * @param array<int,mixed> $hints    Resource hints.
 * @param string           $relation Hint relation type.
 * @return array<int,mixed>
 */
add_filter(
	'wp_resource_hints',
	static function ( array $hints, string $relation ): array {
		if ( 'preconnect' === $relation ) {
			$hints[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}

		return $hints;
	},
	10,
	2
);

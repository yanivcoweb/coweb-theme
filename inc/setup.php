<?php
/**
 * Theme supports, menus, and the editor constraints the design depends on.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);

		register_nav_menus(
			array(
				'primary' => __( 'Primary navigation', 'coweb' ),
			)
		);

		load_theme_textdomain( 'coweb', COWEB_DIR . '/languages' );
	}
);

/**
 * Pages are built entirely from the `sections` Flexible Content field. Leaving
 * the block editor on would offer a second, unmanaged way to lay a page out,
 * and the two would drift within a week.
 *
 * Posts keep their editor — an article body is prose, not a section stack.
 *
 * @param bool   $enabled   Whether the block editor is on.
 * @param string $post_type Post type being edited.
 */
add_filter(
	'use_block_editor_for_post_type',
	static function ( bool $enabled, string $post_type ): bool {
		return 'page' === $post_type ? false : $enabled;
	},
	10,
	2
);

/**
 * The site claims green Core Web Vitals. Emoji detection ships ~10KB of
 * JavaScript to replace glyphs the system font already draws.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
	}
);

/**
 * Register the ACF options page that holds the header CTA and contact details.
 * Guarded because the theme should degrade, not fatal, if ACF Pro is missing.
 */
add_action(
	'acf/init',
	static function (): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page(
			array(
				'page_title' => __( 'Site settings', 'coweb' ),
				'menu_title' => __( 'Site settings', 'coweb' ),
				'menu_slug'  => 'coweb-settings',
				'capability' => 'edit_theme_options',
				'redirect'   => false,
				'icon_url'   => 'dashicons-admin-customizer',
				'position'   => 59,
			)
		);
	}
);

<?php
/**
 * CoWeb theme bootstrap.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COWEB_VERSION', '0.1.0' );
define( 'COWEB_DIR', get_template_directory() );
define( 'COWEB_URI', get_template_directory_uri() );

/**
 * Timber 2.x ships as a Composer dependency of the theme, so the autoloader
 * has to load before anything references a Timber class. Run `composer install`
 * in the theme directory after a fresh clone — vendor/ is not committed.
 */
if ( file_exists( COWEB_DIR . '/vendor/autoload.php' ) ) {
	require_once COWEB_DIR . '/vendor/autoload.php';
}

require_once COWEB_DIR . '/inc/breadcrumbs.php';
require_once COWEB_DIR . '/inc/timber.php';
require_once COWEB_DIR . '/inc/setup.php';
require_once COWEB_DIR . '/inc/post-types.php';
require_once COWEB_DIR . '/inc/assets.php';
require_once COWEB_DIR . '/inc/acf-sections.php';
require_once COWEB_DIR . '/inc/contact-form.php';

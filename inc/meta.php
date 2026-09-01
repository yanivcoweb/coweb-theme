<?php
/**
 * Document head: description, Open Graph, Twitter card, JSON-LD.
 *
 * WordPress emits the <title> (via the title-tag support in setup.php) and a
 * canonical link on singular views, and nothing else. Everything a link
 * preview needs was missing, so every share of the site — WhatsApp, LinkedIn,
 * Slack — rendered as a bare URL.
 *
 * No SEO plugin. A plugin here would bring its own admin UI, its own field
 * storage outside ACF, and front-end markup the theme does not control, all
 * for a handful of tags that are derived from content the theme already has.
 *
 * Everything below is *derived*. There is no per-page "SEO title" field to
 * fall out of sync with the heading an editor actually sees, in the same way
 * breadcrumbs are derived rather than authored.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collapse authored text into a single clean line of the right length.
 *
 * @param string $text  Raw text, possibly with markup and shortcodes.
 * @param int    $limit Maximum characters.
 * @return string
 */
function coweb_meta_trim( string $text, int $limit = 160 ): string {
	$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );

	if ( '' === $text ) {
		return '';
	}

	// mb_* because this is Hebrew: substr would cut a multi-byte character in
	// half and emit a replacement glyph into the description.
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $limit - 1 );
	$space = mb_strrpos( $cut, ' ' );

	return rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), " ,.;:־-" ) . '…';
}

/**
 * The intro line of the first section that has one.
 *
 * Pages and case studies are built from ACF Flexible Content, so `post_content`
 * is empty and `get_the_excerpt()` returns nothing usable. The hero's intro is
 * the page's own summary, written by the editor, already on screen — which
 * makes it a better description than anything a separate field would collect.
 *
 * @param int $post_id Post to read.
 * @return string
 */
function coweb_meta_section_intro( int $post_id ): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$sections = get_field( 'sections', $post_id );

	if ( ! is_array( $sections ) ) {
		return '';
	}

	foreach ( $sections as $section ) {
		foreach ( array( 'intro', 'sub', 'text' ) as $key ) {
			if ( ! empty( $section[ $key ] ) && is_string( $section[ $key ] ) ) {
				return $section[ $key ];
			}
		}
	}

	return '';
}

/**
 * Description for the current request.
 *
 * @return string
 */
function coweb_meta_description(): string {
	$text = '';

	if ( is_singular() ) {
		$post_id = get_queried_object_id();

		// An explicit excerpt beats a derived one; on a post that is the
		// article's own lead, which single.twig already renders.
		$excerpt = get_post_field( 'post_excerpt', $post_id );

		if ( is_string( $excerpt ) && '' !== trim( $excerpt ) ) {
			$text = $excerpt;
		} else {
			$text = coweb_meta_section_intro( $post_id );
		}

		if ( '' === $text ) {
			$text = (string) get_post_field( 'post_content', $post_id );
		}
	} elseif ( is_home() ) {
		// The posts page is a real page with real sections behind it, even
		// though the query in front of it is an archive.
		$blog_id = (int) get_option( 'page_for_posts' );

		if ( $blog_id ) {
			$text = coweb_meta_section_intro( $blog_id );
		}
	} elseif ( is_archive() ) {
		// Covers a term description and anything a plugin or the theme filters
		// in, which term_description() alone does not.
		$text = (string) get_the_archive_description();
	}

	if ( '' === trim( $text ) ) {
		// The tagline is the site's own one-line description and the only
		// sensible last resort. If it is empty the tag is dropped rather than
		// invented — a missing description is better than a wrong one.
		$text = (string) get_bloginfo( 'description' );
	}

	return coweb_meta_trim( $text );
}

/**
 * The image a link preview should use.
 *
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function coweb_share_image(): ?array {
	$attachment_id = 0;

	if ( is_singular() && has_post_thumbnail() ) {
		$attachment_id = (int) get_post_thumbnail_id();
	} elseif ( function_exists( 'get_field' ) ) {
		$fallback = get_field( 'share_image', 'option' );

		if ( is_array( $fallback ) && isset( $fallback['ID'] ) ) {
			$attachment_id = (int) $fallback['ID'];
		} elseif ( is_numeric( $fallback ) ) {
			$attachment_id = (int) $fallback;
		}
	}

	if ( ! $attachment_id ) {
		return null;
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'large' );

	if ( ! $src ) {
		return null;
	}

	return array(
		'url'    => (string) $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Canonical URL for the current request.
 *
 * Built from the queried object rather than REQUEST_URI, which carries
 * whatever query string the visitor arrived with — including the tracking
 * parameters that would otherwise end up advertised as the canonical address.
 *
 * @return string
 */
function coweb_canonical_url(): string {
	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_singular() ) {
		return (string) ( wp_get_canonical_url() ?: get_permalink() );
	}

	if ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );

		return $blog_id ? (string) get_permalink( $blog_id ) : home_url( '/' );
	}

	if ( is_post_type_archive() ) {
		$object = get_queried_object();

		if ( $object instanceof WP_Post_Type ) {
			return (string) get_post_type_archive_link( $object->name );
		}
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}
	}

	return home_url( '/' );
}

/**
 * Browser chrome colour on mobile.
 *
 * The one value in the theme duplicated outside _variables.scss, and it is
 * deliberate: this tag is read before any stylesheet, so it cannot come from a
 * custom property. It is `color/surface/base` — the page background — and if
 * that token ever changes, this changes with it, or a dark site gets a white
 * status bar above it.
 */
const COWEB_THEME_COLOR = '#0b0c0e';

add_action(
	'wp_head',
	static function (): void {
		printf( "	<meta name=\"theme-color\" content=\"%s\">
", esc_attr( COWEB_THEME_COLOR ) );
	},
	2
);

/*
 * Core's rel_canonical() covers singular views and nothing else. Ours covers
 * every view, so core's has to go or singular pages carry two canonical links
 * — and two is worse than none, because a crawler picks for you.
 */
remove_action( 'wp_head', 'rel_canonical' );

/**
 * Print the head tags.
 *
 * Priority 2, ahead of the stylesheet, so a crawler that reads only the first
 * few kilobytes still gets the description and the card.
 */
add_action(
	'wp_head',
	static function (): void {
		// Nothing here belongs on a page that asks not to be indexed, and a
		// preview card for a 404 or a search result is noise in a share sheet.
		if ( is_404() || is_search() ) {
			return;
		}

		$title       = wp_get_document_title();
		$description = coweb_meta_description();
		$url         = coweb_canonical_url();
		$image       = coweb_share_image();
		$is_article  = is_singular( array( 'post', 'work' ) );

		$tags = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:type', $is_article ? 'article' : 'website' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', $url ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'property', 'og:locale', get_locale() ),
		);

		if ( $image ) {
			$tags[] = array( 'property', 'og:image', $image['url'] );
			$tags[] = array( 'property', 'og:image:width', (string) $image['width'] );
			$tags[] = array( 'property', 'og:image:height', (string) $image['height'] );

			if ( '' !== $image['alt'] ) {
				$tags[] = array( 'property', 'og:image:alt', $image['alt'] );
			}
		}

		// A large card with no image renders as an empty grey box, so the card
		// type follows the image rather than being declared up front.
		$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

		if ( $is_article ) {
			$tags[] = array( 'property', 'article:published_time', (string) get_the_date( DATE_W3C ) );
			$tags[] = array( 'property', 'article:modified_time', (string) get_the_modified_date( DATE_W3C ) );
		}

		// Canonical on everything, not only singular views — core's
		// rel_canonical() covers singular and stops there, which leaves every
		// archive and the paginated blog without one.
		printf( "\t<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );

		foreach ( $tags as $tag ) {
			list( $attr, $key, $value ) = $tag;

			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			printf(
				"\t<meta %s=\"%s\" content=\"%s\">\n",
				esc_attr( $attr ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
	},
	2
);

/**
 * Structured data.
 *
 * Three graphs, each one earning its place: the business on the front page,
 * the trail wherever there is one to describe, and the article on a post.
 * JSON_UNESCAPED_UNICODE keeps Hebrew readable in the source instead of
 * turning every heading into a run of \u escapes.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( is_404() || is_search() ) {
			return;
		}

		$graph = array();

		if ( is_front_page() ) {
			$options = function_exists( 'get_field' ) ? get_field( 'contact_email', 'option' ) : '';
			$phone   = function_exists( 'get_field' ) ? get_field( 'contact_phone', 'option' ) : '';
			$social  = function_exists( 'get_field' ) ? get_field( 'linkedin_url', 'option' ) : '';

			$business = array(
				'@type'       => 'ProfessionalService',
				'@id'         => home_url( '/#business' ),
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url( '/' ),
				'description' => coweb_meta_description(),
				'areaServed'  => 'IL',
			);

			if ( is_string( $options ) && '' !== $options ) {
				$business['email'] = $options;
			}

			if ( is_string( $phone ) && '' !== $phone ) {
				$business['telephone'] = $phone;
			}

			if ( is_string( $social ) && '' !== $social ) {
				$business['sameAs'] = array( $social );
			}

			$graph[] = $business;
		}

		$trail = coweb_breadcrumbs();

		if ( count( $trail ) > 1 ) {
			$items = array();

			foreach ( $trail as $i => $crumb ) {
				$item = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['title'],
				);

				// The current page carries no URL by design; schema.org allows
				// the last item to omit `item` for exactly this reason.
				if ( ! empty( $crumb['url'] ) ) {
					$item['item'] = $crumb['url'];
				}

				$items[] = $item;
			}

			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}

		if ( is_singular( array( 'post', 'work' ) ) ) {
			$article = array(
				'@type'         => is_singular( 'post' ) ? 'BlogPosting' : 'CreativeWork',
				'headline'      => get_the_title(),
				'description'   => coweb_meta_description(),
				'datePublished' => get_the_date( DATE_W3C ),
				'dateModified'  => get_the_modified_date( DATE_W3C ),
				'mainEntityOfPage' => coweb_canonical_url(),
				'publisher'     => array(
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
					'url'   => home_url( '/' ),
				),
			);

			$image = coweb_share_image();

			if ( $image ) {
				$article['image'] = $image['url'];
			}

			$graph[] = $article;
		}

		if ( ! $graph ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( false === $json ) {
			return;
		}

		printf( "\t<script type=\"application/ld+json\">%s</script>\n", $json ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output, printed inside a JSON-LD script block.
	},
	3
);

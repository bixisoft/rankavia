<?php
/**
 * Template variables: the WordPress-aware resolvers for the current request and the sample table for the UI.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Templates;

use DateTimeZone;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Builds the variable map for Bixisoft\Rankavia\Vendor\Core\Text\Template. Every resolver is lazy.
 */
final class Variables {

	/**
	 * Length of the automatic excerpt, in characters.
	 */
	public const EXCERPT_LENGTH = 155;

	/**
	 * Resolvers for the current request, keyed by variable name (without percent signs).
	 *
	 * @param string $separator Value of %sep%.
	 * @return array<string, callable():string>
	 */
	public static function for_request( string $separator = '–' ): array {
		return array(
			'title'            => static function (): string {
				return self::title();
			},
			'sitename'         => static function (): string {
				return (string) get_bloginfo( 'name' );
			},
			'tagline'          => static function (): string {
				return (string) get_bloginfo( 'description' );
			},
			'sep'              => static function () use ( $separator ): string {
				return $separator;
			},
			'excerpt'          => static function (): string {
				$post = self::post();
				if ( null === $post ) {
					return '';
				}
				return self::plain_excerpt( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content, self::EXCERPT_LENGTH );
			},
			'category'         => static function (): string {
				$post = self::post();
				if ( null === $post || 'post' !== $post->post_type ) {
					return '';
				}
				$categories = get_the_category( $post->ID );
				return is_array( $categories ) && isset( $categories[0] ) ? (string) $categories[0]->name : '';
			},
			'term'             => static function (): string {
				$term = self::term();
				return null === $term ? '' : (string) $term->name;
			},
			'term_description' => static function (): string {
				if ( null === self::term() ) {
					return '';
				}
				return self::plain_excerpt( (string) term_description(), 0 );
			},
			'author'           => static function (): string {
				if ( is_author() ) {
					return (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() );
				}
				$post = self::post();
				return null === $post ? '' : (string) get_the_author_meta( 'display_name', (int) $post->post_author );
			},
			'date'             => static function (): string {
				return self::date();
			},
			'modified'         => static function (): string {
				$post = self::post();
				if ( null === $post ) {
					return '';
				}
				$modified = get_the_modified_date( '', $post );
				return is_string( $modified ) ? $modified : '';
			},
			'post_type'        => static function (): string {
				$type = self::post_type_object();
				return null === $type ? '' : (string) $type->labels->singular_name;
			},
			'post_type_plural' => static function (): string {
				$type = self::post_type_object();
				return null === $type ? '' : (string) $type->labels->name;
			},
			'page'             => static function (): string {
				$page = self::page_number();
				if ( $page < 2 ) {
					return '';
				}
				/* translators: 1: current page number, 2: total number of pages. */
				return sprintf( __( 'Page %1$d of %2$d', 'rankavia' ), $page, max( $page, self::total_pages() ) );
			},
			'pagenumber'       => static function (): string {
				$page = self::page_number();
				return $page > 1 ? (string) $page : '';
			},
			'search'           => static function (): string {
				return (string) get_search_query( false );
			},
			'currentyear'      => static function (): string {
				return (string) wp_date( 'Y' );
			},
			'currentdate'      => static function (): string {
				return (string) wp_date( (string) get_option( 'date_format' ) );
			},
			'id'               => static function (): string {
				$id = (int) get_queried_object_id();
				return $id > 0 ? (string) $id : '';
			},
		);
	}

	/**
	 * The variable table for the admin UI: name (without percent signs), label and a sample value.
	 *
	 * @return array<int, array{name: string, label: string, sample: string}>
	 */
	public static function samples(): array {
		return array(
			self::sample( 'title', __( 'Title', 'rankavia' ), 'How to speed up WordPress' ),
			self::sample( 'sitename', __( 'Site name', 'rankavia' ), 'Example Site' ),
			self::sample( 'tagline', __( 'Tagline', 'rankavia' ), 'Honest advice for site owners' ),
			self::sample( 'sep', __( 'Separator', 'rankavia' ), '–' ),
			self::sample( 'excerpt', __( 'Excerpt', 'rankavia' ), 'A practical walkthrough of caching, images and fonts that cuts load time in half.' ),
			self::sample( 'category', __( 'Category', 'rankavia' ), 'Performance' ),
			self::sample( 'term', __( 'Term name', 'rankavia' ), 'Performance' ),
			self::sample( 'term_description', __( 'Term description', 'rankavia' ), 'Guides on making sites faster.' ),
			self::sample( 'author', __( 'Author', 'rankavia' ), 'Jane Doe' ),
			self::sample( 'date', __( 'Date', 'rankavia' ), '12 March 2026' ),
			self::sample( 'modified', __( 'Modified date', 'rankavia' ), '2 April 2026' ),
			self::sample( 'post_type', __( 'Post type (singular)', 'rankavia' ), 'Post' ),
			self::sample( 'post_type_plural', __( 'Post type (plural)', 'rankavia' ), 'Posts' ),
			self::sample( 'page', __( 'Page X of Y', 'rankavia' ), 'Page 2 of 5' ),
			self::sample( 'pagenumber', __( 'Page number', 'rankavia' ), '2' ),
			self::sample( 'search', __( 'Search query', 'rankavia' ), 'page speed' ),
			self::sample( 'currentyear', __( 'Current year', 'rankavia' ), '2026' ),
			self::sample( 'currentdate', __( 'Current date', 'rankavia' ), '18 October 2026' ),
			self::sample( 'id', __( 'ID', 'rankavia' ), '42' ),
		);
	}

	/**
	 * Plain-text version of markup, optionally cut to a length on a character boundary.
	 *
	 * @param string $text   Raw text or markup.
	 * @param int    $length Maximum characters, 0 for no cut.
	 * @return string
	 */
	public static function plain_excerpt( string $text, int $length ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		if ( '' === $text || $length < 1 ) {
			return $text;
		}
		return wp_html_excerpt( $text, $length, '…' );
	}

	/**
	 * One sample entry.
	 *
	 * @param string $name   Variable name.
	 * @param string $label  Human label.
	 * @param string $sample Example value.
	 * @return array{name: string, label: string, sample: string}
	 */
	private static function sample( string $name, string $label, string $sample ): array {
		return array(
			'name'   => $name,
			'label'  => $label,
			'sample' => $sample,
		);
	}

	/**
	 * Title of what is being viewed.
	 *
	 * @return string
	 */
	private static function title(): string {
		if ( is_404() ) {
			return __( 'Page not found', 'rankavia' );
		}
		if ( is_search() ) {
			/* translators: %s: search phrase. */
			return sprintf( __( 'Search results for “%s”', 'rankavia' ), (string) get_search_query( false ) );
		}
		if ( is_front_page() ) {
			return (string) get_bloginfo( 'name' );
		}
		if ( is_home() ) {
			return (string) single_post_title( '', false );
		}
		$post = is_singular() ? self::post() : null;
		if ( null !== $post ) {
			return (string) get_the_title( $post );
		}
		if ( null !== self::term() ) {
			return (string) single_term_title( '', false );
		}
		if ( is_post_type_archive() ) {
			return (string) post_type_archive_title( '', false );
		}
		if ( is_author() || is_date() ) {
			return (string) get_the_archive_title();
		}
		return '';
	}

	/**
	 * Post date, or the period of a date archive.
	 *
	 * @return string
	 */
	private static function date(): string {
		if ( is_date() ) {
			$year  = (int) get_query_var( 'year' );
			$month = (int) get_query_var( 'monthnum' );
			$day   = (int) get_query_var( 'day' );
			if ( $year < 1 ) {
				return '';
			}
			$format = 'Y';
			if ( $day > 0 && $month > 0 ) {
				$format = (string) get_option( 'date_format' );
			} elseif ( $month > 0 ) {
				$format = 'F Y';
			}
			$stamp = gmmktime( 12, 0, 0, max( 1, $month ), max( 1, $day ), $year );
			return false === $stamp ? '' : (string) wp_date( $format, $stamp, new DateTimeZone( 'UTC' ) );
		}

		$post = self::post();
		if ( null === $post ) {
			return '';
		}
		$date = get_the_date( '', $post );
		return is_string( $date ) ? $date : '';
	}

	/**
	 * The post being viewed, including the posts page, or null.
	 *
	 * @return WP_Post|null
	 */
	private static function post(): ?WP_Post {
		$object = get_queried_object();
		if ( $object instanceof WP_Post ) {
			return $object;
		}
		if ( is_singular() ) {
			$post = get_post();
			return $post instanceof WP_Post ? $post : null;
		}
		return null;
	}

	/**
	 * The term being viewed on a taxonomy archive, or null.
	 *
	 * @return WP_Term|null
	 */
	private static function term(): ?WP_Term {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return null;
		}
		$object = get_queried_object();
		return $object instanceof WP_Term ? $object : null;
	}

	/**
	 * Post type object for the singular view or the post type archive, or null.
	 *
	 * @return object|null
	 */
	private static function post_type_object(): ?object {
		$name = '';
		if ( is_singular() ) {
			$name = (string) get_post_type();
		} elseif ( is_post_type_archive() ) {
			$var  = get_query_var( 'post_type' );
			$name = is_array( $var ) ? (string) reset( $var ) : (string) $var;
		}
		if ( '' === $name ) {
			return null;
		}
		$type = get_post_type_object( $name );
		return is_object( $type ) ? $type : null;
	}

	/**
	 * Current page number of an archive or of a split post.
	 *
	 * @return int
	 */
	private static function page_number(): int {
		return max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), 1 );
	}

	/**
	 * Total pages of the current archive or split post.
	 *
	 * @return int
	 */
	private static function total_pages(): int {
		if ( is_singular() ) {
			return (int) ( $GLOBALS['numpages'] ?? 1 );
		}
		$query = $GLOBALS['wp_query'] ?? null;
		return $query instanceof WP_Query ? (int) $query->max_num_pages : 1;
	}
}

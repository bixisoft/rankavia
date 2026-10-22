<?php
/**
 * Template contexts: which title and description template applies to which kind of page.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Templates;

/**
 * Builds the list of template contexts for the site and finds the one for the current request.
 */
final class Contexts {

	/**
	 * Context list for this site. Calls translation functions, so run it on or after init.
	 *
	 * @return array<int, array{key: string, label: string, title: string, description: string}>
	 */
	public static function for_site(): array {
		$by_site = '%title% %sep% %sitename%';

		$contexts = array(
			self::context( 'home', __( 'Home page', 'rankavia' ), '%sitename% %sep% %tagline%', '%tagline%' ),
			self::context( 'blog', __( 'Blog page', 'rankavia' ), $by_site, '%excerpt%' ),
		);

		$types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $types as $name => $type ) {
			if ( 'attachment' === (string) $name ) {
				continue;
			}
			$contexts[] = self::context( 'post_type:' . $name, self::label( $type, 'name', (string) $name ), $by_site, '%excerpt%' );
		}

		foreach ( $types as $name => $type ) {
			if ( 'attachment' === (string) $name || empty( $type->has_archive ) ) {
				continue;
			}
			$contexts[] = self::context(
				'archive:' . $name,
				/* translators: %s: post type name, for example "Products". */
				sprintf( __( '%s archive', 'rankavia' ), self::label( $type, 'name', (string) $name ) ),
				'%post_type_plural% %sep% %sitename%',
				'%tagline%'
			);
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $name => $taxonomy ) {
			if ( 'post_format' === (string) $name ) {
				continue;
			}
			$contexts[] = self::context(
				'taxonomy:' . $name,
				/* translators: %s: taxonomy name, for example "Category". */
				sprintf( __( '%s archives', 'rankavia' ), self::label( $taxonomy, 'singular_name', (string) $name ) ),
				'%term% %sep% %sitename%',
				'%term_description%'
			);
		}

		$contexts[] = self::context( 'author', __( 'Author archives', 'rankavia' ), '%author% %sep% %sitename%', '' );
		$contexts[] = self::context( 'date', __( 'Date archives', 'rankavia' ), '%date% %sep% %sitename%', '' );
		$contexts[] = self::context( 'search', __( 'Search results', 'rankavia' ), '%search% %sep% %sitename%', '' );
		$contexts[] = self::context( '404', __( '404 page', 'rankavia' ), 'Page not found %sep% %sitename%', '' );

		return $contexts;
	}

	/**
	 * Context key for the current request, by the conditional tags in precedence order.
	 *
	 * @return string
	 */
	public static function current(): string {
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_home() ) {
			return 'blog';
		}
		if ( is_singular() ) {
			$type = (string) get_post_type();
			return '' !== $type ? 'post_type:' . $type : 'home';
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( is_object( $term ) && isset( $term->taxonomy ) ) {
				return 'taxonomy:' . (string) $term->taxonomy;
			}
			return 'home';
		}
		if ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? (string) reset( $type ) : (string) $type;
			return '' !== $type ? 'archive:' . $type : 'home';
		}
		if ( is_author() ) {
			return 'author';
		}
		if ( is_date() ) {
			return 'date';
		}
		return 'home';
	}

	/**
	 * Default title template for a context key, or the generic one for an unknown key.
	 *
	 * @param string $key Context key.
	 * @return string
	 */
	public static function default_title( string $key ): string {
		foreach ( self::for_site() as $context ) {
			if ( $context['key'] === $key ) {
				return $context['title'];
			}
		}
		return '%title% %sep% %sitename%';
	}

	/**
	 * Default description template for a context key, or empty for an unknown key.
	 *
	 * @param string $key Context key.
	 * @return string
	 */
	public static function default_description( string $key ): string {
		foreach ( self::for_site() as $context ) {
			if ( $context['key'] === $key ) {
				return $context['description'];
			}
		}
		return '';
	}

	/**
	 * One context entry.
	 *
	 * @param string $key         Stable key.
	 * @param string $label       Human label.
	 * @param string $title       Default title template.
	 * @param string $description Default description template.
	 * @return array{key: string, label: string, title: string, description: string}
	 */
	private static function context( string $key, string $label, string $title, string $description ): array {
		return array(
			'key'         => $key,
			'label'       => $label,
			'title'       => $title,
			'description' => $description,
		);
	}

	/**
	 * Label of a post type or taxonomy object, falling back to its name.
	 *
	 * @param object $entity   WP_Post_Type or WP_Taxonomy.
	 * @param string $property Label property: name or singular_name.
	 * @param string $fallback Registered name.
	 * @return string
	 */
	private static function label( object $entity, string $property, string $fallback ): string {
		$labels = isset( $entity->labels ) && is_object( $entity->labels ) ? $entity->labels : null;
		$label  = null !== $labels && isset( $labels->$property ) ? (string) $labels->$property : '';

		return '' !== $label ? $label : $fallback;
	}
}

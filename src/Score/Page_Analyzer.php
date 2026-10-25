<?php
/**
 * PHP implementation of the shared page checks (mirrors packages/scorer).
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Score;

use Bixisoft\Rankavia\Vendor\Core\Score\Result;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Runs every catalogued check against one HTML document. Thresholds must match the TypeScript checks.
 */
final class Page_Analyzer {

	private const TITLE_MIN = 30;
	private const TITLE_MAX = 60;
	private const DESC_MIN  = 70;
	private const DESC_MAX  = 160;

	/**
	 * Analyse a page.
	 *
	 * @param string                $html    Full HTML.
	 * @param string                $url     The page URL (informational).
	 * @param array<string, string> $headers Response headers.
	 * @return Result[]
	 */
	public function analyze( string $html, string $url, array $headers = array() ): array {
		unset( $url );

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		if ( '' !== trim( $html ) ) {
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$xpath   = new DOMXPath( $dom );
		$headers = array_change_key_case( $headers, CASE_LOWER );

		$json_ld     = array();
		$json_errors = 0;
		foreach ( $this->nodes( $xpath, '//script' ) as $script ) {
			if ( 'application/ld+json' !== strtolower( trim( $script->getAttribute( 'type' ) ) ) ) {
				continue;
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
			$decoded = json_decode( $script->textContent, true );
			if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
				++$json_errors;
			} else {
				$json_ld[] = $decoded;
			}
		}

		$metas  = $this->nodes( $xpath, '//meta' );
		$images = $this->nodes( $xpath, '//img' );

		$results = array();

		// --- SEO ---------------------------------------------------------------.
		$title     = $this->text( $this->first( $xpath, '//title' ) );
		$results[] = '' !== $title
			? Result::pass( 'seo.title.present', sprintf( /* translators: %s: page title. */ __( 'Title: "%s"', 'rankavia' ), $this->truncate( $title ) ), array( 'title' => $title ) )
			: Result::fail( 'seo.title.present', __( 'The page has no <title>.', 'rankavia' ) );

		$results[] = $this->length_check( 'seo.title.length', mb_strlen( $title ), self::TITLE_MIN, self::TITLE_MAX, 10, __( 'Title', 'rankavia' ) );
		$results[] = $this->unique_check( 'seo.title.unique', count( $this->nodes( $xpath, '//title' ) ), 'title' );

		$description = (string) $this->meta_content( $metas, 'name', 'description' );
		$results[]   = '' !== $description
			? Result::pass( 'seo.description.present', sprintf( /* translators: %s: meta description. */ __( 'Meta description: "%s"', 'rankavia' ), $this->truncate( $description ) ), array( 'description' => $description ) )
			: Result::fail( 'seo.description.present', __( 'The page has no meta description.', 'rankavia' ) );

		$results[]         = $this->length_check( 'seo.description.length', mb_strlen( $description ), self::DESC_MIN, self::DESC_MAX, 20, __( 'Meta description', 'rankavia' ) );
		$description_count = 0;
		foreach ( $metas as $meta ) {
			if ( 'description' === strtolower( $this->source_trim( $meta->getAttribute( 'name' ) ) ) ) {
				++$description_count;
			}
		}
		$results[] = $this->unique_check( 'seo.description.unique', $description_count, 'meta description' );

		$canonical = $this->link_href( $xpath, 'canonical' );
		if ( '' === $canonical ) {
			$results[] = Result::fail( 'seo.canonical.present', __( 'No canonical link found.', 'rankavia' ) );
		} elseif ( 1 !== preg_match( '#^https?://#i', $canonical ) ) {
			$results[] = Result::warn( 'seo.canonical.present', __( 'Canonical href should be an absolute URL.', 'rankavia' ), array( 'href' => $canonical ) );
		} else {
			$results[] = Result::pass( 'seo.canonical.present', sprintf( /* translators: %s: canonical URL. */ __( 'Canonical: %s', 'rankavia' ), $canonical ), array( 'href' => $canonical ) );
		}
		$canonical_count = 0;
		foreach ( $this->nodes( $xpath, '//link' ) as $link ) {
			$tokens = preg_split( '/[\p{Z}\x{0009}-\x{000D}\x{FEFF}]+/u', strtolower( $this->source_trim( $link->getAttribute( 'rel' ) ) ) );
			if ( is_array( $tokens ) && in_array( 'canonical', $tokens, true ) ) {
				++$canonical_count;
			}
		}
		$results[] = $this->unique_check( 'seo.canonical.unique', $canonical_count, 'canonical' );
		$anchors   = $this->nodes( $xpath, '//a[@href]' );
		$missing   = 0;
		foreach ( $anchors as $anchor ) {
			if ( $this->link_has_name( $anchor, $xpath ) ) {
				continue;
			}
			++$missing;
		}
		$total   = count( $anchors );
		$details = array(
			'total'   => $total,
			'missing' => $missing,
		);
		if ( 0 === $total ) {
			$results[] = Result::info( 'seo.links.named', __( 'No links with href to check.', 'rankavia' ), $details );
		} elseif ( 0 === $missing ) {
			$results[] = Result::pass( 'seo.links.named', sprintf( /* translators: %d: checked link count. */ __( 'All %d links with href have a name.', 'rankavia' ), $total ), $details );
		} else {
			$results[] = Result::warn( 'seo.links.named', sprintf( /* translators: 1: unnamed links, 2: checked links. */ __( '%1$d of %2$d links with href have no name.', 'rankavia' ), $missing, $total ), $details );
		}

		$h1_count = count( $this->nodes( $xpath, '//h1' ) );
		if ( 1 === $h1_count ) {
			$results[] = Result::pass( 'seo.h1.single', __( 'Exactly one H1.', 'rankavia' ), array( 'count' => 1 ) );
		} elseif ( 0 === $h1_count ) {
			$results[] = Result::fail( 'seo.h1.single', __( 'The page has no H1.', 'rankavia' ), array( 'count' => 0 ) );
		} else {
			$results[] = Result::warn( 'seo.h1.single', sprintf( /* translators: %d: number of H1 headings. */ __( 'The page has %d H1 headings; use one.', 'rankavia' ), $h1_count ), array( 'count' => $h1_count ) );
		}

		$viewport = (string) $this->meta_content( $metas, 'name', 'viewport' );
		if ( '' === $viewport ) {
			$results[] = Result::fail( 'seo.viewport.present', __( 'No viewport meta tag; the page is not mobile-friendly.', 'rankavia' ) );
		} elseif ( 1 !== preg_match( '/width\s*=\s*device-width/i', $viewport ) ) {
			$results[] = Result::warn( 'seo.viewport.present', __( 'Viewport meta should include width=device-width.', 'rankavia' ), array( 'viewport' => $viewport ) );
		} else {
			$results[] = Result::pass( 'seo.viewport.present', __( 'Responsive viewport is set.', 'rankavia' ), array( 'viewport' => $viewport ) );
		}

		$html_node = $this->first( $xpath, '/html' );
		$lang      = null !== $html_node ? trim( $html_node->getAttribute( 'lang' ) ) : '';
		$results[] = '' !== $lang
			? Result::pass( 'seo.html.lang', sprintf( /* translators: %s: language code. */ __( 'Language declared: %s.', 'rankavia' ), $lang ), array( 'lang' => $lang ) )
			: Result::fail( 'seo.html.lang', __( 'The <html> element has no lang attribute.', 'rankavia' ) );

		$results[] = $this->images_alt_check( $images );

		$missing_og = array();
		foreach ( array( 'og:title', 'og:description', 'og:image' ) as $property ) {
			if ( '' === (string) $this->meta_content( $metas, 'property', $property ) ) {
				$missing_og[] = $property;
			}
		}
		if ( empty( $missing_og ) ) {
			$results[] = Result::pass( 'seo.og.basic', __( 'Open Graph title, description and image are set.', 'rankavia' ) );
		} elseif ( count( $missing_og ) < 3 ) {
			$results[] = Result::warn( 'seo.og.basic', sprintf( /* translators: %s: comma-separated list of tags. */ __( 'Missing Open Graph tags: %s.', 'rankavia' ), implode( ', ', $missing_og ) ), array( 'missing' => $missing_og ) );
		} else {
			$results[] = Result::fail( 'seo.og.basic', __( 'No Open Graph tags found.', 'rankavia' ), array( 'missing' => $missing_og ) );
		}

		$directive = null;
		foreach ( array( $this->meta_content( $metas, 'name', 'robots' ), $this->meta_content( $metas, 'name', 'googlebot' ), $headers['x-robots-tag'] ?? null ) as $source ) {
			if ( is_string( $source ) && 1 === preg_match( '/\bnoindex\b/i', $source ) ) {
				$directive = $source;
				break;
			}
		}
		$results[] = null !== $directive
			? Result::fail( 'seo.robots.indexable', __( 'The page is marked noindex and will not appear in search.', 'rankavia' ), array( 'directive' => $directive ) )
			: Result::pass( 'seo.robots.indexable', __( 'The page is indexable.', 'rankavia' ) );

		if ( $json_errors > 0 ) {
			$results[] = Result::fail( 'seo.jsonld.valid', sprintf( /* translators: %d: number of invalid blocks. */ __( '%d JSON-LD block(s) failed to parse.', 'rankavia' ), $json_errors ), array( 'errors' => $json_errors ) );
		} elseif ( empty( $json_ld ) ) {
			$results[] = Result::warn( 'seo.jsonld.valid', __( 'No JSON-LD structured data found.', 'rankavia' ), array( 'blocks' => 0 ) );
		} else {
			$results[] = Result::pass( 'seo.jsonld.valid', sprintf( /* translators: %d: number of blocks. */ __( '%d valid JSON-LD block(s).', 'rankavia' ), count( $json_ld ) ), array( 'blocks' => count( $json_ld ) ) );
		}

		// --- AEO ---------------------------------------------------------------.
		$headings  = $this->nodes( $xpath, '//h2 | //h3' );
		$questions = 0;
		foreach ( $headings as $heading ) {
			if ( '?' === substr( $this->text( $heading ), -1 ) ) {
				++$questions;
			}
		}
		$results[] = $questions > 0
			? Result::pass(
				'aeo.headings.questions',
				sprintf( /* translators: %d: number of question headings. */ __( '%d question-style heading(s) help answer engines match queries.', 'rankavia' ), $questions ),
				array(
					'questions' => $questions,
					'headings'  => count( $headings ),
				)
			)
			: Result::warn(
				'aeo.headings.questions',
				__( 'No question-style H2/H3 headings; phrase key sections as the questions people ask.', 'rankavia' ),
				array(
					'questions' => 0,
					'headings'  => count( $headings ),
				)
			);

		$has_author = $this->json_has(
			$json_ld,
			static function ( ?string $key, $value ): bool {
				if ( 'author' === $key ) {
					return true;
				}
				if ( '@type' !== $key ) {
					return false;
				}
				return 'Person' === $value || ( is_array( $value ) && in_array( 'Person', $value, true ) );
			}
		);
		$results[]  = $has_author
			? Result::pass( 'aeo.author.schema', __( 'Author or Person markup present (E-E-A-T signal).', 'rankavia' ) )
			: Result::warn( 'aeo.author.schema', __( 'No author/Person structured data; add an author with a bio and sameAs links.', 'rankavia' ) );

		// --- GEO ---------------------------------------------------------------.
		$semantic  = count( $this->nodes( $xpath, '//article | //main' ) );
		$results[] = $semantic > 0
			? Result::pass( 'geo.semantic.article', __( 'Semantic <article>/<main> wrappers make the main content easy to extract.', 'rankavia' ), array( 'count' => $semantic ) )
			: Result::warn( 'geo.semantic.article', __( 'No <article> or <main> element; wrap the primary content in semantic HTML.', 'rankavia' ), array( 'count' => 0 ) );

		$in_json_ld = $this->json_has(
			$json_ld,
			static function ( ?string $key ): bool {
				return 'dateModified' === $key;
			}
		);
		$in_meta    = '' !== (string) $this->meta_content( $metas, 'property', 'article:modified_time' );
		$in_time    = false;
		foreach ( $this->nodes( $xpath, '//time' ) as $time ) {
			if ( '' !== $time->getAttribute( 'datetime' ) ) {
				$in_time = true;
				break;
			}
		}
		$results[] = ( $in_json_ld || $in_meta || $in_time )
			? Result::pass(
				'geo.updated.date',
				__( 'A machine-readable modified date is present (freshness signal).', 'rankavia' ),
				array(
					'jsonLd' => $in_json_ld,
					'meta'   => $in_meta,
					'time'   => $in_time,
				)
			)
			: Result::warn( 'geo.updated.date', __( 'No dateModified, article:modified_time or <time datetime>; show when content was updated.', 'rankavia' ) );

		// --- Speed -------------------------------------------------------------.
		$results[] = $this->images_dimensions_check( $images );
		$results[] = $this->images_lazy_check( $images );

		$blocking = 0;
		foreach ( $this->nodes( $xpath, '//head//script' ) as $script ) {
			if ( '' === $script->getAttribute( 'src' ) || $script->hasAttribute( 'async' ) || $script->hasAttribute( 'defer' ) ) {
				continue;
			}
			if ( 'module' === strtolower( $script->getAttribute( 'type' ) ) ) {
				continue;
			}
			++$blocking;
		}
		if ( 0 === $blocking ) {
			$results[] = Result::pass( 'speed.scripts.blocking', __( 'No render-blocking scripts in <head>.', 'rankavia' ), array( 'blocking' => 0 ) );
		} elseif ( $blocking <= 2 ) {
			$results[] = Result::warn( 'speed.scripts.blocking', sprintf( /* translators: %d: number of scripts. */ __( '%d render-blocking script(s) in <head>; add defer or async.', 'rankavia' ), $blocking ), array( 'blocking' => $blocking ) );
		} else {
			$results[] = Result::fail( 'speed.scripts.blocking', sprintf( /* translators: %d: number of scripts. */ __( '%d render-blocking scripts in <head>; add defer or async.', 'rankavia' ), $blocking ), array( 'blocking' => $blocking ) );
		}

		return $results;
	}

	/**
	 * Check duplicate declarations.
	 *
	 * @param string $id Check id.
	 * @param int    $count Declaration count.
	 * @param string $label Declaration label.
	 * @return Result
	 */
	private function unique_check( string $id, int $count, string $label ): Result {
		$details = array( 'count' => $count );
		if ( 0 === $count ) {
			return Result::info( $id, sprintf( /* translators: %s: declaration label. */ __( 'No %s declaration to check for duplicates.', 'rankavia' ), $label ), $details );
		}
		return 1 === $count
			? Result::pass( $id, sprintf( /* translators: %s: declaration label. */ __( 'One %s declaration.', 'rankavia' ), $label ), $details )
			: Result::warn( $id, sprintf( /* translators: 1: declaration count, 2: declaration label. */ __( '%1$d %2$s declarations found.', 'rankavia' ), $count, $label ), $details );
	}

	/**
	 * Find detectable source text, aria-label or image-alt names.
	 *
	 * @param DOMElement $anchor Link element.
	 * @param DOMXPath   $xpath Query helper.
	 * @return bool
	 */
	private function link_has_name( DOMElement $anchor, DOMXPath $xpath ): bool {
		if ( '' !== $this->source_trim( $anchor->getAttribute( 'aria-label' ) ) ) {
			return true;
		}
		$image_nodes = $xpath->query( './/img', $anchor );
		if ( false !== $image_nodes ) {
			foreach ( $image_nodes as $image ) {
				if ( $image instanceof DOMElement && '' !== $this->source_trim( $image->getAttribute( 'alt' ) ) ) {
					return true;
				}
			}
		}
		$text_nodes = $xpath->query( './/text()[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::template)]', $anchor );
		if ( false !== $text_nodes ) {
			foreach ( $text_nodes as $text_node ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
				if ( '' !== $this->source_trim( $text_node->nodeValue ?? '' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Trim ECMAScript whitespace for parity with the shared TypeScript checks.
	 *
	 * @param string $value Source value.
	 * @return string
	 */
	private function source_trim( string $value ): string {
		return (string) preg_replace( '/^[\p{Z}\x{0009}-\x{000D}\x{FEFF}]+|[\p{Z}\x{0009}-\x{000D}\x{FEFF}]+$/u', '', $value );
	}

	/**
	 * Length band check shared by title and description.
	 *
	 * @param string $id        Check id.
	 * @param int    $length    Measured length.
	 * @param int    $min       Lower bound of the pass band.
	 * @param int    $max       Upper bound of the pass band.
	 * @param int    $tolerance Extra characters either side that only warn.
	 * @param string $label     Translated field name.
	 * @return Result
	 */
	private function length_check( string $id, int $length, int $min, int $max, int $tolerance, string $label ): Result {
		$details = array( 'length' => $length );
		if ( 0 === $length ) {
			/* translators: %s: field name (Title or Meta description). */
			return Result::fail( $id, sprintf( __( 'No %s to measure.', 'rankavia' ), strtolower( $label ) ), $details );
		}
		if ( $length >= $min && $length <= $max ) {
			/* translators: 1: field name, 2: character count. */
			return Result::pass( $id, sprintf( __( '%1$s is %2$d characters.', 'rankavia' ), $label, $length ), $details );
		}
		/* translators: 1: field name, 2: character count, 3: minimum, 4: maximum. */
		$message = sprintf( __( '%1$s is %2$d characters; aim for %3$d–%4$d.', 'rankavia' ), $label, $length, $min, $max );
		if ( $length >= $min - $tolerance && $length <= $max + $tolerance ) {
			return Result::warn( $id, $message, $details );
		}
		return Result::fail( $id, $message, $details );
	}

	/**
	 * Alt attribute coverage.
	 *
	 * @param DOMElement[] $images All img elements.
	 * @return Result
	 */
	private function images_alt_check( array $images ): Result {
		$total = count( $images );
		if ( 0 === $total ) {
			return Result::info( 'seo.images.alt', __( 'No images on the page.', 'rankavia' ), array( 'total' => 0 ) );
		}
		$missing = 0;
		foreach ( $images as $image ) {
			if ( ! $image->hasAttribute( 'alt' ) ) {
				++$missing;
			}
		}
		$details = array(
			'total'   => $total,
			'missing' => $missing,
		);
		if ( 0 === $missing ) {
			/* translators: %d: number of images. */
			return Result::pass( 'seo.images.alt', sprintf( __( 'All %d images have alt attributes.', 'rankavia' ), $total ), $details );
		}
		/* translators: 1: images missing alt, 2: total images. */
		$message = sprintf( __( '%1$d of %2$d images lack an alt attribute.', 'rankavia' ), $missing, $total );
		return $missing / $total <= 0.2 ? Result::warn( 'seo.images.alt', $message, $details ) : Result::fail( 'seo.images.alt', $message, $details );
	}

	/**
	 * Width/height coverage.
	 *
	 * @param DOMElement[] $images All img elements.
	 * @return Result
	 */
	private function images_dimensions_check( array $images ): Result {
		$total = count( $images );
		if ( 0 === $total ) {
			return Result::info( 'speed.images.dimensions', __( 'No images on the page.', 'rankavia' ), array( 'total' => 0 ) );
		}
		$missing = 0;
		foreach ( $images as $image ) {
			if ( '' === $image->getAttribute( 'width' ) || '' === $image->getAttribute( 'height' ) ) {
				++$missing;
			}
		}
		$details = array(
			'total'   => $total,
			'missing' => $missing,
		);
		if ( 0 === $missing ) {
			return Result::pass( 'speed.images.dimensions', __( 'All images declare width and height (prevents layout shift).', 'rankavia' ), $details );
		}
		if ( $missing / $total <= 0.25 ) {
			/* translators: 1: images missing dimensions, 2: total images. */
			return Result::warn( 'speed.images.dimensions', sprintf( __( '%1$d of %2$d images lack width/height attributes.', 'rankavia' ), $missing, $total ), $details );
		}
		/* translators: 1: images missing dimensions, 2: total images. */
		return Result::fail( 'speed.images.dimensions', sprintf( __( '%1$d of %2$d images lack width/height attributes (CLS risk).', 'rankavia' ), $missing, $total ), $details );
	}

	/**
	 * Lazy-loading of below-the-fold images (everything after the first two).
	 *
	 * @param DOMElement[] $images All img elements.
	 * @return Result
	 */
	private function images_lazy_check( array $images ): Result {
		$below_fold = array_slice( $images, 2 );
		if ( empty( $below_fold ) ) {
			return Result::info( 'speed.images.lazy', __( 'Too few images for lazy-loading to matter.', 'rankavia' ), array( 'total' => count( $images ) ) );
		}
		$eager = 0;
		foreach ( $below_fold as $image ) {
			if ( 'lazy' !== strtolower( $image->getAttribute( 'loading' ) ) ) {
				++$eager;
			}
		}
		$details = array(
			'total' => count( $images ),
			'eager' => $eager,
		);
		if ( $eager < 3 ) {
			return Result::pass( 'speed.images.lazy', __( 'Below-the-fold images are lazy-loaded.', 'rankavia' ), $details );
		}
		/* translators: %d: number of eagerly loaded images. */
		return Result::warn( 'speed.images.lazy', sprintf( __( '%d below-the-fold images load eagerly; add loading="lazy".', 'rankavia' ), $eager ), $details );
	}

	/**
	 * Elements matching an XPath expression.
	 *
	 * @param DOMXPath $xpath      Query helper.
	 * @param string   $expression XPath expression.
	 * @return DOMElement[]
	 */
	private function nodes( DOMXPath $xpath, string $expression ): array {
		$list = $xpath->query( $expression );
		if ( false === $list ) {
			return array();
		}
		$elements = array();
		foreach ( $list as $node ) {
			if ( $node instanceof DOMElement ) {
				$elements[] = $node;
			}
		}
		return $elements;
	}

	/**
	 * First element matching an XPath expression.
	 *
	 * @param DOMXPath $xpath      Query helper.
	 * @param string   $expression XPath expression.
	 * @return DOMElement|null
	 */
	private function first( DOMXPath $xpath, string $expression ): ?DOMElement {
		$nodes = $this->nodes( $xpath, $expression );
		return $nodes[0] ?? null;
	}

	/**
	 * Whitespace-normalised text content.
	 *
	 * @param DOMElement|null $element Element or null.
	 * @return string
	 */
	private function text( ?DOMElement $element ): string {
		if ( null === $element ) {
			return '';
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		return trim( (string) preg_replace( '/\s+/u', ' ', $element->textContent ) );
	}

	/**
	 * Content of the first meta whose attribute matches (case-insensitive).
	 *
	 * @param DOMElement[] $metas     All meta elements.
	 * @param string       $attribute "name" or "property".
	 * @param string       $value     Wanted attribute value.
	 * @return string|null Null when no such meta exists.
	 */
	private function meta_content( array $metas, string $attribute, string $value ): ?string {
		$wanted = strtolower( $value );
		foreach ( $metas as $meta ) {
			if ( strtolower( trim( $meta->getAttribute( $attribute ) ) ) === $wanted ) {
				return trim( $meta->getAttribute( 'content' ) );
			}
		}
		return null;
	}

	/**
	 * Href of the first link whose rel contains the token.
	 *
	 * @param DOMXPath $xpath Query helper.
	 * @param string   $rel   Rel token, e.g. "canonical".
	 * @return string
	 */
	private function link_href( DOMXPath $xpath, string $rel ): string {
		foreach ( $this->nodes( $xpath, '//link' ) as $link ) {
			$tokens = preg_split( '/\s+/', strtolower( trim( $link->getAttribute( 'rel' ) ) ) );
			if ( is_array( $tokens ) && in_array( $rel, $tokens, true ) ) {
				return trim( $link->getAttribute( 'href' ) );
			}
		}
		return '';
	}

	/**
	 * Depth-first search over decoded JSON-LD.
	 *
	 * @param mixed    $value     Decoded JSON value.
	 * @param callable $predicate Receives (key|null, value); return true to match.
	 * @param int      $depth     Recursion guard.
	 * @return bool
	 */
	private function json_has( $value, callable $predicate, int $depth = 0 ): bool {
		if ( $depth > 12 || ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && $predicate( $key, $child ) ) {
				return true;
			}
			if ( $this->json_has( $child, $predicate, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Shorten a string for messages.
	 *
	 * @param string $value Input.
	 * @param int    $max   Maximum length.
	 * @return string
	 */
	private function truncate( string $value, int $max = 80 ): string {
		return mb_strlen( $value ) > $max ? mb_substr( $value, 0, $max - 1 ) . '…' : $value;
	}
}

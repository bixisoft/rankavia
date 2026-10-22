<?php
/**
 * Collects head tags for output in wp_head.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Html;

/**
 * Builds escaped <meta> and <link> tags, de-duplicated by key, for a single render.
 */
final class Head {

	/**
	 * Escaped tag HTML keyed by a stable identifier.
	 *
	 * @var array<string, string>
	 */
	private array $tags = array();

	/**
	 * Add a <meta> tag. Empty content is ignored.
	 *
	 * @param string $name      The name or property value, e.g. "description" or "og:title".
	 * @param string $content   The content attribute.
	 * @param string $attribute The attribute carrying $name: "name" or "property".
	 * @return self
	 */
	public function meta( string $name, string $content, string $attribute = 'name' ): self {
		$content = trim( $content );
		if ( '' === $content ) {
			return $this;
		}

		$this->tags[ 'meta:' . $attribute . ':' . $name ] = sprintf(
			'<meta %s="%s" content="%s" />',
			esc_attr( $attribute ),
			esc_attr( $name ),
			esc_attr( $content )
		);

		return $this;
	}

	/**
	 * Add a <link> tag. Empty href is ignored.
	 *
	 * @param string                $rel        The rel attribute.
	 * @param string                $href       The href attribute.
	 * @param array<string, string> $attributes Extra attributes, e.g. array( 'hreflang' => 'en' ).
	 * @return self
	 */
	public function link( string $rel, string $href, array $attributes = array() ): self {
		$href = trim( $href );
		if ( '' === $href ) {
			return $this;
		}

		$extra = '';
		foreach ( $attributes as $attribute => $value ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( (string) $attribute ), esc_attr( (string) $value ) );
		}

		$key = 'link:' . $rel;
		if ( isset( $attributes['hreflang'] ) ) {
			$key .= ':' . $attributes['hreflang'];
		}

		$this->tags[ $key ] = sprintf( '<link rel="%s" href="%s"%s />', esc_attr( $rel ), esc_url( $href ), $extra );

		return $this;
	}

	/**
	 * Add pre-escaped HTML (for example a JSON-LD script tag). The caller is responsible for escaping.
	 *
	 * @param string $key  Stable key for de-duplication.
	 * @param string $html Escaped HTML.
	 * @return self
	 */
	public function raw( string $key, string $html ): self {
		$this->tags[ 'raw:' . $key ] = $html;
		return $this;
	}

	/**
	 * Whether a tag key exists.
	 *
	 * @param string $key Tag key, e.g. "meta:name:description".
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->tags[ $key ] );
	}

	/**
	 * Remove a tag by key.
	 *
	 * @param string $key Tag key.
	 * @return self
	 */
	public function remove( string $key ): self {
		unset( $this->tags[ $key ] );
		return $this;
	}

	/**
	 * All tags keyed by identifier.
	 *
	 * @return array<string, string>
	 */
	public function tags(): array {
		return $this->tags;
	}

	/**
	 * Render all tags, one per line. Everything was escaped when added.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( empty( $this->tags ) ) {
			return '';
		}
		return implode( "\n", $this->tags ) . "\n";
	}
}

<?php
/**
 * Title and description template engine.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Text;

/**
 * Renders strings such as "%title% %sep% %sitename%" from injected variables. Plain PHP, no WordPress calls.
 */
final class Template {

	/**
	 * Pattern for a variable token.
	 */
	private const TOKEN = '/%([A-Za-z0-9_]+)%/';

	/**
	 * Variable values or resolvers keyed by name (without the percent signs).
	 *
	 * @var array<string, callable|string>
	 */
	private array $variables;

	/**
	 * Separator passed to the constructor, matched literally.
	 *
	 * @var string
	 */
	private string $separator;

	/**
	 * Constructor.
	 *
	 * @param array<string, callable|string> $variables Name (without %) => value or resolver returning a string.
	 * @param string                         $separator The title separator, also the value of %sep%.
	 */
	public function __construct( array $variables, string $separator = '–' ) {
		$this->variables = $variables;
		$this->separator = trim( $separator );
	}

	/**
	 * Render a template.
	 *
	 * @param string $template Template text.
	 * @return string
	 */
	public function render( string $template ): string {
		$resolved = array();

		// 1. Replace variables; each resolver runs at most once per render, unknown names become empty.
		$text = (string) preg_replace_callback(
			self::TOKEN,
			function ( array $found ) use ( &$resolved ): string {
				$name = (string) $found[1];
				if ( ! array_key_exists( $name, $resolved ) ) {
					$resolved[ $name ] = $this->resolve( $name );
				}
				return $resolved[ $name ];
			},
			$template
		);

		// 2. Collapse whitespace runs and trim.
		$text = $this->collapse( $text );

		// 3. Drop empty separator segments.
		if ( '' !== $this->separator ) {
			$segments = preg_split( '/(?<!\S)' . preg_quote( $this->separator, '/' ) . '(?!\S)/u', $text );
			if ( false !== $segments ) {
				$kept = array();
				foreach ( $segments as $segment ) {
					$segment = trim( $segment );
					if ( '' !== $segment ) {
						$kept[] = $segment;
					}
				}
				$text = implode( ' ' . $this->separator . ' ', $kept );
			}
		}

		// 4. Plain text out: tags stripped, entities decoded.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Plain PHP engine, WordPress is not available here.
		return trim( html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Variable names used in a template, in order of first use.
	 *
	 * @param string $template Template text.
	 * @return array<int, string>
	 */
	public static function variables_in( string $template ): array {
		preg_match_all( self::TOKEN, $template, $matches );

		return array_values( array_unique( array_map( 'strval', $matches[1] ) ) );
	}

	/**
	 * Value of one variable, empty when unknown. %sep% is the separator unless a variable overrides it.
	 *
	 * @param string $name Variable name.
	 * @return string
	 */
	private function resolve( string $name ): string {
		if ( ! isset( $this->variables[ $name ] ) ) {
			return 'sep' === $name ? $this->separator : '';
		}

		$value = $this->variables[ $name ];
		if ( ! is_string( $value ) ) {
			$value = $value();
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Collapse whitespace runs to one space and trim.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function collapse( string $text ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}

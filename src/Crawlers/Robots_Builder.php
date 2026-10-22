<?php
/**
 * Builds the robots.txt text that Rankavia adds to WordPress' own output.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Crawlers;

/**
 * Pure: arrays and strings in, one string out. No WordPress functions.
 */
final class Robots_Builder {

	public const SIGNAL_KEYS = array( 'search', 'ai-input', 'ai-train' );

	/**
	 * Add content signals, AI crawler groups and custom rules to the robots.txt WordPress built.
	 *
	 * @param string                $core_output WordPress' robots.txt, including its sitemap line.
	 * @param bool                  $public      Whether the site may be indexed (the blog_public option).
	 * @param array<string, string> $policy      Bot id to '', 'allow' or 'block'.
	 * @param array<string, string> $signals     Content signal name (search, ai-input, ai-train) to '', 'yes' or 'no'.
	 * @param string                $custom      Custom rules, appended verbatim.
	 * @return string
	 */
	public static function build( string $core_output, bool $public, array $policy, array $signals, string $custom ): string {
		$custom = trim( self::line_endings( $custom ) );

		// Rule 1: a site hidden from search engines gets WordPress' own "Disallow: /" and only the custom rules.
		if ( ! $public ) {
			if ( '' === $custom ) {
				return $core_output;
			}
			return self::normalise( self::append( $core_output, self::custom_block( $custom ) ) );
		}

		// Rule 2: content signals directly after the first "User-agent: *" line.
		$output = self::with_signals( self::line_endings( $core_output ), self::signal_lines( $signals ) );

		// Rule 3: one group per bot that has a rule.
		$groups = self::bot_groups( $policy );
		if ( '' !== $groups ) {
			$output = self::append( $output, "# AI crawlers (managed by Rankavia)\n" . $groups );
		}

		// Rule 4: custom rules.
		if ( '' !== $custom ) {
			$output = self::append( $output, self::custom_block( $custom ) );
		}

		// Rule 5: line endings, blank lines and the final newline.
		return self::normalise( $output );
	}

	/**
	 * The comment and Content-Signal line for the signals that are set, or an empty array.
	 *
	 * @param array<string, string> $signals Content signal name to '', 'yes' or 'no'.
	 * @return array<int, string>
	 */
	private static function signal_lines( array $signals ): array {
		$pairs = array();
		foreach ( self::SIGNAL_KEYS as $name ) {
			if ( isset( $signals[ $name ] ) && in_array( $signals[ $name ], array( 'yes', 'no' ), true ) ) {
				$pairs[] = $name . '=' . $signals[ $name ];
			}
		}
		if ( array() === $pairs ) {
			return array();
		}

		return array(
			'# Content signals: a voluntary convention (contentsignals.org).',
			'Content-Signal: ' . implode( ', ', $pairs ),
		);
	}

	/**
	 * Insert lines after the first "User-agent: *" line, or put them in a new wildcard group at the top.
	 *
	 * @param string             $output Core output with "\n" line endings.
	 * @param array<int, string> $lines  Lines to insert.
	 * @return string
	 */
	private static function with_signals( string $output, array $lines ): string {
		if ( array() === $lines ) {
			return $output;
		}

		$block = implode( "\n", $lines );
		if ( 1 === preg_match( '/^user-agent:[ \t]*\*[ \t]*$/im', $output, $match, PREG_OFFSET_CAPTURE ) ) {
			$end = $match[0][1] + strlen( $match[0][0] );
			return substr( $output, 0, $end ) . "\n" . $block . substr( $output, $end );
		}

		return "User-agent: *\n" . $block . ( '' === trim( $output ) ? '' : "\n\n" . ltrim( $output ) );
	}

	/**
	 * One "User-agent" group per bot with a rule, separated by a blank line, in catalogue order.
	 *
	 * @param array<string, string> $policy Bot id to '', 'allow' or 'block'.
	 * @return string
	 */
	private static function bot_groups( array $policy ): string {
		$groups = array();
		foreach ( Bots::tokens() as $id => $token ) {
			$value = isset( $policy[ $id ] ) ? $policy[ $id ] : Bots::POLICY_NONE;
			if ( Bots::POLICY_ALLOW === $value ) {
				$groups[] = 'User-agent: ' . $token . "\nAllow: /";
			} elseif ( Bots::POLICY_BLOCK === $value ) {
				$groups[] = 'User-agent: ' . $token . "\nDisallow: /";
			}
		}
		return implode( "\n\n", $groups );
	}

	/**
	 * Add a block after the existing text, separated by one blank line.
	 *
	 * @param string $output Text so far.
	 * @param string $block  Block to add.
	 * @return string
	 */
	private static function append( string $output, string $block ): string {
		$output = rtrim( $output );
		return '' === $output ? $block : $output . "\n\n" . $block;
	}

	/**
	 * The "# Custom rules" heading followed by the rules.
	 *
	 * @param string $custom Trimmed custom rules, not empty.
	 * @return string
	 */
	private static function custom_block( string $custom ): string {
		return "# Custom rules\n" . $custom;
	}

	/**
	 * Normalise line endings to "\n".
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function line_endings( string $text ): string {
		return str_replace( array( "\r\n", "\r" ), "\n", $text );
	}

	/**
	 * "\n" line endings, at most one blank line in a row, one newline at the end.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalise( string $text ): string {
		$text = (string) preg_replace( "/\n{3,}/", "\n\n", self::line_endings( $text ) );
		$text = rtrim( $text );
		return '' === $text ? '' : $text . "\n";
	}
}

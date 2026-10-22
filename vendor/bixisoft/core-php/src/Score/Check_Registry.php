<?php
/**
 * Catalogue of checks (metadata only; implementations live in the plugin and the TypeScript scorer).
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Score;

/**
 * Loads check metadata (id, category, title, severity, weight, description) from the shared checks.json.
 */
final class Check_Registry {

	/**
	 * Default weight per severity, used when a catalogue entry has no explicit weight.
	 *
	 * @var array<string, int>
	 */
	public const SEVERITY_WEIGHTS = array(
		'critical' => 4,
		'high'     => 3,
		'medium'   => 2,
		'low'      => 1,
	);

	/**
	 * Checks keyed by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $checks = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $checks Catalogue entries. Each needs at least "id" and "category".
	 */
	public function __construct( array $checks ) {
		foreach ( $checks as $check ) {
			if ( ! is_array( $check ) || ! isset( $check['id'], $check['category'] ) ) {
				continue;
			}
			if ( ! isset( $check['weight'] ) ) {
				$severity        = isset( $check['severity'] ) ? (string) $check['severity'] : 'medium';
				$check['weight'] = self::SEVERITY_WEIGHTS[ $severity ] ?? self::SEVERITY_WEIGHTS['medium'];
			}
			$this->checks[ (string) $check['id'] ] = $check;
		}
	}

	/**
	 * Build a registry from a checks.json file. A missing or invalid file yields an empty registry.
	 *
	 * @param string $path Absolute path to checks.json.
	 * @return self
	 */
	public static function from_file( string $path ): self {
		if ( ! is_readable( $path ) ) {
			return new self( array() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled catalogue, not a remote URL.
		$json = file_get_contents( $path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;

		return new self( is_array( $data ) ? $data : array() );
	}

	/**
	 * One catalogue entry.
	 *
	 * @param string $id Check id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->checks[ $id ] ?? null;
	}

	/**
	 * Whether the id is in the catalogue.
	 *
	 * @param string $id Check id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->checks[ $id ] );
	}

	/**
	 * All entries keyed by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return $this->checks;
	}

	/**
	 * Entries in one category keyed by id.
	 *
	 * @param string $category Category id: seo, aeo, geo or speed.
	 * @return array<string, array<string, mixed>>
	 */
	public function by_category( string $category ): array {
		return array_filter(
			$this->checks,
			static function ( array $check ) use ( $category ): bool {
				return $check['category'] === $category;
			}
		);
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->checks );
	}
}

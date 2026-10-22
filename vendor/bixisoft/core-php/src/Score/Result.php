<?php
/**
 * Result of a single check.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Score;

use InvalidArgumentException;

/**
 * Immutable outcome of one check: pass, warn, fail or info (not scored).
 */
final class Result {

	public const PASS = 'pass';
	public const WARN = 'warn';
	public const FAIL = 'fail';
	public const INFO = 'info';

	/**
	 * Check id, e.g. "seo.title.present".
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * One of the status constants.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Human-readable explanation.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Machine-readable extras (counts, values found).
	 *
	 * @var array<string, mixed>
	 */
	private array $details;

	/**
	 * Constructor.
	 *
	 * @param string               $id      Check id.
	 * @param string               $status  One of the status constants.
	 * @param string               $message Human-readable explanation.
	 * @param array<string, mixed> $details Machine-readable extras.
	 * @throws InvalidArgumentException When the status is unknown.
	 */
	public function __construct( string $id, string $status, string $message = '', array $details = array() ) {
		if ( ! in_array( $status, array( self::PASS, self::WARN, self::FAIL, self::INFO ), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing exception, never rendered to site visitors.
			throw new InvalidArgumentException( sprintf( 'Unknown result status "%s".', $status ) );
		}
		$this->id      = $id;
		$this->status  = $status;
		$this->message = $message;
		$this->details = $details;
	}

	/**
	 * Passing result.
	 *
	 * @param string               $id      Check id.
	 * @param string               $message Explanation.
	 * @param array<string, mixed> $details Extras.
	 * @return self
	 */
	public static function pass( string $id, string $message = '', array $details = array() ): self {
		return new self( $id, self::PASS, $message, $details );
	}

	/**
	 * Warning result (half credit).
	 *
	 * @param string               $id      Check id.
	 * @param string               $message Explanation.
	 * @param array<string, mixed> $details Extras.
	 * @return self
	 */
	public static function warn( string $id, string $message = '', array $details = array() ): self {
		return new self( $id, self::WARN, $message, $details );
	}

	/**
	 * Failing result.
	 *
	 * @param string               $id      Check id.
	 * @param string               $message Explanation.
	 * @param array<string, mixed> $details Extras.
	 * @return self
	 */
	public static function fail( string $id, string $message = '', array $details = array() ): self {
		return new self( $id, self::FAIL, $message, $details );
	}

	/**
	 * Informational result (not scored).
	 *
	 * @param string               $id      Check id.
	 * @param string               $message Explanation.
	 * @param array<string, mixed> $details Extras.
	 * @return self
	 */
	public static function info( string $id, string $message = '', array $details = array() ): self {
		return new self( $id, self::INFO, $message, $details );
	}

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Status constant.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Explanation.
	 *
	 * @return string
	 */
	public function message(): string {
		return $this->message;
	}

	/**
	 * Extras.
	 *
	 * @return array<string, mixed>
	 */
	public function details(): array {
		return $this->details;
	}

	/**
	 * Whether the status equals the given one.
	 *
	 * @param string $status Status constant.
	 * @return bool
	 */
	public function is( string $status ): bool {
		return $this->status === $status;
	}

	/**
	 * Array form for JSON responses.
	 *
	 * @return array{id: string, status: string, message: string, details: array<string, mixed>}
	 */
	public function to_array(): array {
		return array(
			'id'      => $this->id,
			'status'  => $this->status,
			'message' => $this->message,
			'details' => $this->details,
		);
	}
}

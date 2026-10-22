<?php
/**
 * Typed settings access: Options for storage, Schema for shape.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Settings;

use Bixisoft\Rankavia\Vendor\Core\Options;

/**
 * Reads sanitised values, validates and saves updates, resets, exports and imports.
 */
final class Settings {

	public const EXPORT_VERSION = '1';

	/**
	 * Storage.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Field definitions.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 *
	 * @param Options $options Storage.
	 * @param Schema  $schema  Field definitions.
	 */
	public function __construct( Options $options, Schema $schema ) {
		$this->options = $options;
		$this->schema  = $schema;
	}

	/**
	 * The schema.
	 *
	 * @return Schema
	 */
	public function schema(): Schema {
		return $this->schema;
	}

	/**
	 * One sanitised stored value, else the default. Null for keys outside the schema.
	 *
	 * @param string $key Field key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Defaults merged with sanitised stored values, schema keys only.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = $this->schema->sanitize( $this->options->all() );
		return array_merge( $this->schema->defaults(), $stored );
	}

	/**
	 * Values of one group.
	 *
	 * @param string $group Group id.
	 * @return array<string, mixed>
	 */
	public function group( string $group ): array {
		$values = array();
		foreach ( $this->all() as $key => $value ) {
			$field = $this->schema->fields()[ $key ];
			if ( $group === $field['group'] ) {
				$values[ $key ] = $value;
			}
		}
		return $values;
	}

	/**
	 * Sanitise, merge over the current values, validate, and save only when valid.
	 *
	 * Unknown keys are ignored. Keys stored outside the schema (legacy installs) are kept.
	 *
	 * @param array<string, mixed> $input Partial map of key to raw value.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public function update( array $input ): array {
		$merged = array_merge( $this->all(), $this->schema->sanitize( $input ) );
		$errors = $this->schema->validate( $merged );

		if ( array() !== $errors ) {
			return array(
				'values' => $this->all(),
				'errors' => $errors,
			);
		}

		$this->options->save( array_merge( $this->options->all(), $merged ) );

		return array(
			'values' => $this->all(),
			'errors' => array(),
		);
	}

	/**
	 * Restore defaults for one group, or for everything.
	 *
	 * @param string|null $group Group id, or null for all groups.
	 * @return array<string, mixed> All values after the reset.
	 */
	public function reset( ?string $group = null ): array {
		$defaults = $this->schema->defaults();
		$reset    = array();
		foreach ( $this->schema->fields() as $key => $field ) {
			if ( null === $group || $group === $field['group'] ) {
				$reset[ $key ] = $defaults[ $key ];
			}
		}

		if ( array() !== $reset ) {
			$this->options->save( array_merge( $this->options->all(), $reset ) );
		}

		return $this->all();
	}

	/**
	 * Portable snapshot of the current values.
	 *
	 * @return array{version: string, exported_at: string, values: array<string, mixed>}
	 */
	public function export(): array {
		return array(
			'version'     => self::EXPORT_VERSION,
			'exported_at' => gmdate( 'c' ),
			'values'      => $this->all(),
		);
	}

	/**
	 * Import an export() payload or a bare values map. Same return shape as update().
	 *
	 * @param array<string, mixed> $payload Export payload or values map.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public function import( array $payload ): array {
		$values = isset( $payload['values'] ) && is_array( $payload['values'] ) ? $payload['values'] : $payload;
		return $this->update( $values );
	}
}

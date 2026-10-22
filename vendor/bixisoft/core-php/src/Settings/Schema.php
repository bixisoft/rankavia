<?php
/**
 * Declarative settings schema: storage shape, sanitisation, validation and UI description.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Settings;

/**
 * Holds groups of typed fields. Pure PHP: no WordPress functions, never returns WP_Error.
 */
final class Schema {

	public const TYPES = array( 'string', 'text', 'boolean', 'integer', 'select', 'multiselect', 'image', 'list', 'map' );

	/**
	 * Groups keyed by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $groups = array();

	/**
	 * Flat field definitions keyed by field key, each carrying its 'group'.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $fields = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $groups Groups keyed by id: label, description, optional ui hints, fields.
	 */
	public function __construct( array $groups ) {
		foreach ( $groups as $id => $group ) {
			$id     = (string) $id;
			$fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();

			$this->groups[ $id ] = array(
				'label'       => isset( $group['label'] ) ? (string) $group['label'] : $id,
				'description' => isset( $group['description'] ) ? (string) $group['description'] : '',
				'ui'          => isset( $group['ui'] ) && is_array( $group['ui'] ) ? $group['ui'] : array(),
				'fields'      => array(),
			);

			foreach ( $fields as $key => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$key           = (string) $key;
				$field['type'] = isset( $field['type'] ) && in_array( $field['type'], self::TYPES, true ) ? (string) $field['type'] : 'string';
				if ( ! isset( $field['label'] ) ) {
					$field['label'] = $key;
				}
				if ( ! array_key_exists( 'default', $field ) ) {
					$field['default'] = $this->empty_value( $field['type'] );
				}

				$this->groups[ $id ]['fields'][ $key ] = $field;
				$this->fields[ $key ]                  = array_merge( $field, array( 'group' => $id ) );
			}
		}
	}

	/**
	 * Groups keyed by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function groups(): array {
		return $this->groups;
	}

	/**
	 * Flat field definitions keyed by field key; each carries a 'group' entry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Whether a field key exists.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->fields[ $key ] );
	}

	/**
	 * Defaults keyed by field key.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$defaults = array();
		foreach ( $this->fields as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}
		return $defaults;
	}

	/**
	 * Coerce input to typed values. Unknown keys are dropped; never fails.
	 *
	 * @param array<string, mixed> $input Raw values.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$output = array();
		foreach ( $this->fields as $key => $field ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = $this->sanitize_value( $field, $input[ $key ] );
			if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				$value = call_user_func( $field['sanitize_callback'], $value );
			}
			$output[ $key ] = $value;
		}
		return $output;
	}

	/**
	 * Validate values. Only keys present in $values are checked.
	 *
	 * @param array<string, mixed> $values Values, normally already sanitised.
	 * @return array<string, string> Field key to message for every invalid value; empty when valid.
	 */
	public function validate( array $values ): array {
		$errors = array();
		foreach ( $this->fields as $key => $field ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$message = $this->validate_value( $field, $values[ $key ] );

			if ( '' === $message && isset( $field['validate_callback'] ) && is_callable( $field['validate_callback'] ) ) {
				$result = call_user_func( $field['validate_callback'], $values[ $key ], $values );
				if ( is_string( $result ) && '' !== $result ) {
					$message = $result;
				}
			}

			if ( '' !== $message ) {
				$errors[ $key ] = $message;
			}
		}
		return $errors;
	}

	/**
	 * JSON-safe description for the admin UI (no callbacks). Choices and keys are ordered {value,label} lists; a map key
	 * declared with a note also carries it. A map with choices emits them like a select.
	 *
	 * @return array<string, mixed>
	 */
	public function describe(): array {
		$groups = array();
		foreach ( $this->groups as $id => $group ) {
			$fields = array();
			foreach ( $group['fields'] as $key => $field ) {
				$type  = (string) $field['type'];
				$entry = array(
					'key'         => (string) $key,
					'type'        => $type,
					'label'       => (string) $field['label'],
					'description' => isset( $field['description'] ) ? (string) $field['description'] : '',
					'default'     => $field['default'],
					'choices'     => $this->described_choices( $field, $type ),
					'placeholder' => isset( $field['placeholder'] ) ? (string) $field['placeholder'] : null,
					'min'         => isset( $field['min'] ) ? (int) $field['min'] : null,
					'max'         => isset( $field['max'] ) ? (int) $field['max'] : null,
					'maxLength'   => isset( $field['max_length'] ) ? (int) $field['max_length'] : null,
					'itemFormat'  => 'list' === $type ? $this->item_format( $field ) : null,
					'keys'        => 'map' === $type ? $this->key_entries( $field ) : null,
					'helpUrl'     => isset( $field['help_url'] ) ? (string) $field['help_url'] : null,
				);
				// Presentation hint for the UI; only present when declared, never used by sanitise or validate.
				if ( isset( $field['format'] ) ) {
					$entry['format'] = (string) $field['format'];
				}
				$fields[] = $entry;
			}

			$groups[] = array(
				'id'          => (string) $id,
				'label'       => (string) $group['label'],
				'description' => (string) $group['description'],
				'ui'          => (object) $group['ui'],
				'fields'      => $fields,
			);
		}
		return array( 'groups' => $groups );
	}

	/**
	 * JSON-schema fragments for register_rest_route() args, keyed by field key.
	 *
	 * Only types and enumerations are expressed here, and no defaults, so partial writes work. Lengths and
	 * bounds are enforced by validate()/sanitize() so the client gets the per-field error map.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function rest_args(): array {
		$args = array();
		foreach ( $this->fields as $key => $field ) {
			$type = (string) $field['type'];
			$enum = array_map( 'strval', array_keys( $this->map_of( $field, 'choices' ) ) );
			$arg  = array( 'description' => isset( $field['description'] ) ? (string) $field['description'] : '' );

			switch ( $type ) {
				case 'boolean':
					$arg['type'] = 'boolean';
					break;
				case 'integer':
				case 'image':
					$arg['type'] = 'integer';
					break;
				case 'select':
					$arg['type'] = 'string';
					$arg['enum'] = $enum;
					break;
				case 'multiselect':
					$arg['type']        = 'array';
					$arg['items']       = array(
						'type' => 'string',
						'enum' => $enum,
					);
					$arg['uniqueItems'] = true;
					break;
				case 'list':
					$arg['type']  = 'array';
					$arg['items'] = array( 'type' => 'string' );
					break;
				case 'map':
					$properties = array();
					foreach ( array_keys( $this->map_of( $field, 'keys' ) ) as $map_key ) {
						$properties[ (string) $map_key ] = array( 'type' => 'string' );
					}
					$arg['type']       = 'object';
					$arg['properties'] = $properties;
					break;
				default:
					$arg['type'] = 'string';
			}

			$args[ $key ] = $arg;
		}
		return $args;
	}

	/**
	 * Type sanitiser for one value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return mixed
	 */
	private function sanitize_value( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'text':
				return $this->clean_text( $value );
			case 'boolean':
				return $this->clean_boolean( $value, (bool) $field['default'] );
			case 'integer':
				return $this->clean_integer( $field, $value );
			case 'select':
				$choice = $this->clean_string( $value );
				return $this->is_choice( $field, $choice ) ? $choice : $field['default'];
			case 'multiselect':
				return $this->clean_multiselect( $field, $value );
			case 'image':
				return is_scalar( $value ) ? max( 0, (int) $value ) : 0;
			case 'list':
				return $this->clean_list( $value );
			case 'map':
				return $this->clean_map( $field, $value );
			default:
				return $this->clean_string( $value );
		}
	}

	/**
	 * Validation message for one value, or an empty string when valid.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Value.
	 * @return string
	 */
	private function validate_value( array $field, $value ): string {
		$label = (string) $field['label'];

		switch ( $field['type'] ) {
			case 'string':
			case 'text':
				if ( isset( $field['max_length'] ) && $this->length( is_scalar( $value ) ? (string) $value : '' ) > (int) $field['max_length'] ) {
					return sprintf( '%s must be at most %d characters.', $label, (int) $field['max_length'] );
				}
				return '';
			case 'integer':
				if ( isset( $field['min'] ) && (int) $value < (int) $field['min'] ) {
					return sprintf( '%s must be at least %d.', $label, (int) $field['min'] );
				}
				if ( isset( $field['max'] ) && (int) $value > (int) $field['max'] ) {
					return sprintf( '%s must be at most %d.', $label, (int) $field['max'] );
				}
				return '';
			case 'select':
				return $this->is_choice( $field, is_scalar( $value ) ? (string) $value : '' ) ? '' : sprintf( '%s has a value that is not allowed.', $label );
			case 'multiselect':
				foreach ( is_array( $value ) ? $value : array() as $item ) {
					if ( ! $this->is_choice( $field, is_scalar( $item ) ? (string) $item : '' ) ) {
						return sprintf( '%s has a value that is not allowed.', $label );
					}
				}
				return '';
			case 'list':
				if ( 'url' === $this->item_format( $field ) ) {
					foreach ( is_array( $value ) ? $value : array() as $item ) {
						if ( ! $this->is_url( is_scalar( $item ) ? (string) $item : '' ) ) {
							return sprintf( '%s must only contain full URLs such as https://example.com/page.', $label );
						}
					}
				}
				return '';
			case 'map':
				if ( $this->has_choices( $field ) ) {
					foreach ( is_array( $value ) ? $value : array() as $item ) {
						if ( ! $this->is_choice( $field, is_scalar( $item ) ? (string) $item : '' ) ) {
							return sprintf( '%s has a value that is not allowed.', $label );
						}
					}
				}
				return '';
			default:
				return '';
		}
	}

	/**
	 * Single-line plain text: tags stripped, control characters collapsed to a space, trimmed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_string( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Core must stay free of WordPress functions so it is unit-testable.
		$text = strip_tags( (string) $value );
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text ) );
	}

	/**
	 * Multi-line plain text: tags stripped, newlines normalised, other control characters removed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Core must stay free of WordPress functions so it is unit-testable.
		$text = strip_tags( (string) $value );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $text ) );
	}

	/**
	 * Boolean from the accepted spellings; anything else gives the default.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Returned for unrecognised input.
	 * @return bool
	 */
	private function clean_boolean( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_string( $value ) ) {
			$word = strtolower( trim( (string) $value ) );
			if ( in_array( $word, array( '1', 'true', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $word, array( '0', 'false', 'off' ), true ) ) {
				return false;
			}
		}
		return $fallback;
	}

	/**
	 * Integer clamped to min/max.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return int
	 */
	private function clean_integer( array $field, $value ): int {
		$number = is_scalar( $value ) ? (int) $value : (int) $field['default'];
		if ( isset( $field['min'] ) ) {
			$number = max( (int) $field['min'], $number );
		}
		if ( isset( $field['max'] ) ) {
			$number = min( (int) $field['max'], $number );
		}
		return $number;
	}

	/**
	 * Allowed choice keys, unique, in input order.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return array<int, string>
	 */
	private function clean_multiselect( array $field, $value ): array {
		$result = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			$item = $this->clean_string( $item );
			if ( $this->is_choice( $field, $item ) && ! in_array( $item, $result, true ) ) {
				$result[] = $item;
			}
		}
		return $result;
	}

	/**
	 * Trimmed, non-empty, de-duplicated strings. A newline-separated string is accepted too.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	private function clean_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\R/', $value );
		}

		$result = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			$item = $this->clean_string( $item );
			if ( '' !== $item && ! in_array( $item, $result, true ) ) {
				$result[] = $item;
			}
		}
		return $result;
	}

	/**
	 * Associative array limited to the allowed keys, values as strings. When the field declares choices, a value that
	 * is not a choice key is stored as an empty string.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return array<string, string>
	 */
	private function clean_map( array $field, $value ): array {
		$allowed = array_map( 'strval', array_keys( $this->map_of( $field, 'keys' ) ) );
		$result  = array();
		foreach ( is_array( $value ) ? $value : array() as $map_key => $item ) {
			if ( in_array( (string) $map_key, $allowed, true ) ) {
				$item = $this->clean_string( $item );
				if ( $this->has_choices( $field ) && ! $this->is_choice( $field, $item ) ) {
					$item = '';
				}
				$result[ (string) $map_key ] = $item;
			}
		}
		return $result;
	}

	/**
	 * Whether the field declares a choices list.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private function has_choices( array $field ): bool {
		return isset( $field['choices'] ) && is_array( $field['choices'] );
	}

	/**
	 * Whether a string is one of the field's choice keys.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $value Candidate.
	 * @return bool
	 */
	private function is_choice( array $field, string $value ): bool {
		return in_array( $value, array_map( 'strval', array_keys( $this->map_of( $field, 'choices' ) ) ), true );
	}

	/**
	 * Whether a string is an absolute http(s) URL.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private function is_url( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_URL ) && 1 === preg_match( '#^https?://#i', $value );
	}

	/**
	 * List item format: 'string' (default) or 'url'.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return string
	 */
	private function item_format( array $field ): string {
		return isset( $field['item_format'] ) && 'url' === $field['item_format'] ? 'url' : 'string';
	}

	/**
	 * A map-valued definition entry. Choices map to labels; keys map to a label or a {label, note} array.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $name  Definition key.
	 * @return array<string, mixed>
	 */
	private function map_of( array $field, string $name ): array {
		return isset( $field[ $name ] ) && is_array( $field[ $name ] ) ? $field[ $name ] : array();
	}

	/**
	 * Associative array as an ordered list of {value,label} pairs.
	 *
	 * @param array<string, mixed> $map Value to label.
	 * @return array<int, array{value: string, label: string}>
	 */
	private function pairs( array $map ): array {
		$pairs = array();
		foreach ( $map as $value => $label ) {
			$pairs[] = array(
				'value' => (string) $value,
				'label' => is_scalar( $label ) ? (string) $label : '',
			);
		}
		return $pairs;
	}

	/**
	 * Choices for the UI: always for select and multiselect, for a map only when it declares choices.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $type  Field type.
	 * @return array<int, array{value: string, label: string}>|null
	 */
	private function described_choices( array $field, string $type ): ?array {
		if ( in_array( $type, array( 'select', 'multiselect' ), true ) || ( 'map' === $type && $this->has_choices( $field ) ) ) {
			return $this->pairs( $this->map_of( $field, 'choices' ) );
		}
		return null;
	}

	/**
	 * Map keys as an ordered list of {value,label} entries, plus a note when the key was declared with one.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return array<int, array{value: string, label: string, note?: string}>
	 */
	private function key_entries( array $field ): array {
		$entries = array();
		foreach ( $this->map_of( $field, 'keys' ) as $key => $definition ) {
			$key = (string) $key;
			if ( ! is_array( $definition ) ) {
				$entries[] = array(
					'value' => $key,
					'label' => is_scalar( $definition ) ? (string) $definition : $key,
				);
				continue;
			}

			$entry = array(
				'value' => $key,
				'label' => isset( $definition['label'] ) && is_scalar( $definition['label'] ) ? (string) $definition['label'] : $key,
			);
			if ( isset( $definition['note'] ) && is_scalar( $definition['note'] ) && '' !== (string) $definition['note'] ) {
				$entry['note'] = (string) $definition['note'];
			}
			$entries[] = $entry;
		}
		return $entries;
	}

	/**
	 * Character length, multibyte-aware when mbstring is available.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Empty typed value used when a definition has no default.
	 *
	 * @param string $type Field type.
	 * @return mixed
	 */
	private function empty_value( string $type ) {
		switch ( $type ) {
			case 'boolean':
				return false;
			case 'integer':
			case 'image':
				return 0;
			case 'multiselect':
			case 'list':
			case 'map':
				return array();
			default:
				return '';
		}
	}
}

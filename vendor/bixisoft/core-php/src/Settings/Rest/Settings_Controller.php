<?php
/**
 * REST controller for schema-driven settings.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Settings\Rest;

use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Subclasses set the namespace, the capability and the error code.
 */
abstract class Settings_Controller extends WP_REST_Controller {

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Capability required for every route.
	 *
	 * @var string
	 */
	protected string $capability = 'manage_options';

	/**
	 * Error code returned with a 400 when values are invalid.
	 *
	 * @var string
	 */
	protected string $error_code = 'bixisoft_invalid_settings';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings  = $settings;
		$this->rest_base = 'settings';
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$base = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'include' => array(
							'description' => 'Set to "schema" to include the schema description.',
							'type'        => 'string',
							'enum'        => array( 'schema' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->settings->schema()->rest_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schema' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'group' => array(
							'description' => 'Schema group to reset; omit to reset everything.',
							'type'        => 'string',
							'enum'        => array_map( 'strval', array_keys( $this->settings->schema()->groups() ) ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Capability check shared by every route. Nonces come from apiFetch's middleware.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( $this->capability );
	}

	/**
	 * GET settings[?include=schema].
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$data = array( 'values' => $this->settings->all() );
		if ( 'schema' === $request->get_param( 'include' ) ) {
			$data['schema'] = $this->settings->schema()->describe();
		}
		return rest_ensure_response( $data );
	}

	/**
	 * POST settings with a partial key to value map.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		return $this->respond( $this->settings->update( $this->payload( $request ) ) );
	}

	/**
	 * GET settings/schema.
	 *
	 * @return WP_REST_Response
	 */
	public function get_schema(): WP_REST_Response {
		return rest_ensure_response( $this->settings->schema()->describe() );
	}

	/**
	 * POST settings/reset with an optional group.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function reset_settings( WP_REST_Request $request ): WP_REST_Response {
		$group = $request->get_param( 'group' );
		return rest_ensure_response(
			array(
				'values' => $this->settings->reset( is_string( $group ) && '' !== $group ? $group : null ),
			)
		);
	}

	/**
	 * GET settings/export.
	 *
	 * @return WP_REST_Response
	 */
	public function export_settings(): WP_REST_Response {
		return rest_ensure_response( $this->settings->export() );
	}

	/**
	 * POST settings/import with an export payload or a bare map.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_settings( WP_REST_Request $request ) {
		return $this->respond( $this->settings->import( $this->payload( $request ) ) );
	}

	/**
	 * Turn an update result into a response or a 400 error carrying the error map.
	 *
	 * @param array{values: array<string, mixed>, errors: array<string, string>} $result Settings result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( array $result ) {
		if ( array() !== $result['errors'] ) {
			return new WP_Error(
				$this->error_code,
				'Some settings are invalid.',
				array(
					'status' => 400,
					'errors' => $result['errors'],
				)
			);
		}
		return rest_ensure_response( array( 'values' => $result['values'] ) );
	}

	/**
	 * Request body as an array.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string, mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		return $body;
	}
}

<?php
/**
 * REST endpoint returning the Visibility Score for the home page.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Rest;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Score\Check_Registry;
use Bixisoft\Rankavia\Vendor\Core\Score\Result;
use Bixisoft\Rankavia\Vendor\Core\Score\Scorer;
use Bixisoft\Rankavia\Score\Page_Analyzer;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /rankavia/v1/score[?refresh=1]
 */
final class Score_Controller extends WP_REST_Controller {

	public const TRANSIENT = 'rankavia_score_home';

	/**
	 * Shared container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Shared container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		$this->namespace = 'rankavia/v1';
		$this->rest_base = 'score';
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_score' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'refresh' => array(
							'description' => __( 'Recompute instead of returning the cached score.', 'rankavia' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Only administrators can trigger a scan of the site.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Fetch the home page, analyse it and score it.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_score( WP_REST_Request $request ) {
		$refresh = (bool) $request->get_param( 'refresh' );
		$cached  = get_transient( self::TRANSIENT );

		if ( ! $refresh && is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$url      = home_url( '/' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Rankavia/' . RANKAVIA_VERSION . ' (+' . $url . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'rankavia_fetch_failed',
				sprintf(
					/* translators: %s: error message from the HTTP request. */
					__( 'Rankavia could not fetch your home page: %s', 'rankavia' ),
					$response->get_error_message()
				),
				array( 'status' => 502 )
			);
		}

		$html    = (string) wp_remote_retrieve_body( $response );
		$headers = $this->header_map( wp_remote_retrieve_headers( $response ) );

		$analyzer = $this->container->get( 'page_analyzer' );
		$scorer   = $this->container->get( 'scorer' );
		$registry = $this->container->get( 'check_registry' );

		if ( ! $analyzer instanceof Page_Analyzer || ! $scorer instanceof Scorer || ! $registry instanceof Check_Registry ) {
			return new WP_Error( 'rankavia_not_ready', __( 'Scoring services are not available.', 'rankavia' ), array( 'status' => 500 ) );
		}

		$results = $analyzer->analyze( $html, $url, $headers );

		$payload = array(
			'url'         => $url,
			'status'      => (int) wp_remote_retrieve_response_code( $response ),
			'generatedAt' => gmdate( 'c' ),
			'scores'      => $scorer->score( $results ),
			'results'     => array_map(
				static function ( Result $result ) use ( $registry ): array {
					$meta = $registry->get( $result->id() );
					return array_merge( is_array( $meta ) ? $meta : array(), $result->to_array() );
				},
				$results
			),
		);

		set_transient( self::TRANSIENT, $payload, 10 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $payload );
	}

	/**
	 * Normalise response headers to a lower-cased string map.
	 *
	 * @param mixed $headers Whatever wp_remote_retrieve_headers() returned.
	 * @return array<string, string>
	 */
	private function header_map( $headers ): array {
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		if ( ! is_array( $headers ) ) {
			return array();
		}

		$map = array();
		foreach ( $headers as $name => $value ) {
			$map[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return $map;
	}
}

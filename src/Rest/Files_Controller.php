<?php
/**
 * REST endpoint previewing robots.txt, llms.txt and the sitemap index for the admin screen.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Rest;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Modules\Llms\Llms_Module;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /rankavia/v1/files/(robots|llms|sitemap), restricted to administrators.
 */
final class Files_Controller {

	/**
	 * Route namespace.
	 */
	public const REST_NAMESPACE = 'rankavia/v1';

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
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/files/(?P<name>robots|llms|sitemap)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_file' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Only administrators can read the previews.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Serve one file description.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_file( WP_REST_Request $request ) {
		$payload = $this->describe( (string) $request->get_param( 'name' ) );
		if ( null === $payload ) {
			return new WP_Error( 'rankavia_unknown_file', __( 'Unknown file.', 'rankavia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $payload );
	}

	/**
	 * Describe one file: its URL, what is served now, whether it is active and notes for the user.
	 *
	 * @param string $name One of robots, llms, sitemap.
	 * @return array{name: string, url: string, content: string, active: bool, notes: array<int, array{status: string, message: string}>}|null Null for an unknown name.
	 */
	public function describe( string $name ): ?array {
		switch ( $name ) {
			case 'robots':
				return $this->robots();
			case 'llms':
				return $this->llms();
			case 'sitemap':
				return $this->sitemap();
			default:
				return null;
		}
	}

	/**
	 * The robots.txt WordPress would serve now.
	 *
	 * @return array{name: string, url: string, content: string, active: bool, notes: array<int, array{status: string, message: string}>}
	 */
	private function robots(): array {
		$notes    = array();
		$physical = file_exists( ABSPATH . 'robots.txt' );
		if ( $physical ) {
			$notes[] = $this->note( 'warning', __( 'A robots.txt file exists in your site root, so your web server serves it and WordPress never runs. Rankavia does not change or delete that file; remove it to use these settings.', 'rankavia' ) );
		}
		if ( ! $this->is_public() ) {
			$notes[] = $this->note( 'info', __( 'Search engines are discouraged in Settings → Reading, so robots.txt blocks everything.', 'rankavia' ) );
		}

		// do_robots() runs the same filter chain as a real request; it only echoes (and sends a text/plain header the REST server replaces).
		ob_start();
		do_robots();
		$content = (string) ob_get_clean();

		return array(
			'name'    => 'robots',
			'url'     => home_url( '/robots.txt' ),
			'content' => $content,
			'active'  => ! $physical,
			'notes'   => $notes,
		);
	}

	/**
	 * The llms.txt file as it would be built now, even when it is switched off.
	 *
	 * @return array{name: string, url: string, content: string, active: bool, notes: array<int, array{status: string, message: string}>}
	 */
	private function llms(): array {
		$llms     = $this->container->has( 'llms' ) ? $this->container->get( 'llms' ) : null;
		$enabled  = $llms instanceof Llms_Module && $llms->is_enabled();
		$public   = $this->is_public();
		$physical = file_exists( ABSPATH . 'llms.txt' );

		$notes = array();
		if ( ! $enabled ) {
			$notes[] = $this->note( 'info', __( 'llms.txt is switched off, so /llms.txt shows a 404 page. The preview shows what it would contain.', 'rankavia' ) );
		}
		if ( ! $public ) {
			$notes[] = $this->note( 'info', __( 'Search engines are discouraged in Settings → Reading, so /llms.txt is not served.', 'rankavia' ) );
		}
		if ( $physical ) {
			$notes[] = $this->note( 'warning', __( 'An llms.txt file exists in your site root, so your web server serves it and WordPress never runs. Rankavia does not change or delete that file; remove it to use these settings.', 'rankavia' ) );
		}

		return array(
			'name'    => 'llms',
			'url'     => home_url( '/llms.txt' ),
			'content' => $llms instanceof Llms_Module ? $llms->content() : '',
			'active'  => $enabled && $public && ! $physical,
			'notes'   => $notes,
		);
	}

	/**
	 * The sitemap index; the content is XML served by WordPress, so it is left empty.
	 *
	 * @return array{name: string, url: string, content: string, active: bool, notes: array<int, array{status: string, message: string}>}
	 */
	private function sitemap(): array {
		$url = get_sitemap_url( 'index' );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = home_url( '/wp-sitemap.xml' );
		}

		$active = wp_sitemaps_get_server()->sitemaps_enabled();

		$notes = array();
		if ( ! $this->setting_on( 'sitemaps_enabled' ) ) {
			$notes[] = $this->note( 'info', __( 'XML sitemaps are switched off in the Rankavia settings.', 'rankavia' ) );
		} elseif ( ! $this->is_public() ) {
			$notes[] = $this->note( 'info', __( 'Search engines are discouraged in Settings → Reading, so WordPress turns its sitemaps off.', 'rankavia' ) );
		} elseif ( ! $active ) {
			$notes[] = $this->note( 'warning', __( 'Another plugin or filter has turned the WordPress sitemaps off.', 'rankavia' ) );
		}

		return array(
			'name'    => 'sitemap',
			'url'     => $url,
			'content' => '',
			'active'  => $active,
			'notes'   => $notes,
		);
	}

	/**
	 * Whether the site is open to search engines (Settings, Reading).
	 *
	 * @return bool
	 */
	private function is_public(): bool {
		return '0' !== (string) get_option( 'blog_public', '1' );
	}

	/**
	 * Whether a boolean setting is on.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function setting_on( string $key ): bool {
		$settings = $this->container->get( 'settings' );
		return $settings instanceof Settings && (bool) $settings->get( $key );
	}

	/**
	 * One note for the admin screen.
	 *
	 * @param string $status  info, warning or error.
	 * @param string $message Translated message.
	 * @return array{status: string, message: string}
	 */
	private function note( string $status, string $message ): array {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}
}

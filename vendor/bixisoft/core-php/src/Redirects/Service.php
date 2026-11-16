<?php
/**
 * Front-end redirects, 404 logging and automatic redirects for changed addresses.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * The redirect runtime. Several packages can bundle it (Rankavia SEO, the standalone Redirects & 404
 * plugin); each calls claim() and only the owner chosen on `init` boots it, so a request is handled
 * once and every package shares one set of tables.
 */
class Service {
	/** Filter whose final value names the package that owns redirects. */
	public const PROVIDER_FILTER = 'rankavia_redirects_provider';

	/** Contract version between packages. */
	public const API = 1;

	/** Daily retention job for the 404 log. */
	public const PRUNE_HOOK = 'rankavia_redirects_prune';

	/** Path fragments that are scanner probes, not pages anyone links to. */
	private const PROBES = array( '/.env', '/.git', '/wp-content/', '/wp-includes/', 'phpmyadmin', '/cgi-bin/', '.php' );

	/**
	 * Rule storage.
	 *
	 * @var Store
	 */
	protected Store $store;

	/**
	 * 404 log.
	 *
	 * @var Not_Found_Log
	 */
	protected Not_Found_Log $log;

	/**
	 * Build with its storage.
	 *
	 * @param Store|null         $store Rule storage.
	 * @param Not_Found_Log|null $log   404 log.
	 */
	public function __construct( ?Store $store = null, ?Not_Found_Log $log = null ) {
		$this->store = $store ?? new Store();
		$this->log   = $log ?? new Not_Found_Log();
	}

	/**
	 * Offer to own redirects. The claim with the highest priority wins.
	 *
	 * @param string $name     Package name, e.g. 'rankavia' or 'rankavia-redirects-404'.
	 * @param int    $priority Higher wins; Rankavia SEO uses 20, the standalone 10.
	 * @return void
	 */
	public static function claim( string $name, int $priority ): void {
		add_filter(
			self::PROVIDER_FILTER,
			static function () use ( $name ): string {
				return $name;
			},
			$priority
		);
	}

	/**
	 * The package that owns redirects on this request ('' when none claimed). Call on or after `init`.
	 *
	 * @return string
	 */
	public static function owner(): string {
		$owner = apply_filters( self::PROVIDER_FILTER, '' );
		return is_string( $owner ) ? $owner : '';
	}

	/**
	 * Rule storage.
	 *
	 * @return Store
	 */
	public function store(): Store {
		return $this->store;
	}

	/**
	 * 404 log.
	 *
	 * @return Not_Found_Log
	 */
	public function log(): Not_Found_Log {
		return $this->log;
	}

	/**
	 * Create tables if needed and add the runtime hooks. Only the owner calls this.
	 *
	 * @return void
	 */
	public function boot(): void {
		Schema::ensure( (string) home_url( '/' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_log_not_found' ), 99 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( self::PRUNE_HOOK, array( $this, 'prune' ) );
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Validate a new rule against the stored ones.
	 *
	 * @param string $source Source path.
	 * @param string $target Destination path or same-origin URL.
	 * @param int    $status 301, 302 or 410.
	 * @return array{source:string,target:string,status:int}|null
	 */
	public function validate( string $source, string $target, int $status ): ?array {
		$store = $this->store;
		return Rules::create(
			$source,
			$target,
			$status,
			(string) home_url( '/' ),
			static function ( string $path ) use ( $store ): ?array {
				return $store->find( $path );
			}
		);
	}

	/**
	 * Apply the exact stored rule for an eligible front-end request.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		$path = $this->eligible_path( false );
		if ( null === $path ) {
			return;
		}
		$rule = $this->store->find( $path );
		if ( null === $rule ) {
			return;
		}
		$this->store->touch( $path );
		if ( 410 === $rule['status'] ) {
			status_header( 410 );
			nocache_headers();
			$this->terminate();
			return;
		}
		wp_safe_redirect( $rule['target'], $rule['status'], 'Rankavia' );
		$this->terminate();
	}

	/**
	 * Count a "page not found" response. Runs after WordPress's own old-slug redirect.
	 *
	 * @return void
	 */
	public function maybe_log_not_found(): void {
		if ( ! is_404() ) {
			return;
		}
		$path = $this->eligible_path( true );
		if ( null === $path || strlen( $path ) > Rules::SOURCE_MAX ) {
			return;
		}
		$lower = strtolower( $path );
		foreach ( self::PROBES as $probe ) {
			if ( false !== strpos( $lower, $probe ) ) {
				return;
			}
		}
		$this->log->record( $path );
	}

	/**
	 * Add a redirect when a published post or page gets a new address.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $after   Post after the update.
	 * @param \WP_Post $before  Post before the update.
	 * @return void
	 */
	public function on_post_updated( $post_id, $after, $before ): void {
		if ( ! $after instanceof \WP_Post || ! $before instanceof \WP_Post || 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
			return;
		}
		if ( wp_is_post_revision( $after ) || wp_is_post_autosave( $after ) || ! is_post_type_viewable( $after->post_type ) ) {
			return;
		}
		$old_url = get_permalink( $before );
		$new_url = get_permalink( $after );
		if ( ! is_string( $old_url ) || ! is_string( $new_url ) ) {
			return;
		}
		$old_path = (string) wp_parse_url( $old_url, PHP_URL_PATH );
		$new_path = (string) wp_parse_url( $new_url, PHP_URL_PATH );
		if ( '' === $old_path || $old_path === $new_path || false !== strpos( $old_url, '?' ) || false !== strpos( $new_url, '?' ) ) {
			return;
		}
		// The page lives at its new address again: an earlier automatic redirect away from it must go.
		$back = $this->store->row( $new_path );
		if ( null !== $back && 'slug' === $back['origin'] ) {
			$this->store->delete( $new_path );
		}
		// Earlier automatic redirects to the old address now point straight at the new one.
		$this->store->retarget( $old_url, $new_url );
		$rule = $this->validate( $old_path, $new_path, 301 );
		if ( null !== $rule ) {
			$this->store->add( $rule, 'slug' );
		}
		unset( $post_id );
	}

	/**
	 * Daily retention job.
	 *
	 * @return void
	 */
	public function prune(): void {
		$this->log->prune();
	}

	/**
	 * The request path when this is a front-end GET/HEAD by a visitor who is not logged in.
	 *
	 * @param bool $strip_query Whether to drop a query string (404 logging) instead of skipping the request (redirects).
	 * @return string|null
	 */
	protected function eligible_path( bool $strip_query ): ?string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compared to GET/HEAD only.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact URI is checked by Rules::safe_path; normalizing would change matching.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || is_admin() || is_user_logged_in() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || '' === $uri ) {
			return null;
		}
		$query = strpos( $uri, '?' );
		if ( false !== $query ) {
			if ( ! $strip_query ) {
				return null;
			}
			$uri = substr( $uri, 0, $query );
		}
		return Rules::safe_path( $uri, (string) home_url( '/' ) ) ? $uri : null;
	}

	/**
	 * Stop after a redirect; overridden in tests.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}
}

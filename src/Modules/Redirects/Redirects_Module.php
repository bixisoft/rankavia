<?php
/**
 * Redirects and the 404 log inside Rankavia SEO.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Redirects;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Redirects\Admin_Page;
use Bixisoft\Rankavia\Vendor\Core\Redirects\Service;

/**
 * Rankavia SEO claims the shared redirect runtime with priority 20, so when the standalone
 * Redirects & 404 plugin is also active this module owns the handler, tables and screen.
 */
class Redirects_Module implements Module {
	public const SLUG = 'rankavia-redirects';

	/** Package name used in the ownership claim. */
	public const PROVIDER = 'rankavia';

	/**
	 * Redirect runtime.
	 *
	 * @var Service|null
	 */
	private ?Service $service = null;

	/**
	 * Admin screen.
	 *
	 * @var Admin_Page|null
	 */
	private ?Admin_Page $page = null;

	/** Return the module identifier. @return string */
	public function id(): string {
		return 'redirects';
	}

	/**
	 * No services are needed.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		unset( $container );
	}

	/**
	 * Claim the runtime and add the menu entry.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		unset( $container );
		Service::claim( self::PROVIDER, 20 );
		add_action( 'init', array( $this, 'start' ), 5 );
		// Register after the Rankavia parent menu so WordPress retains its dashboard link.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_filter( 'rankavia_admin_nav_links', array( $this, 'nav_link' ) );
	}

	/**
	 * Boot the runtime when this package owns it.
	 *
	 * @return void
	 */
	public function start(): void {
		if ( self::PROVIDER !== Service::owner() ) {
			return;
		}
		$this->service = new Service();
		$this->service->boot();
		$this->page = new Admin_Page(
			$this->service,
			self::SLUG,
			self::labels(),
			array(
				'open'  => static function ( string $title ): void {
					do_action( 'rankavia_admin_shell_open', 'redirects', $title );
				},
				'close' => static function (): void {
					do_action( 'rankavia_admin_shell_close' );
				},
			)
		);
		$this->page->hooks();
	}

	/** Add a native submenu under the existing Rankavia parent. @return void */
	public function menu(): void {
		if ( null === $this->page ) {
			return;
		}
		add_submenu_page( 'rankavia', __( 'Redirects', 'rankavia' ), __( 'Redirects', 'rankavia' ), 'manage_options', self::SLUG, array( $this->page, 'render' ) );
	}

	/**
	 * List this page in the shared Rankavia navigation.
	 *
	 * @param mixed $links Links collected so far.
	 * @return array<int, mixed>
	 */
	public function nav_link( $links ): array {
		$links   = is_array( $links ) ? array_values( $links ) : array();
		$links[] = array(
			'id'    => 'redirects',
			'title' => __( 'Redirects', 'rankavia' ),
			'slug'  => self::SLUG,
			'order' => 32,
		);
		return $links;
	}

	/**
	 * Translated labels for the shared screen.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			'forbidden'           => __( 'You cannot manage redirects.', 'rankavia' ),
			'title'               => __( 'Redirects', 'rankavia' ),
			'tab_redirects'       => __( 'Redirects', 'rankavia' ),
			'tab_not_found'       => __( 'Not found (404)', 'rankavia' ),
			'intro'               => __( 'Send an old address on this site to a new one. Exact paths only; destinations stay on this site. Logged-in visitors and requests with a query string are not redirected.', 'rankavia' ),
			'add_heading'         => __( 'Add a redirect', 'rankavia' ),
			'rules_heading'       => __( 'Redirects', 'rankavia' ),
			'search'              => __( 'Search redirects', 'rankavia' ),
			'col_source'          => __( 'Old address', 'rankavia' ),
			'col_target'          => __( 'New address', 'rankavia' ),
			'col_status'          => __( 'Type', 'rankavia' ),
			'col_origin'          => __( 'Added by', 'rankavia' ),
			'col_used'            => __( 'Last used', 'rankavia' ),
			'col_action'          => __( 'Action', 'rankavia' ),
			'origin_manual'       => __( 'You', 'rankavia' ),
			'origin_slug'         => __( 'Address change', 'rankavia' ),
			'origin_not_found'    => __( 'From the 404 list', 'rankavia' ),
			'origin_migrated'     => __( 'Earlier version', 'rankavia' ),
			'never'               => __( 'Not yet', 'rankavia' ),
			'delete'              => __( 'Delete', 'rankavia' ),
			'empty'               => __( 'No redirects yet. Add the first one above.', 'rankavia' ),
			'no_match'            => __( 'No redirects match your search.', 'rankavia' ),
			'export'              => __( 'Download all as CSV', 'rankavia' ),
			'log_intro'           => __( 'Addresses on this site that visitors asked for and did not find, most requested first. Only the path and a count are kept: no IP address, query string or browser details. Paths not seen for 30 days are removed.', 'rankavia' ),
			/* translators: %s: number of requests. */
			'log_dropped'         => __( 'The list is full, so %s requests for new missing addresses were counted but not listed. Fix or dismiss some rows to make room.', 'rankavia' ),
			'col_path'            => __( 'Missing address', 'rankavia' ),
			'col_hits'            => __( 'Requests', 'rankavia' ),
			'col_last_seen'       => __( 'Last requested', 'rankavia' ),
			'col_fix'             => __( 'Fix', 'rankavia' ),
			'forget'              => __( 'Dismiss', 'rankavia' ),
			'log_empty'           => __( 'No missing pages recorded yet.', 'rankavia' ),
			'clear'               => __( 'Clear the whole list', 'rankavia' ),
			'source'              => __( 'Old address (path)', 'rankavia' ),
			'target'              => __( 'New address', 'rankavia' ),
			'target_help'         => __( 'A path such as /new-page/ or a full address on this site. Leave empty for "Gone".', 'rankavia' ),
			'status'              => __( 'Type', 'rankavia' ),
			'status_301'          => __( '301 Moved permanently', 'rankavia' ),
			'status_302'          => __( '302 Moved temporarily', 'rankavia' ),
			'status_410'          => __( '410 Gone', 'rankavia' ),
			'add'                 => __( 'Add redirect', 'rankavia' ),
			'redirect'            => __( 'Redirect', 'rankavia' ),
			'migration_failed'    => __( 'Redirects saved by an earlier version could not be moved to the new list because they look damaged. They were left as they were, and none of them run. Please contact support.', 'rankavia' ),
			'notice_added'        => __( 'Redirect added.', 'rankavia' ),
			'notice_deleted'      => __( 'Redirect deleted.', 'rankavia' ),
			'notice_invalid'      => __( 'That redirect cannot be added: check the paths, and that it does not point back to itself or lead in a circle.', 'rankavia' ),
			'notice_exists'       => __( 'That old address already has a redirect.', 'rankavia' ),
			'notice_stale'        => __( 'The list changed in the meantime. Reload and try again.', 'rankavia' ),
			'notice_undo_expired' => __( 'There is nothing to undo any more.', 'rankavia' ),
			'notice_undone'       => __( 'Change undone.', 'rankavia' ),
			'notice_forgotten'    => __( 'Address dismissed from the list.', 'rankavia' ),
			'notice_cleared'      => __( 'The list of missing pages was cleared.', 'rankavia' ),
			'undo'                => __( 'Undo', 'rankavia' ),
			'previous'            => __( 'Previous page', 'rankavia' ),
			'next'                => __( 'Next page', 'rankavia' ),
			/* translators: 1: current page number, 2: number of pages. */
			'page_of'             => __( 'Page %1$s of %2$s', 'rankavia' ),
		);
	}
}

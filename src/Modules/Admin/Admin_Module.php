<?php
/**
 * Admin menu, screen registry and the React admin app.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Admin;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Templates\Variables;

/**
 * Registers the Rankavia menu with one submenu per screen and loads the built admin app on them.
 */
final class Admin_Module implements Module {

	public const MENU_SLUG = 'rankavia';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'admin';
	}

	/**
	 * Nothing to bind.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		unset( $container );
	}

	/**
	 * Add admin hooks.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		unset( $container );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * The screen registry: built-in screens plus whatever modules add, in menu order.
	 *
	 * @return array<int, array{id: string, title: string, menu_title: string, capability: string, order: int, groups: array<int, string>}>
	 */
	public function screens(): array {
		$screens = array(
			array(
				'id'         => 'dashboard',
				'title'      => __( 'Dashboard', 'rankavia' ),
				'menu_title' => __( 'Dashboard', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 0,
				'groups'     => array(),
			),
			array(
				'id'         => 'search-appearance',
				'title'      => __( 'Search appearance', 'rankavia' ),
				'menu_title' => __( 'Search appearance', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 20,
				'groups'     => array( 'titles', 'search_appearance' ),
			),
			array(
				'id'         => 'crawlers',
				'title'      => __( 'Sitemaps & crawlers', 'rankavia' ),
				'menu_title' => __( 'Sitemaps & crawlers', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 25,
				'groups'     => array( 'sitemaps', 'ai_crawlers', 'robots_txt', 'llms_txt' ),
			),
			array(
				'id'         => 'social',
				'title'      => __( 'Social', 'rankavia' ),
				'menu_title' => __( 'Social', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 30,
				'groups'     => array( 'social' ),
			),
			array(
				'id'         => 'tools',
				'title'      => __( 'Tools', 'rankavia' ),
				'menu_title' => __( 'Tools', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 40,
				'groups'     => array(),
			),
			array(
				'id'         => 'settings',
				'title'      => __( 'Settings', 'rankavia' ),
				'menu_title' => __( 'Settings', 'rankavia' ),
				'capability' => 'manage_options',
				'order'      => 50,
				'groups'     => array( 'general', 'advanced' ),
			),
		);

		/**
		 * Filters the admin screens. Modules add their own screen here.
		 *
		 * @param array<int, array<string, mixed>> $screens Screens: id, title, menu_title, capability, order, groups.
		 */
		$filtered = apply_filters( 'rankavia_admin_screens', $screens );

		$clean = array();
		foreach ( is_array( $filtered ) ? $filtered : array() as $screen ) {
			$normal = $this->normalize_screen( $screen );
			if ( null !== $normal ) {
				$clean[ $normal['id'] ] = $normal;
			}
		}

		$clean = array_values( $clean );
		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return $a['order'] <=> $b['order'];
			}
		);

		return $clean;
	}

	/**
	 * Page slug of a screen: "rankavia" for the dashboard, otherwise "rankavia-<id>".
	 *
	 * @param string $id Screen id.
	 * @return string
	 */
	public function slug( string $id ): string {
		return 'dashboard' === $id ? self::MENU_SLUG : self::MENU_SLUG . '-' . $id;
	}

	/**
	 * Top-level menu entry and one submenu per screen.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$screens = $this->screens();

		$capability = 'manage_options';
		foreach ( $screens as $screen ) {
			if ( 'dashboard' === $screen['id'] ) {
				$capability = $screen['capability'];
			}
		}

		add_menu_page(
			__( 'Rankavia', 'rankavia' ),
			__( 'Rankavia', 'rankavia' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-chart-area',
			58
		);

		foreach ( $screens as $screen ) {
			add_submenu_page(
				self::MENU_SLUG,
				$screen['title'],
				$screen['menu_title'],
				$screen['capability'],
				$this->slug( $screen['id'] ),
				array( $this, 'render' )
			);
		}
	}

	/**
	 * Load the built app on every Rankavia screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		$screens = $this->screens();
		$current = $this->screen_id_for_hook( $hook_suffix, $screens );
		if ( '' === $current ) {
			return;
		}

		$asset_file = $this->asset_file();
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/**
		 * Generated by @wordpress/scripts.
		 *
		 * @var array{dependencies: string[], version: string} $asset
		 */
		$asset = require $asset_file;

		wp_enqueue_media();

		wp_enqueue_script(
			'rankavia-admin',
			RANKAVIA_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		if ( file_exists( RANKAVIA_PATH . 'build/style-index.css' ) ) {
			wp_enqueue_style( 'rankavia-admin', RANKAVIA_URL . 'build/style-index.css', array( 'wp-components' ), $asset['version'] );
		}

		$config_screens = array();
		foreach ( $screens as $screen ) {
			if ( ! current_user_can( $screen['capability'] ) ) {
				continue;
			}
			$config_screens[] = array(
				'id'     => $screen['id'],
				'title'  => $screen['title'],
				'url'    => admin_url( 'admin.php?page=' . $this->slug( $screen['id'] ) ),
				'groups' => $screen['groups'],
			);
		}

		wp_add_inline_script(
			'rankavia-admin',
			'window.rankaviaAdmin = ' . (string) wp_json_encode(
				array(
					'version'           => RANKAVIA_VERSION,
					'homeUrl'           => home_url( '/' ),
					'adminUrl'          => admin_url(),
					'restNamespace'     => 'rankavia/v1',
					'siteName'          => (string) get_bloginfo( 'name' ),
					'screens'           => $config_screens,
					'currentScreen'     => $current,
					'templateVariables' => Variables::samples(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Path of the asset manifest written by @wordpress/scripts.
	 *
	 * @return string
	 */
	private function asset_file(): string {
		/**
		 * Filters where the admin app's asset manifest is read from.
		 *
		 * @param string $path Absolute path to build/index.asset.php.
		 */
		return (string) apply_filters( 'rankavia_admin_asset_file', RANKAVIA_PATH . 'build/index.asset.php' );
	}

	/**
	 * Screen id for an admin page hook, or an empty string when it is not one of ours.
	 *
	 * Hooks look like "toplevel_page_rankavia" or "rankavia_page_rankavia-social". Matching the page slug
	 * against the registry keeps other plugins whose slug also starts with "rankavia" out.
	 *
	 * @param string                           $hook_suffix Admin page hook.
	 * @param array<int, array<string, mixed>> $screens     Registry from screens().
	 * @return string
	 */
	private function screen_id_for_hook( string $hook_suffix, array $screens ): string {
		if ( 1 !== preg_match( '/(?:^|_page_)(rankavia(?:-[a-z0-9_-]+)?)$/i', $hook_suffix, $matches ) ) {
			return '';
		}

		foreach ( $screens as $screen ) {
			if ( $this->slug( (string) $screen['id'] ) === $matches[1] ) {
				return (string) $screen['id'];
			}
		}
		return '';
	}

	/**
	 * Fill defaults on one screen definition, or null when it has no usable id.
	 *
	 * @param mixed $screen Raw definition.
	 * @return array{id: string, title: string, menu_title: string, capability: string, order: int, groups: array<int, string>}|null
	 */
	private function normalize_screen( $screen ): ?array {
		if ( ! is_array( $screen ) || ! isset( $screen['id'] ) || ! is_string( $screen['id'] ) || '' === $screen['id'] ) {
			return null;
		}

		$title  = isset( $screen['title'] ) ? (string) $screen['title'] : $screen['id'];
		$groups = array();
		foreach ( isset( $screen['groups'] ) && is_array( $screen['groups'] ) ? $screen['groups'] : array() as $group ) {
			$groups[] = (string) $group;
		}

		return array(
			'id'         => $screen['id'],
			'title'      => $title,
			'menu_title' => isset( $screen['menu_title'] ) ? (string) $screen['menu_title'] : $title,
			'capability' => isset( $screen['capability'] ) ? (string) $screen['capability'] : 'manage_options',
			'order'      => isset( $screen['order'] ) ? (int) $screen['order'] : 100,
			'groups'     => $groups,
		);
	}

	/**
	 * Page markup; the app mounts into #rankavia-app.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="wrap rankavia-wrap">';
		echo '<h1>' . esc_html__( 'Rankavia', 'rankavia' ) . '</h1>';
		if ( ! file_exists( $this->asset_file() ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The admin app has not been built yet. Run "npm install && npm run build" in the repository.', 'rankavia' ) . '</p></div>';
		}
		echo '<div id="rankavia-app"></div>';
		echo '</div>';
	}
}

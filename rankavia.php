<?php
/**
 * Plugin Name:       Rankavia – SEO and Site Discovery
 * Plugin URI:        https://rankavia.com/
 * Description:       SEO metadata, site discovery controls, selected structured data and a Visibility Score dashboard, with previewable Yoast and Rank Math post metadata migration.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Bixisoft
 * Author URI:        https://bixisoft.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rankavia
 *
 * @package Rankavia
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'RANKAVIA_VERSION', '0.1.0' );
define( 'RANKAVIA_FILE', __FILE__ );
define( 'RANKAVIA_PATH', plugin_dir_path( __FILE__ ) );
define( 'RANKAVIA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the Composer autoloader: the plugin's own vendor/ in a built zip, or the monorepo root vendor/ in development.
 *
 * @return bool Whether an autoloader was found.
 */
function rankavia_load_autoloader(): bool {
	$candidates = array(
		// A deployment or dev environment (wp-env mounts the monorepo) can point straight at an autoloader.
		defined( 'BIXISOFT_AUTOLOADER' ) ? (string) BIXISOFT_AUTOLOADER : '',
		RANKAVIA_PATH . 'vendor/autoload.php',
		dirname( RANKAVIA_PATH, 2 ) . '/vendor/autoload.php',
	);
	foreach ( $candidates as $autoloader ) {
		if ( '' !== $autoloader && file_exists( $autoloader ) ) {
			require_once $autoloader;
			return true;
		}
	}
	return false;
}

if ( ! rankavia_load_autoloader() || ! class_exists( \Bixisoft\Rankavia\Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Rankavia could not load its dependencies. Install the plugin from a release zip, or run "composer install" in the repository.', 'rankavia' ) . '</p></div>';
		}
	);
	return;
}

register_activation_hook( __FILE__, array( \Bixisoft\Rankavia\Plugin::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\Bixisoft\Rankavia\Plugin::instance()->boot();
	}
);

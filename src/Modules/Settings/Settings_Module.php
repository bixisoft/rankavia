<?php
/**
 * Registers the settings REST routes.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Settings;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Rest\Settings_Controller;

/**
 * The settings services live in the plugin bootstrap; this module only exposes them over REST.
 */
final class Settings_Module implements Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'settings';
	}

	/**
	 * Nothing to bind: 'options', 'schema' and 'settings' are registered by the plugin.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		unset( $container );
	}

	/**
	 * Register the REST controller.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$settings = $container->get( 'settings' );
				if ( $settings instanceof Settings ) {
					( new Settings_Controller( $settings ) )->register_routes();
				}
			}
		);
	}
}

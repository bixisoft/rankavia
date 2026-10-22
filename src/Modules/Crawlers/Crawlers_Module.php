<?php
/**
 * Adds the AI crawler policy, content signals and custom rules to WordPress' virtual robots.txt.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Crawlers;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Crawlers\Robots_Builder;
use Bixisoft\Rankavia\Rest\Files_Controller;
use RuntimeException;

/**
 * Hooks the robots_txt filter. No file is ever written to the site root.
 */
final class Crawlers_Module implements Module {

	/**
	 * Priority after core's sitemap line, which is added at the default priority.
	 */
	public const FILTER_PRIORITY = 20;

	/**
	 * Shared container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'crawlers';
	}

	/**
	 * Keep the container for lazy access to the settings.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Add the robots.txt filter and the files preview route.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;

		add_filter( 'robots_txt', array( $this, 'filter_robots' ), self::FILTER_PRIORITY, 2 );
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				( new Files_Controller( $container ) )->register_routes();
			}
		);
	}

	/**
	 * Extend the robots.txt WordPress built.
	 *
	 * @param mixed $output Core's robots.txt text.
	 * @param mixed $public The blog_public option: '0' when search engines are discouraged.
	 * @return string
	 */
	public function filter_robots( $output, $public = '1' ): string {
		$settings = $this->settings();
		$policy   = $settings->get( 'ai_crawler_policy' );

		return Robots_Builder::build(
			is_string( $output ) ? $output : '',
			'0' !== (string) $public,
			is_array( $policy ) ? array_map( 'strval', $policy ) : array(),
			array(
				'search'   => (string) $settings->get( 'content_signal_search' ),
				'ai-input' => (string) $settings->get( 'content_signal_ai_input' ),
				'ai-train' => (string) $settings->get( 'content_signal_ai_train' ),
			),
			(string) $settings->get( 'robots_txt_custom' )
		);
	}

	/**
	 * Settings service.
	 *
	 * @return Settings
	 * @throws RuntimeException When the service is missing.
	 */
	private function settings(): Settings {
		$settings = $this->container->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			throw new RuntimeException( 'The settings service is not registered.' );
		}
		return $settings;
	}
}

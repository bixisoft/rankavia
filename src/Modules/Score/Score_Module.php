<?php
/**
 * Visibility Score: check catalogue, analyzer, scorer and REST endpoint.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Score;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Score\Check_Registry;
use Bixisoft\Rankavia\Vendor\Core\Score\Scorer;
use Bixisoft\Rankavia\Rest\Score_Controller;
use Bixisoft\Rankavia\Score\Page_Analyzer;

/**
 * Registers scoring services and the REST route.
 */
final class Score_Module implements Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'score';
	}

	/**
	 * Bind the catalogue, analyzer and scorer.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			'check_registry',
			static function (): Check_Registry {
				return Check_Registry::from_file( RANKAVIA_PATH . 'data/checks.json' );
			}
		);

		$container->singleton(
			'page_analyzer',
			static function (): Page_Analyzer {
				return new Page_Analyzer();
			}
		);

		$container->singleton(
			'scorer',
			static function ( Container $c ): Scorer {
				$registry = $c->get( 'check_registry' );
				if ( ! $registry instanceof Check_Registry ) {
					$registry = new Check_Registry( array() );
				}
				return new Scorer( $registry );
			}
		);
	}

	/**
	 * Register the REST route.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				( new Score_Controller( $container ) )->register_routes();
			}
		);
	}
}

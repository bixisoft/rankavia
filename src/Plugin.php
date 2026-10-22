<?php
/**
 * Plugin entry: wires the kernel, shared services and modules.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Kernel;
use Bixisoft\Rankavia\Vendor\Core\Options;
use Bixisoft\Rankavia\Vendor\Core\Settings\Schema;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Modules\Admin\Admin_Module;
use Bixisoft\Rankavia\Modules\Answers\Faq_Module;
use Bixisoft\Rankavia\Modules\Breadcrumbs\Breadcrumbs_Module;
use Bixisoft\Rankavia\Modules\Crawlers\Crawlers_Module;
use Bixisoft\Rankavia\Modules\Editor\Editor_Module;
use Bixisoft\Rankavia\Modules\Llms\Llms_Module;
use Bixisoft\Rankavia\Modules\Meta\Meta_Module;
use Bixisoft\Rankavia\Modules\Migration\Migration_Module;
use Bixisoft\Rankavia\Modules\Redirects\Redirects_Module;
use Bixisoft\Rankavia\Modules\Score\Score_Module;
use Bixisoft\Rankavia\Modules\Schema\Schema_Module;
use Bixisoft\Rankavia\Modules\Settings\Settings_Module;
use Bixisoft\Rankavia\Modules\Sitemaps\Sitemaps_Module;
use Bixisoft\Rankavia\Modules\Sitemaps\Html_Sitemap_Module;
use Bixisoft\Rankavia\Settings\Schema_Factory;

/**
 * Singleton owning the kernel for this plugin.
 */
final class Plugin {

	public const OPTION_NAME = 'rankavia_settings';

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Kernel with container and modules.
	 *
	 * @var Kernel
	 */
	private Kernel $kernel;

	/**
	 * Build the kernel and register modules (no hooks yet).
	 */
	private function __construct() {
		$this->kernel = new Kernel();

		$container = $this->kernel->container();
		$container->instance( 'version', RANKAVIA_VERSION );

		// Built lazily: the schema labels are translated, which must not happen before init.
		$container->singleton(
			'schema',
			static function (): Schema {
				return Schema_Factory::create();
			}
		);
		$container->singleton(
			'options',
			static function ( Container $c ): Options {
				$schema = $c->get( 'schema' );
				return new Options( self::OPTION_NAME, $schema instanceof Schema ? $schema->defaults() : array() );
			}
		);
		$container->singleton(
			'settings',
			static function ( Container $c ): Settings {
				$options = $c->get( 'options' );
				$schema  = $c->get( 'schema' );
				return new Settings(
					$options instanceof Options ? $options : new Options( self::OPTION_NAME ),
					$schema instanceof Schema ? $schema : new Schema( array() )
				);
			}
		);

		$this->kernel
			->add( new Meta_Module() )
			->add( new Schema_Module() )
			->add( new Breadcrumbs_Module() )
			->add( new Faq_Module() )
			->add( new Redirects_Module() )
			->add( new Migration_Module() )
			->add( new Score_Module() )
			->add( new Admin_Module() )
			->add( new Editor_Module() )
			->add( new Settings_Module() )
			->add( new Sitemaps_Module() )
			->add( new Html_Sitemap_Module() )
			->add( new Crawlers_Module() )
			->add( new Llms_Module() );
	}

	/**
	 * Shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Boot every enabled module. Called on plugins_loaded.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->kernel->boot();

		/**
		 * Fires once Rankavia has booted all modules.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'rankavia_loaded', $this );
	}

	/**
	 * The kernel (modules and container).
	 *
	 * @return Kernel
	 */
	public function kernel(): Kernel {
		return $this->kernel;
	}

	/**
	 * Activation: seed options without overwriting existing ones.
	 *
	 * @return void
	 */
	public static function activate(): void {
		add_option( self::OPTION_NAME, self::default_settings() );
		update_option( 'rankavia_version', RANKAVIA_VERSION );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return Schema_Factory::create()->defaults();
	}
}

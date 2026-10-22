<?php
/**
 * REST routes for Rankavia's settings.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Rest;

use Bixisoft\Rankavia\Vendor\Core\Settings\Rest\Settings_Controller as Core_Settings_Controller;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;

/**
 * Routes under /rankavia/v1/settings, restricted to administrators.
 */
final class Settings_Controller extends Core_Settings_Controller {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		parent::__construct( $settings );
		$this->namespace  = 'rankavia/v1';
		$this->capability = 'manage_options';
		$this->error_code = 'rankavia_invalid_settings';
	}
}

<?php
/**
 * Removes every option and transient the plugin created.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rankavia_settings' );
delete_option( 'rankavia_version' );
delete_transient( 'rankavia_score_home' );

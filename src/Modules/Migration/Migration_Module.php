<?php
/** Native administrator preview, apply and rollback UI.
 *
 * @package Rankavia
 */

declare( strict_types=1 );
namespace Bixisoft\Rankavia\Modules\Migration;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Migration\Engine;

/** All mutations use the current user's capabilities and a WordPress nonce. */
final class Migration_Module implements Module {

	/** Return the module identifier.
	 *
	 * @return string Module identifier.
	 */
	public function id(): string {
		return 'migration'; }
	/** Register this module with the application container.
	 *
	 * @param Container $container Existing services.
	 * @return void
	 */
	public function register( Container $container ): void {
		unset( $container ); }
	/** Register this module with the application container.
	 *
	 * @param Container $container Existing services.
	 * @return void
	 */
	public function boot( Container $container ): void {
		unset( $container );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
	}
	/** Add the migration screen beneath the Rankavia menu.
	 *
	 * @return void
	 */
	public function menu(): void {
		$hook = add_submenu_page( 'rankavia', __( 'SEO migration', 'rankavia' ), __( 'SEO migration', 'rankavia' ), 'manage_options', 'rankavia-migration', array( $this, 'render' ) );
		if ( is_string( $hook ) ) {
			add_action( 'load-' . $hook, array( $this, 'authorize_request' ) );
		}
	}

	/** Validate posted actions before admin header output, so failures remain403.
	 *
	 * @return void
	 */
	public function authorize_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'rankavia' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only dispatch presence; nonce verified immediately below before any operation.
		if ( isset( $_POST['migration_action'] ) ) {
			check_admin_referer( 'rankavia_migration' );
		}
	}
	/** Read an unslashed string for validation by the migration engine.
	 *
	 * @param string $key POST field name.
	 * @return string Field value or an empty string.
	 */
	private function input( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked before reads; engine strictly validates tokens/provider/IDs.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}
	/** Render the migration interface and handle submitted actions.
	 *
	 * @return void
	 * @throws \RuntimeException When migration input is invalid.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'rankavia' ), '', array( 'response' => 403 ) );
		}
		$engine   = new Engine();
		$plan     = null;
		$error    = '';
		$message  = '';
		$provider = 'yoast';
		$ids      = '';
		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only dispatch presence is read before nonce verification.
			if ( isset( $_POST['migration_action'] ) ) {
				if ( false === check_admin_referer( 'rankavia_migration' ) ) {
					return;
				}
				$action   = $this->input( 'migration_action' );
				$provider = $this->input( 'provider' );
				if ( 'preview' === $action ) {
					Engine::prefix( $provider );
					$ids = trim( $this->input( 'post_ids' ) );
					if ( strlen( $ids ) > 512 || ( '' !== $ids && ! preg_match( '/\A[0-9,\s]+\z/D', $ids ) ) ) {
						throw new \RuntimeException( 'Enter at most twenty comma-separated post/page IDs.' );
					}
					$list = '' === $ids ? $engine->discover( $provider ) : array_map( 'intval', preg_split( '/[\s,]+/', $ids, -1, PREG_SPLIT_NO_EMPTY ) );
					$ids  = implode( ', ', $list );
					$plan = $engine->preview( $provider, $list );
				} elseif ( 'apply' === $action ) {
					$message = 'Applied. Keep this rollback receipt: ' . $engine->apply( $this->input( 'preview_token' ) );
				} elseif ( 'rollback' === $action ) {
					$engine->rollback( $this->input( 'receipt' ) );
					$message = 'Migration rolled back. Source plugin data is unchanged.';
				} else {
					throw new \RuntimeException( 'Unknown migration action.' );
				}
			}
		} catch ( \RuntimeException $failure ) {
			$error = $failure->getMessage();
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Yoast and Rank Math migration', 'rankavia' ) . '</h1>';
		echo '<p>' . esc_html__( 'Preview supported post/page metadata, then explicitly apply. Existing Rankavia fields and all source data are preserved. Each batch supports up to 20 posts/pages. Back up your database first.', 'rankavia' ) . '</p>';
		echo '<p>' . esc_html__( 'This does not migrate global settings, taxonomy metadata, redirects, custom schema, scores, advanced robots or remote images. Unsupported fields and template variables are skipped with warnings. Source plugins are not deactivated; review duplicate output before switching plugins.', 'rankavia' ) . '</p>';
		if ( '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
		if ( '' !== $message ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>'; }
		echo '<form method="post">';
		wp_nonce_field( 'rankavia_migration' );
		echo '<input type="hidden" name="migration_action" value="preview" /><p><label>' . esc_html__( 'Source', 'rankavia' ) . ' <select name="provider"><option value="yoast"' . selected( $provider, 'yoast', false ) . '>Yoast SEO</option><option value="rankmath"' . selected( $provider, 'rankmath', false ) . '>Rank Math</option></select></label></p>';
		echo '<p><label>' . esc_html__( 'Post/page IDs (blank discovers the first 20 source posts; enter later IDs for subsequent batches)', 'rankavia' ) . '<br /><input name="post_ids" type="text" class="large-text" maxlength="512" value="' . esc_attr( $ids ) . '" /></label></p>';
		submit_button( __( 'Preview without changing content', 'rankavia' ) );
		echo '</form>';
		if ( is_array( $plan ) ) {
			echo '<h2>' . esc_html__( 'Preview: only missing destination fields will be inserted', 'rankavia' ) . '</h2><table class="widefat striped"><thead><tr><th>Post ID</th><th>Destination field</th><th>New value</th></tr></thead><tbody>';
			foreach ( $plan['changes'] as $change ) {
				echo '<tr><td>' . esc_html( (string) $change['post_id'] ) . '</td><td>' . esc_html( $change['key'] ) . '</td><td style="overflow-wrap:anywhere">' . esc_html( $change['value'] ) . '</td></tr>'; }
			echo '</tbody></table><ul>';
			foreach ( $plan['warnings'] as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>'; }
			echo '</ul>';
			if ( array() !== $plan['changes'] ) {
				echo '<form method="post">';
				wp_nonce_field( 'rankavia_migration' );
				echo '<input type="hidden" name="migration_action" value="apply" /><input type="hidden" name="preview_token" value="' . esc_attr( $plan['token'] ) . '" />';
				submit_button( __( 'Apply this exact preview', 'rankavia' ) );
				echo '</form>';
			}
		}
		echo '<h2>' . esc_html__( 'Rollback receipts', 'rankavia' ) . '</h2><p>' . esc_html__( 'Rollback removes only the values inserted by that batch. If any imported field was edited, removed or duplicated afterward, rollback stops without changing any fields. Retain your database backup. At most 50 receipts are retained; no automatic deletion.', 'rankavia' ) . '</p>';
		foreach ( $engine->receipts() as $receipt ) {
			echo '<p><code>' . esc_html( $receipt['id'] ) . '</code> ' . esc_html( $receipt['provider'] . ' — ' . $receipt['status'] . ' — ' . $receipt['count'] . ' fields' ) . '</p>';
			if ( 'applied' === $receipt['status'] ) {
				echo '<form method="post">';
				wp_nonce_field( 'rankavia_migration' );
				echo '<input type="hidden" name="migration_action" value="rollback" /><input type="hidden" name="receipt" value="' . esc_attr( $receipt['id'] ) . '" />';
				submit_button( __( 'Roll back this batch', 'rankavia' ), 'secondary' );
				echo '</form>';
			}
		}
		echo '</div>';
	}
}

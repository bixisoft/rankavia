<?php
/**
 * Native Rankavia redirect manager and front request handler.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Redirects;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Redirects\Option_Store;
use Bixisoft\Rankavia\Redirects\Rules;

/** A separate operational option keeps redirect rules outside the strict settings schema. */
class Redirects_Module implements Module {
	public const OPTION = 'rankavia_redirect_rules';
	public const SLUG   = 'rankavia-redirects';

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
	 * Register admin and front hooks.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		unset( $container );
		// Register after the Rankavia parent menu so WordPress retains its dashboard link.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_rankavia_redirects', array( $this, 'save' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/** Add a native submenu under the existing Rankavia parent. @return void */
	public function menu(): void {
		add_submenu_page( 'rankavia', __( 'Redirects', 'rankavia' ), __( 'Redirects', 'rankavia' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
	}

	/** Render the management form and the currently stored rules. @return void */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage redirects.', 'rankavia' ) );
		}
		$rules = Rules::stored( get_option( self::OPTION, array() ), (string) home_url( '/' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Redirects', 'rankavia' ) . '</h1>';
		if ( null === $rules ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Stored redirect rules are invalid. No redirects will run until they are repaired.', 'rankavia' ) . '</p></div></div>';
			return;
		}
		echo '<p>' . esc_html__( 'Exact paths only. Destinations must stay on this site. Redirects are skipped for logged-in visitors, requests with query strings, and protected WordPress or commerce paths.', 'rankavia' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice code after the admin-post redirect.
		$notice = isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		if ( '' !== $notice ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $this->notice( $notice ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'rankavia_redirects' );
		echo '<input type="hidden" name="action" value="rankavia_redirects"><input type="hidden" name="revision" value="' . esc_attr( $this->revision( $rules ) ) . '">';
		echo '<input type="hidden" name="operation" value="add"><table class="form-table"><tbody>';
		echo '<tr><th><label for="rankavia-source">' . esc_html__( 'Source path', 'rankavia' ) . '</label></th><td><input id="rankavia-source" name="source" type="text" maxlength="200" required placeholder="/old-page/"></td></tr>';
		echo '<tr><th><label for="rankavia-target">' . esc_html__( 'Destination', 'rankavia' ) . '</label></th><td><input id="rankavia-target" name="target" type="text" maxlength="2048" placeholder="/new-page/"><p class="description">' . esc_html__( 'Leave empty for 410 Gone.', 'rankavia' ) . '</p></td></tr>';
		echo '<tr><th><label for="rankavia-status">' . esc_html__( 'Status', 'rankavia' ) . '</label></th><td><select id="rankavia-status" name="status"><option value="301">301</option><option value="302">302</option><option value="410">410</option></select></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add rule', 'rankavia' ) );
		echo '</form><h2>' . esc_html__( 'Current rules', 'rankavia' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'rankavia' ) . '</th><th>' . esc_html__( 'Destination', 'rankavia' ) . '</th><th>' . esc_html__( 'Status', 'rankavia' ) . '</th><th>' . esc_html__( 'Action', 'rankavia' ) . '</th></tr></thead><tbody>';
		foreach ( $rules as $rule ) {
			echo '<tr><td>' . esc_html( $rule['source'] ) . '</td><td>' . esc_html( $rule['target'] ) . '</td><td>' . esc_html( (string) $rule['status'] ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'rankavia_redirects' );
			echo '<input type="hidden" name="action" value="rankavia_redirects"><input type="hidden" name="operation" value="delete"><input type="hidden" name="revision" value="' . esc_attr( $this->revision( $rules ) ) . '"><input type="hidden" name="source" value="' . esc_attr( $rule['source'] ) . '">';
			submit_button( __( 'Delete', 'rankavia' ), 'delete small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** Process a nonce-protected add or delete, then return to the admin page. @return void */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage redirects.', 'rankavia' ) );
		}
		check_admin_referer( 'rankavia_redirects' );
		$snapshot = Option_Store::read();
		$rules    = Rules::stored( $snapshot['rules'], (string) home_url( '/' ) );
		if ( null === $rules ) {
			$this->return_to_page( 'invalid' );
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact digest comparison after nonce verification.
		$revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? wp_unslash( $_POST['revision'] ) : '';
		if ( ! hash_equals( $this->revision( $rules ), $revision ) ) {
			$this->return_to_page( 'stale' );
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rules::create validates the exact unslashed source.
		$source = isset( $_POST['source'] ) && is_string( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared only to fixed operation names.
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : '';
		if ( 'add' === $operation ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rules::create validates the exact destination.
			$target = isset( $_POST['target'] ) && is_string( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '';
			$status = isset( $_POST['status'] ) && is_scalar( $_POST['status'] ) ? (int) $_POST['status'] : 0;
			$rule   = Rules::create( $source, $target, $status, (string) home_url( '/' ), $rules );
			if ( null === $rule ) {
				$this->return_to_page( 'invalid' );
				return;
			}
			$rules[] = $rule;
		} elseif ( 'delete' === $operation ) {
			$rules = array_values(
				array_filter(
					$rules,
					static function ( array $rule ) use ( $source ): bool {
						return $rule['source'] !== $source;
					}
				)
			);
		} else {
			$this->return_to_page( 'invalid' );
			return;
		}
		$this->return_to_page( Option_Store::compare_and_swap( $snapshot['raw'], $rules ) ? 'saved' : 'stale' );
	}

	/** Apply one exact stored rule for an eligible front request. @return void */
	public function maybe_redirect(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Server method is compared to GET/HEAD only.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact server URI is checked by Rules::safe_path; normalization would change matching.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || is_admin() || is_user_logged_in() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || '' === $uri || false !== strpos( $uri, '?' ) ) {
			return;
		}
		$home = (string) home_url( '/' );
		if ( ! Rules::safe_path( $uri, $home ) ) {
			return;
		}
		$rules = Rules::stored( get_option( self::OPTION, array() ), $home );
		if ( null === $rules ) {
			return;
		}
		foreach ( $rules as $rule ) {
			if ( $uri !== $rule['source'] ) {
				continue;
			}
			if ( 410 === $rule['status'] ) {
				status_header( 410 );
				nocache_headers();
			} else {
				wp_safe_redirect( $rule['target'], $rule['status'] );
			}
			$this->terminate();
			return;
		}
	}

	/**
	 * Hash the displayed rules to detect stale forms.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return string
	 */
	private function revision( array $rules ): string {
		return hash( 'sha256', (string) wp_json_encode( $rules ) );
	}

	/**
	 * Return a safe status message.
	 *
	 * @param string $code Notice code.
	 * @return string
	 */
	private function notice( string $code ): string {
		$messages = array(
			'saved'   => __( 'Redirect rules saved.', 'rankavia' ),
			'stale'   => __( 'Rules changed in another tab. Reload and try again.', 'rankavia' ),
			'invalid' => __( 'The rule is invalid or the stored rules need repair.', 'rankavia' ),
		);
		return $messages[ $code ] ?? '';
	}

	/**
	 * Finish a POST through the admin page.
	 *
	 * @param string $notice Notice code.
	 * @return void
	 */
	private function return_to_page( string $notice ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&notice=' . $notice ) );
		$this->terminate();
	}

	/** Exit after sending the response; override in tests. @return void */
	protected function terminate(): void {
		exit;
	}
}

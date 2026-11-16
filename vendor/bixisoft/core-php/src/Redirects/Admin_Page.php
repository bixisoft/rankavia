<?php
/**
 * Shared admin screen for redirects and the 404 log.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * Renders the Redirects and "Not found" tabs and handles their form posts. Every visible string is
 * passed in by the package that shows the page, so each keeps its own text domain.
 */
class Admin_Page {
	/** Action name for admin-post.php. */
	public const ACTION = 'rankavia_redirects';

	/** Nonce action. */
	public const NONCE = 'rankavia_redirects';

	/** Rows per page. */
	public const PER_PAGE = 50;

	/** Seconds an undo stays available. */
	public const UNDO_TTL = 900;

	/**
	 * Redirect runtime.
	 *
	 * @var Service
	 */
	protected Service $service;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Translated labels, see label().
	 *
	 * @var array<string,string>
	 */
	protected array $labels;

	/**
	 * Called before and after the page body (e.g. to draw a shared header); receives the page title.
	 *
	 * @var array{open:callable(string):void,close:callable():void}|null
	 */
	protected ?array $frame;

	/**
	 * Build the page.
	 *
	 * @param Service                                                      $service Redirect runtime.
	 * @param string                                                       $slug    Admin page slug.
	 * @param array<string,string>                                         $labels  Translated labels.
	 * @param array{open:callable(string):void,close:callable():void}|null $frame   Optional surrounding frame.
	 */
	public function __construct( Service $service, string $slug, array $labels, ?array $frame = null ) {
		$this->service = $service;
		$this->slug    = $slug;
		$this->labels  = $labels;
		$this->frame   = $frame;
	}

	/**
	 * Add the form handlers. Call from the owner's boot.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Admin URL of this page.
	 *
	 * @param array<string,string|int> $args Extra query arguments.
	 * @return string
	 */
	public function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $this->slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Draw the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( $this->label( 'forbidden' ) ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters.
		$tab    = isset( $_GET['tab'] ) && 'not-found' === $_GET['tab'] ? 'not-found' : 'redirects';
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$notice = isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$title = $this->label( 'title' );
		if ( null !== $this->frame ) {
			( $this->frame['open'] )( $title );
		} else {
			echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
		}
		$this->notices( $notice );
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr( $title ) . '">';
		foreach ( array(
			'redirects' => $this->label( 'tab_redirects' ),
			'not-found' => $this->label( 'tab_not_found' ),
		) as $id => $name ) {
			$current = $id === $tab;
			echo '<a class="nav-tab' . ( $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $this->url( array( 'tab' => $id ) ) ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $name ) . '</a>';
		}
		echo '</nav>';
		if ( 'not-found' === $tab ) {
			$this->render_not_found( $paged );
		} else {
			$this->render_redirects( $paged, $search );
		}
		if ( null !== $this->frame ) {
			( $this->frame['close'] )();
		} else {
			echo '</div>';
		}
	}

	/**
	 * Handle a form post, then return to the page.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( $this->label( 'forbidden' ) ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
		$operation = $this->field( 'operation' );
		$tab       = 'redirects';
		switch ( $operation ) {
			case 'add':
				$notice = $this->add( $this->field( 'source' ), $this->field( 'target' ), (int) $this->field( 'status' ), 'manual' );
				break;
			case 'redirect-404':
				$source = $this->field( 'source' );
				$notice = $this->add( $source, $this->field( 'target' ), (int) $this->field( 'status' ), 'not_found' );
				if ( 'added' === $notice ) {
					$this->service->log()->forget( $source );
				}
				$tab = 'not-found';
				break;
			case 'delete':
				$notice = $this->delete( $this->field( 'source' ), $this->field( 'expected' ) );
				break;
			case 'undo':
				$notice = $this->undo();
				break;
			case 'forget-404':
				$this->service->log()->forget( $this->field( 'source' ) );
				$notice = 'forgotten';
				$tab    = 'not-found';
				break;
			case 'clear-404':
				$this->service->log()->clear();
				$notice = 'cleared';
				$tab    = 'not-found';
				break;
			case 'export':
				$this->export();
				return;
			default:
				$notice = 'invalid';
		}
		wp_safe_redirect(
			$this->url(
				array(
					'tab'    => $tab,
					'notice' => $notice,
				)
			)
		);
		$this->terminate();
	}

	/**
	 * Validate and store a rule; remember it for undo.
	 *
	 * @param string $source Source path.
	 * @param string $target Destination.
	 * @param int    $status HTTP status.
	 * @param string $origin Store origin.
	 * @return string Notice code.
	 */
	protected function add( string $source, string $target, int $status, string $origin ): string {
		if ( null !== $this->service->store()->find( $source ) ) {
			return 'exists';
		}
		$rule = $this->service->validate( $source, $target, $status );
		if ( null === $rule ) {
			return 'invalid';
		}
		if ( ! $this->service->store()->add( $rule, $origin ) ) {
			return 'exists';
		}
		$this->remember( 'added', $rule, $origin );
		return 'added';
	}

	/**
	 * Delete a rule only if it still is the one the user saw.
	 *
	 * @param string $source   Source path.
	 * @param string $expected Fingerprint of the row as displayed.
	 * @return string Notice code.
	 */
	protected function delete( string $source, string $expected ): string {
		$row = $this->service->store()->row( $source );
		if ( null === $row || ! hash_equals( self::fingerprint( $row ), $expected ) ) {
			return 'stale';
		}
		$deleted = $this->service->store()->delete( $source );
		if ( null === $deleted ) {
			return 'stale';
		}
		$this->remember(
			'deleted',
			array(
				'source' => $deleted['source'],
				'target' => $deleted['target'],
				'status' => $deleted['status'],
			),
			$deleted['origin']
		);
		return 'deleted';
	}

	/**
	 * Reverse the current user's last add or delete.
	 *
	 * @return string Notice code.
	 */
	protected function undo(): string {
		$key  = $this->undo_key();
		$last = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $last ) || ! isset( $last['type'], $last['rule'], $last['origin'] ) || ! is_array( $last['rule'] ) ) {
			return 'undo-expired';
		}
		$rule = $last['rule'];
		if ( 'added' === $last['type'] ) {
			$row = $this->service->store()->row( (string) $rule['source'] );
			if ( null === $row || $row['target'] !== $rule['target'] || $row['status'] !== (int) $rule['status'] ) {
				return 'stale';
			}
			$this->service->store()->delete( (string) $rule['source'] );
			return 'undone';
		}
		$again = $this->service->validate( (string) $rule['source'], (string) $rule['target'], (int) $rule['status'] );
		if ( null === $again || ! $this->service->store()->add( $again, (string) $last['origin'] ) ) {
			return 'stale';
		}
		return 'undone';
	}

	/**
	 * Send every rule as CSV.
	 *
	 * @return void
	 */
	protected function export(): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			$this->terminate();
			return;
		}
		fputcsv( $out, array( 'source', 'target', 'status', 'origin', 'created_at', 'last_used' ) );
		$store = $this->service->store();
		$pages = (int) ceil( $store->count() / 200 );
		for ( $page = 1; $page <= $pages; ++$page ) {
			foreach ( $store->page( $page, 200 ) as $row ) {
				fputcsv( $out, array( self::csv_cell( $row['source'] ), self::csv_cell( $row['target'] ), $row['status'], $row['origin'], $row['created_at'], (string) $row['last_used'] ) );
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
		$this->terminate();
	}

	/**
	 * Redirects tab.
	 *
	 * @param int    $paged  Page number.
	 * @param string $search Search text.
	 * @return void
	 */
	protected function render_redirects( int $paged, string $search ): void {
		$store = $this->service->store();
		echo '<p>' . esc_html( $this->label( 'intro' ) ) . '</p>';
		echo '<h2>' . esc_html( $this->label( 'add_heading' ) ) . '</h2>';
		$this->rule_form( 'add', '', 'rankavia-source' );
		$total = $store->count( $search );
		echo '<h2>' . esc_html( $this->label( 'rules_heading' ) ) . ' <span class="count">(' . esc_html( number_format_i18n( $total ) ) . ')</span></h2>';
		echo '<form method="get" class="search-form"><input type="hidden" name="page" value="' . esc_attr( $this->slug ) . '">';
		echo '<label class="screen-reader-text" for="rankavia-redirect-search">' . esc_html( $this->label( 'search' ) ) . '</label>';
		echo '<input type="search" id="rankavia-redirect-search" name="s" value="' . esc_attr( $search ) . '"> ';
		submit_button( $this->label( 'search' ), 'secondary', '', false );
		echo '</form>';
		$rows = $store->page( $paged, self::PER_PAGE, $search );
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html( $this->label( 'col_source' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_target' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_status' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_origin' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_used' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_action' ) ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><code>' . esc_html( $row['source'] ) . '</code></td><td>' . ( '' === $row['target'] ? '&mdash;' : '<code>' . esc_html( $row['target'] ) . '</code>' ) . '</td><td>' . esc_html( (string) $row['status'] ) . '</td><td>' . esc_html( $this->label( 'origin_' . $row['origin'] ) ) . '</td><td>' . esc_html( null === $row['last_used'] ? $this->label( 'never' ) : $row['last_used'] . ' UTC' ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'delete' );
			echo '<input type="hidden" name="source" value="' . esc_attr( $row['source'] ) . '"><input type="hidden" name="expected" value="' . esc_attr( self::fingerprint( $row ) ) . '">';
			submit_button( $this->label( 'delete' ), 'delete small', 'submit', false, array( 'aria-label' => $this->label( 'delete' ) . ' ' . $row['source'] ) );
			echo '</form></td></tr>';
		}
		if ( array() === $rows ) {
			echo '<tr><td colspan="6">' . esc_html( '' === $search ? $this->label( 'empty' ) : $this->label( 'no_match' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->pagination( $total, $paged, array( 's' => $search ) );
		if ( $total > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'export' );
			submit_button( $this->label( 'export' ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	/**
	 * "Not found" tab.
	 *
	 * @param int $paged Page number.
	 * @return void
	 */
	protected function render_not_found( int $paged ): void {
		$log  = $this->service->log();
		$rows = $log->page( $paged, self::PER_PAGE );
		echo '<p>' . esc_html( $this->label( 'log_intro' ) ) . '</p>';
		$dropped = $log->dropped();
		if ( $dropped > 0 ) {
			/* The label carries one %s for the number. */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( $this->label( 'log_dropped' ), number_format_i18n( $dropped ) ) ) . '</p></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html( $this->label( 'col_path' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_hits' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_last_seen' ) ) . '</th><th scope="col">' . esc_html( $this->label( 'col_fix' ) ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $index => $row ) {
			echo '<tr><td><code>' . esc_html( $row['path'] ) . '</code></td><td>' . esc_html( number_format_i18n( $row['hits'] ) ) . '</td><td>' . esc_html( $row['last_seen'] . ' UTC' ) . '</td><td>';
			$this->rule_form( 'redirect-404', $row['path'], 'rankavia-404-' . (int) $index );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'forget-404' );
			echo '<input type="hidden" name="source" value="' . esc_attr( $row['path'] ) . '">';
			submit_button( $this->label( 'forget' ), 'link small', 'submit', false, array( 'aria-label' => $this->label( 'forget' ) . ' ' . $row['path'] ) );
			echo '</form></td></tr>';
		}
		if ( array() === $rows ) {
			echo '<tr><td colspan="4">' . esc_html( $this->label( 'log_empty' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->pagination( $log->rows(), $paged, array( 'tab' => 'not-found' ) );
		if ( array() !== $rows ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'clear-404' );
			submit_button( $this->label( 'clear' ), 'delete', 'submit', false );
			echo '</form>';
		}
	}

	/**
	 * Add-rule form; with a fixed source for a 404 row.
	 *
	 * @param string $operation 'add' or 'redirect-404'.
	 * @param string $source    Fixed source path, '' to ask.
	 * @param string $id        Unique id prefix for labels.
	 * @return void
	 */
	protected function rule_form( string $operation, string $source, string $id ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rankavia-redirect-form">';
		$this->hidden( $operation );
		if ( '' === $source ) {
			echo '<p><label for="' . esc_attr( $id ) . '-source">' . esc_html( $this->label( 'source' ) ) . '</label><br><input id="' . esc_attr( $id ) . '-source" name="source" type="text" class="regular-text" maxlength="' . esc_attr( (string) Rules::SOURCE_MAX ) . '" required placeholder="/old-page/"></p>';
		} else {
			echo '<input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
		}
		// The full form explains the destination; the compact form in a 404 row keeps only the field.
		$help = '' === $source ? ' <span class="description">' . esc_html( $this->label( 'target_help' ) ) . '</span>' : '';
		echo '<p><label for="' . esc_attr( $id ) . '-target">' . esc_html( $this->label( 'target' ) ) . '</label><br><input id="' . esc_attr( $id ) . '-target" name="target" type="text" class="' . ( '' === $source ? 'regular-text' : 'medium-text' ) . '" maxlength="' . esc_attr( (string) Rules::TARGET_MAX ) . '" placeholder="/new-page/">' . $help . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help is escaped above.
		echo '<p><label for="' . esc_attr( $id ) . '-status">' . esc_html( $this->label( 'status' ) ) . '</label> <select id="' . esc_attr( $id ) . '-status" name="status">';
		foreach ( array(
			301 => $this->label( 'status_301' ),
			302 => $this->label( 'status_302' ),
			410 => $this->label( 'status_410' ),
		) as $code => $name ) {
			echo '<option value="' . esc_attr( (string) $code ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '</select> ';
		submit_button( '' === $source ? $this->label( 'add' ) : $this->label( 'redirect' ), '' === $source ? 'primary' : 'secondary small', 'submit', false );
		echo '</p></form>';
	}

	/**
	 * Status message after a post.
	 *
	 * @param string $code Notice code.
	 * @return void
	 */
	protected function notices( string $code ): void {
		$migration = get_option( Schema::MIGRATION_OPTION );
		if ( 'invalid' === $migration || 'incomplete' === $migration ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $this->label( 'migration_failed' ) ) . '</p></div>';
		}
		if ( '' === $code ) {
			return;
		}
		$text = $this->label( 'notice_' . str_replace( '-', '_', $code ) );
		if ( '' === $text ) {
			return;
		}
		$error = in_array( $code, array( 'invalid', 'exists', 'stale', 'undo-expired' ), true );
		echo '<div class="notice ' . ( $error ? 'notice-error' : 'notice-success' ) . ' inline"><p>' . esc_html( $text );
		if ( in_array( $code, array( 'added', 'deleted' ), true ) && false !== get_transient( $this->undo_key() ) ) {
			echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'undo' );
			submit_button( $this->label( 'undo' ), 'secondary small', 'submit', false );
			echo '</form><p>';
		}
		echo '</p></div>';
	}

	/**
	 * Previous/next links.
	 *
	 * @param int                      $total Rows.
	 * @param int                      $paged Current page.
	 * @param array<string,string|int> $args  Arguments to keep.
	 * @return void
	 */
	protected function pagination( int $total, int $paged, array $args ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages < 2 ) {
			return;
		}
		echo '<p class="tablenav-pages">';
		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url( $this->url( $args + array( 'paged' => $paged - 1 ) ) ) . '">' . esc_html( $this->label( 'previous' ) ) . '</a> ';
		}
		/* The label carries %1$s for the page and %2$s for the total. */
		echo esc_html( sprintf( $this->label( 'page_of' ), number_format_i18n( $paged ), number_format_i18n( $pages ) ) );
		if ( $paged < $pages ) {
			echo ' <a class="button" href="' . esc_url( $this->url( $args + array( 'paged' => $paged + 1 ) ) ) . '">' . esc_html( $this->label( 'next' ) ) . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Nonce and action fields.
	 *
	 * @param string $operation Operation name.
	 * @return void
	 */
	protected function hidden( string $operation ): void {
		// Written by hand: wp_nonce_field() repeats id="_wpnonce" in every row form.
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
	}

	/**
	 * A posted string, unslashed and not otherwise changed (Rules validates paths exactly).
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	protected function field( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in handle(); values are validated by Rules or compared exactly.
		return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? trim( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Store the last change for undo.
	 *
	 * @param string                                        $type   'added' or 'deleted'.
	 * @param array{source:string,target:string,status:int} $rule   Rule.
	 * @param string                                        $origin Store origin.
	 * @return void
	 */
	protected function remember( string $type, array $rule, string $origin ): void {
		set_transient(
			$this->undo_key(),
			array(
				'type'   => $type,
				'rule'   => $rule,
				'origin' => $origin,
			),
			self::UNDO_TTL
		);
	}

	/**
	 * Per-user undo key.
	 *
	 * @return string
	 */
	protected function undo_key(): string {
		return 'rankavia_redirects_undo_' . get_current_user_id();
	}

	/**
	 * A label, or '' when the package did not supply it.
	 *
	 * @param string $key Label key.
	 * @return string
	 */
	protected function label( string $key ): string {
		return $this->labels[ $key ] ?? '';
	}

	/**
	 * Identity of a displayed row, so a delete only removes what the user saw.
	 *
	 * @param array{id:int,source:string,target:string,status:int} $row Stored row.
	 * @return string
	 */
	public static function fingerprint( array $row ): string {
		return hash( 'sha256', $row['id'] . '|' . $row['source'] . '|' . $row['target'] . '|' . $row['status'] );
	}

	/**
	 * Keep spreadsheet programs from running a cell as a formula.
	 *
	 * @param string $value Cell text.
	 * @return string
	 */
	public static function csv_cell( string $value ): string {
		return '' !== $value && false !== strpos( '=+-@', $value[0] ) ? "'" . $value : $value;
	}

	/**
	 * Stop after sending a response; overridden in tests.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}
}

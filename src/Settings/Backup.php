<?php
/**
 * Export / import of the plugin settings as a JSON file.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the two admin-post actions behind the "Backup" card on
 * Settings → NanoBar: downloading the current settings, and restoring them
 * from a previously exported file. Imported data never bypasses validation:
 * it goes through the same Sanitizer as a normal save.
 */
final class Backup {

	/**
	 * Export action name (admin-post hook suffix and nonce action).
	 */
	public const EXPORT_ACTION = 'nanobar_export';

	/**
	 * Import action name (admin-post hook suffix and nonce action).
	 */
	public const IMPORT_ACTION = 'nanobar_import';

	/**
	 * Query argument carrying the import result back to the settings page.
	 */
	public const RESULT_ARG = 'nanobar_backup';

	/**
	 * Largest accepted import file, in bytes. A real export is a few KB.
	 */
	private const MAX_BYTES = 262144;

	/**
	 * Registers the admin-post handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
	}

	/**
	 * Sends the current settings as a downloadable JSON file.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nanobar' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::EXPORT_ACTION );

		$payload = array(
			'plugin'  => 'nanobar',
			'version' => NANOBAR_VERSION,
			'options' => Options::get(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nanobar-settings-' . gmdate( 'Y-m-d' ) . '.json"' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Restores the settings from an uploaded export, then returns to the page.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nanobar' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::IMPORT_ACTION );

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $this->import_uploaded_file(), admin_url( 'options-general.php?page=nanobar' ) ) );
		exit;
	}

	/**
	 * Reads, validates and stores the uploaded file.
	 *
	 * @return string Result code: imported | nofile | toolarge | invalid.
	 */
	private function import_uploaded_file(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by check_admin_referer() in handle_import(); only tmp_name, size and error are read, and the contents are validated and sanitized below.
		$file = isset( $_FILES['nanobar_import_file'] ) && is_array( $_FILES['nanobar_import_file'] ) ? wp_unslash( $_FILES['nanobar_import_file'] ) : array();

		$tmp_name = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$size     = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		$error    = isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return 'toolarge';
		}
		if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return 'nofile';
		}
		if ( $size > self::MAX_BYTES ) {
			return 'toolarge';
		}

		$contents = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading an uploaded temp file, not a remote URL.
		if ( false === $contents ) {
			return 'invalid';
		}

		$options = self::extract_options( $contents );
		if ( null === $options ) {
			return 'invalid';
		}

		// A file missing some keys must not switch those off (the sanitizer reads
		// an absent checkbox as "unchecked"), so start from the defaults.
		$options = array_merge( Options::get_defaults(), $options );

		// update_option() runs the registered sanitize callback, but sanitizing
		// explicitly keeps this path correct even if that registration changes.
		update_option( Options::OPTION_NAME, Sanitizer::sanitize( $options ) );

		return 'imported';
	}

	/**
	 * Pulls the options array out of an export file's contents.
	 *
	 * @param string $json Raw file contents.
	 * @return array<string, mixed>|null Null when the file is not a NanoBar export.
	 */
	public static function extract_options( string $json ): ?array {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || 'nanobar' !== ( $data['plugin'] ?? null ) || ! isset( $data['options'] ) || ! is_array( $data['options'] ) ) {
			return null;
		}

		return $data['options'];
	}

	/**
	 * Prints the two Backup cards (Export | Import). They sit after the
	 * settings form, not inside it (forms cannot nest); the import card has
	 * its own admin-post form, enhanced by admin-backup.js (drop zone, file
	 * summary, inline confirmation) but fully working without it.
	 */
	public static function render_card(): void {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION );
		?>
		<div class="nanobar-bento-pair nanobar-backup-pair" id="nanobar-section-backup">
			<div class="nanobar-bento-card nanobar-backup" id="nanobar-section-export">
				<div class="nanobar-bento-card__header">
					<span class="dashicons dashicons-database-export" aria-hidden="true"></span>
					<h2><?php esc_html_e( 'Export', 'nanobar' ); ?></h2>
				</div>
				<div class="nanobar-bento-card__body nanobar-backup__body">
					<p class="nanobar-help"><?php esc_html_e( 'Download your saved settings as a JSON file, to keep a copy or to reuse them on another site.', 'nanobar' ); ?></p>
					<ul class="nanobar-backup__list">
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'General options, enabled roles and appearance', 'nanobar' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Panel items, including which show on phones', 'nanobar' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Your quick links', 'nanobar' ); ?></li>
					</ul>
					<div class="nanobar-backup__actions">
						<a class="nanobar-btn nanobar-btn--primary" href="<?php echo esc_url( $export_url ); ?>">
							<span class="dashicons dashicons-download" aria-hidden="true"></span>
							<?php esc_html_e( 'Download settings', 'nanobar' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="nanobar-bento-card nanobar-backup" id="nanobar-section-import">
				<div class="nanobar-bento-card__header">
					<span class="dashicons dashicons-database-import" aria-hidden="true"></span>
					<h2><?php esc_html_e( 'Import', 'nanobar' ); ?></h2>
				</div>
				<form class="nanobar-bento-card__body nanobar-backup__body nanobar-backup__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-backup-form>
					<p class="nanobar-help"><?php esc_html_e( 'Restore settings from a NanoBar export. This replaces the current saved settings; the file is validated exactly like a normal save.', 'nanobar' ); ?></p>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
					<?php wp_nonce_field( self::IMPORT_ACTION ); ?>

					<div class="nanobar-dropzone" data-dropzone>
						<input type="file" id="nanobar-import-file" class="nanobar-dropzone__input" name="nanobar_import_file" accept="application/json,.json" required />
						<label class="nanobar-dropzone__prompt" for="nanobar-import-file">
							<span class="dashicons dashicons-upload nanobar-dropzone__icon" aria-hidden="true"></span>
							<span class="nanobar-dropzone__title"><?php esc_html_e( 'Drop your settings file here', 'nanobar' ); ?></span>
							<span class="nanobar-dropzone__hint">
								<?php esc_html_e( 'or click to browse', 'nanobar' ); ?>
								&middot;
								<?php esc_html_e( '.json, up to 256 KB', 'nanobar' ); ?>
							</span>
						</label>
						<div class="nanobar-dropzone__file" data-dropzone-file hidden>
							<span class="dashicons dashicons-media-code nanobar-dropzone__file-icon" aria-hidden="true"></span>
							<span class="nanobar-dropzone__file-info">
								<span class="nanobar-dropzone__file-name" data-dropzone-name></span>
								<span class="nanobar-dropzone__file-meta" data-dropzone-meta></span>
							</span>
							<button type="button" class="nanobar-dropzone__remove" data-dropzone-remove title="<?php esc_attr_e( 'Remove file', 'nanobar' ); ?>">
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Remove file', 'nanobar' ); ?></span>
							</button>
						</div>
					</div>
					<p class="nanobar-backup__summary" role="status" data-backup-summary></p>

					<div class="nanobar-backup__actions">
						<button type="submit" class="nanobar-btn nanobar-btn--primary" data-backup-import>
							<span class="dashicons dashicons-upload" aria-hidden="true"></span>
							<?php esc_html_e( 'Import settings', 'nanobar' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Prints the result notice of a just-completed import, if the query
	 * string carries a known result code (the text is fixed, never taken
	 * from the request).
	 */
	public static function render_result_notice(): void {
				// Result of an import (redirected back by Settings\Backup). Only
				// known codes print anything; the message text is fixed, never
				// taken from the query string.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a result code.
				$backup_code    = isset( $_GET[ self::RESULT_ARG ] ) && is_string( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';
				$backup_message = '' !== $backup_code ? self::get_result_message( $backup_code ) : null;
		if ( null !== $backup_message ) :
			?>
					<div class="notice notice-<?php echo esc_attr( $backup_message[1] ); ?> is-dismissible"><p><?php echo esc_html( $backup_message[0] ); ?></p></div>
				<?php endif; ?>
		<?php
	}

	/**
	 * Human-readable message and notice type for a result code, or null when
	 * the code is unknown (so a crafted query string prints nothing).
	 *
	 * @param string $code Result code from the query string.
	 * @return array{0: string, 1: string}|null Message and notice class (success|error).
	 */
	public static function get_result_message( string $code ): ?array {
		$messages = array(
			'imported' => array( __( 'Settings imported.', 'nanobar' ), 'success' ),
			'nofile'   => array( __( 'No file was uploaded. Choose a NanoBar settings file (.json) first.', 'nanobar' ), 'error' ),
			'toolarge' => array( __( 'The file is too large to be a NanoBar settings export.', 'nanobar' ), 'error' ),
			'invalid'  => array( __( 'That file is not a valid NanoBar settings export.', 'nanobar' ), 'error' ),
		);

		return $messages[ $code ] ?? null;
	}
}

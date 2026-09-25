<?php
/**
 * Tests for NanoBar\Settings\Backup (the pure parts).
 *
 * @package NanoBar
 */

declare( strict_types=1 );

use NanoBar\Settings\Backup;
use NanoBar\Settings\Options;
use NanoBar\Settings\Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Export file parsing and result messages.
 */
final class BackupTest extends TestCase {

	/**
	 * A valid export yields its options array.
	 */
	public function test_extracts_options_from_valid_export(): void {
		$json = wp_json_encode(
			array(
				'plugin'  => 'nanobar',
				'version' => '2.0.0',
				'options' => array( 'toggle_size' => 50 ),
			)
		);

		$this->assertSame( array( 'toggle_size' => 50 ), Backup::extract_options( (string) $json ) );
	}

	/**
	 * Anything else is rejected.
	 */
	public function test_rejects_invalid_files(): void {
		$this->assertNull( Backup::extract_options( '' ) );
		$this->assertNull( Backup::extract_options( 'not json' ) );
		$this->assertNull( Backup::extract_options( '[]' ) );
		$this->assertNull( Backup::extract_options( '{"plugin":"other","options":{}}' ) );
		$this->assertNull( Backup::extract_options( '{"plugin":"nanobar"}' ) );
		$this->assertNull( Backup::extract_options( '{"plugin":"nanobar","options":"x"}' ) );
	}

	/**
	 * An exported then re-sanitized payload is stable (the round trip loses nothing).
	 */
	public function test_export_round_trip_is_stable(): void {
		$options = Options::get_defaults();
		$options['quick_links'] = array(
			array(
				'label'          => 'Posts',
				'url'            => '/wp-admin/edit.php',
				'icon'           => 'dashicons-admin-post',
				'roles'          => array( 'editor' ),
				'mobile_visible' => true,
			),
		);

		$once  = Sanitizer::sanitize( $options );
		$twice = Sanitizer::sanitize( $once );

		$this->assertSame( $once, $twice );
		$this->assertSame( $options['quick_links'], $once['quick_links'] );
	}

	/**
	 * Malicious values in an import are neutralized by the sanitizer.
	 */
	public function test_import_is_sanitized(): void {
		$out = Sanitizer::sanitize(
			array(
				'toggle_size'      => 9999,
				'toggle_bg_color'  => 'red;}</style><script>',
				'position_horizontal' => 'nowhere',
				'quick_links'      => array(
					array(
						'label' => '<b>x</b>',
						'url'   => 'https://evil.example.com',
					),
				),
			)
		);

		$this->assertSame( 72, $out['toggle_size'] );
		$this->assertSame( Options::get_defaults()['toggle_bg_color'], $out['toggle_bg_color'] );
		$this->assertSame( array(), $out['quick_links'] );
	}

	/**
	 * Only known result codes print a message.
	 */
	public function test_result_messages(): void {
		$this->assertSame( 'success', Backup::get_result_message( 'imported' )[1] );
		$this->assertSame( 'error', Backup::get_result_message( 'invalid' )[1] );
		$this->assertNull( Backup::get_result_message( '<script>' ) );
		$this->assertNull( Backup::get_result_message( '' ) );
	}
}

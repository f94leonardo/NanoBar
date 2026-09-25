<?php
/**
 * Tests for NanoBar\Settings\Sanitizer.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

use NanoBar\Settings\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sanitizer (write path).
 */
final class SanitizerTest extends TestCase {

	/**
	 * Sanitizes a single quick link and returns the stored rows.
	 *
	 * @param array<string, mixed> $link Raw link row.
	 * @return array<int, array<string, mixed>>
	 */
	private function links( array $link ): array {
		$out = Sanitizer::sanitize( array( 'quick_links' => array( $link ) ) );

		return $out['quick_links'];
	}

	/**
	 * URL cases: raw input => stored value ('' = row dropped).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function urls(): array {
		$home = untrailingslashit( home_url() );

		return array(
			'relative path'              => array( '/wp-admin/edit.php', '/wp-admin/edit.php' ),
			'relative with query'        => array( '/wp-admin/x.php?p=%2Fa', '/wp-admin/x.php?p=%2Fa' ),
			'same-site absolute'         => array( $home . '/wp-admin/edit.php?a=1#f', '/wp-admin/edit.php?a=1#f' ),
			'external host'              => array( 'https://evil.example.com/x', '' ),
			'protocol-relative external' => array( '//evil.example.com/x', '' ),
			'javascript scheme'          => array( 'javascript:alert(1)', '' ),
			'data scheme'                => array( 'data:text/html,x', '' ),
			'same-site double slash'     => array( $home . '//evil.example.com/x', '/evil.example.com/x' ),
			'empty'                      => array( '', '' ),
		);
	}

	/**
	 * @param string $raw      Raw URL.
	 * @param string $expected Stored URL, or '' when the row is dropped.
	 */
	#[DataProvider( 'urls' )]
	public function test_quick_link_url( string $raw, string $expected ): void {
		$rows = $this->links(
			array(
				'label' => 'L',
				'url'   => $raw,
			)
		);

		if ( '' === $expected ) {
			$this->assertSame( array(), $rows );
			return;
		}

		$this->assertCount( 1, $rows );
		$this->assertSame( $expected, $rows[0]['url'] );
		$this->assertStringStartsNotWith( '//', $rows[0]['url'] );
	}

	/**
	 * Half-filled rows are dropped.
	 */
	public function test_row_without_label_is_dropped(): void {
		$this->assertSame(
			array(),
			$this->links(
				array(
					'label' => '   ',
					'url'   => '/x',
				)
			)
		);
	}

	/**
	 * Icon must be a dashicon class; roles are optional and filtered.
	 */
	public function test_icon_and_roles(): void {
		$rows = $this->links(
			array(
				'label' => 'L',
				'url'   => '/x',
				'icon'  => 'dashicons-admin-home" onclick="x',
				'roles' => array( 'administrator', 'nope', 'subscriber' ),
			)
		);
		$this->assertSame( 'dashicons-admin-links', $rows[0]['icon'] );
		$this->assertSame( array( 'administrator' ), $rows[0]['roles'] );
		$this->assertFalse( $rows[0]['mobile_visible'] );

		$rows = $this->links(
			array(
				'label' => 'L',
				'url'   => '/x',
				'icon'  => 'dashicons-star-filled',
			)
		);
		$this->assertSame( 'dashicons-star-filled', $rows[0]['icon'] );
		$this->assertSame( array(), $rows[0]['roles'] );
	}

	/**
	 * The number of links is capped.
	 */
	public function test_quick_links_max(): void {
		$max   = \NanoBar\Config::get()['quick_links']['max'];
		$links = array();
		for ( $i = 0; $i < $max + 5; $i++ ) {
			$links[] = array(
				'label' => 'L' . $i,
				'url'   => '/x' . $i,
			);
		}
		$out = Sanitizer::sanitize( array( 'quick_links' => $links ) );
		$this->assertCount( $max, $out['quick_links'] );
	}

	/**
	 * Garbage input never fatals and yields the defaults' shape.
	 */
	public function test_garbage_input(): void {
		foreach ( array( null, 'x', 5, array( 'quick_links' => 'x', 'roles' => 'x', 'visible_items' => 'x', 'mobile_items' => 'x' ) ) as $input ) {
			$out = Sanitizer::sanitize( $input );
			$this->assertIsArray( $out );
			$this->assertNotEmpty( $out['roles'] );
			$this->assertSame( array(), $out['quick_links'] );
		}
	}

	/**
	 * A save that omits mobile_items keeps everything visible on mobile;
	 * one that includes the array honours unchecked boxes.
	 */
	public function test_mobile_items(): void {
		$out = Sanitizer::sanitize( array() );
		$this->assertNotContains( false, $out['mobile_items'] );

		$out = Sanitizer::sanitize( array( 'mobile_items' => array( 'dashboard' => '1' ) ) );
		$this->assertTrue( $out['mobile_items']['dashboard'] );
		$this->assertFalse( $out['mobile_items']['logout'] );
	}

	/**
	 * Size and unit are clamped together.
	 */
	public function test_size_and_unit(): void {
		$out = Sanitizer::sanitize(
			array(
				'toggle_size'      => '99',
				'toggle_size_unit' => 'rem',
			)
		);
		$this->assertSame( 'rem', $out['toggle_size_unit'] );
		$this->assertEqualsWithDelta( 4.5, $out['toggle_size'], 0.0001 );

		$out = Sanitizer::sanitize(
			array(
				'toggle_size'      => '99',
				'toggle_size_unit' => 'bogus',
			)
		);
		$this->assertSame( 'px', $out['toggle_size_unit'] );
		$this->assertSame( 72, $out['toggle_size'] );
	}
}

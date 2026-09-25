<?php
/**
 * Tests for NanoBar\Settings\Options normalizers.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

use NanoBar\Settings\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Options normalizers.
 */
final class OptionsTest extends TestCase {

	/**
	 * Size clamping per unit.
	 *
	 * @return array<string, array{0: mixed, 1: mixed, 2: int|float}>
	 */
	public static function sizes(): array {
		return array(
			'px in range'            => array( 44, 'px', 44 ),
			'px below min'           => array( 5, 'px', 32 ),
			'px above max'           => array( 500, 'px', 72 ),
			'px rounds'              => array( '40.6', 'px', 41 ),
			'px huge string'         => array( '1e999', 'px', 72 ),
			'px negative huge'       => array( '-1e999', 'px', 32 ),
			'px garbage'             => array( 'abc', 'px', 32 ),
			'px array'               => array( array( 1 ), 'px', 32 ),
			'unknown unit is px'     => array( 50, 'em', 50 ),
			'rem in range'           => array( 3, 'rem', 3.0 ),
			'rem rounds to 2 places' => array( 2.5678, 'rem', 2.57 ),
			'rem below min'          => array( 0.5, 'rem', 2.0 ),
			'rem above max'          => array( 99, 'rem', 4.5 ),
			'rem huge string'        => array( '1e999', 'rem', 4.5 ),
		);
	}

	/**
	 * @param mixed     $size     Requested size.
	 * @param mixed     $unit     Requested unit.
	 * @param int|float $expected Expected result.
	 */
	#[DataProvider( 'sizes' )]
	public function test_clamp_toggle_size( mixed $size, mixed $unit, int|float $expected ): void {
		$this->assertEqualsWithDelta( $expected, Options::clamp_toggle_size( $size, $unit ), 0.0001 );
	}

	/**
	 * Unit normalizer.
	 */
	public function test_normalize_size_unit(): void {
		$this->assertSame( 'rem', Options::normalize_size_unit( 'rem' ) );
		$this->assertSame( 'px', Options::normalize_size_unit( 'vh' ) );
		$this->assertSame( 'px', Options::normalize_size_unit( null ) );
	}

	/**
	 * Color scheme normalizer.
	 */
	public function test_normalize_color_scheme(): void {
		$this->assertSame( 'dark', Options::normalize_color_scheme( 'dark' ) );
		$this->assertSame( 'auto', Options::normalize_color_scheme( 'blue' ) );
		$this->assertSame( 'auto', Options::normalize_color_scheme( array() ) );
	}

	/**
	 * A missing key in the stored option falls back to the default.
	 */
	public function test_visible_and_mobile_items_fall_back_to_defaults(): void {
		$visible = Options::get_visible_items( array() );
		$this->assertTrue( $visible['dashboard'] );
		$this->assertFalse( $visible['query_monitor'] );

		$mobile = Options::get_mobile_items( array() );
		$this->assertNotEmpty( $mobile );
		$this->assertNotContains( false, $mobile );
	}
}

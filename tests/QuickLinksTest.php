<?php
/**
 * Tests for the read path: NanoBar\Frontend\QuickLinks.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

use NanoBar\Frontend\QuickLinks;
use PHPUnit\Framework\TestCase;

/**
 * Stored quick links are re-validated when read.
 */
final class QuickLinksTest extends TestCase {

	/**
	 * Original current user, restored after each test.
	 *
	 * @var mixed
	 */
	private mixed $original_user;

	/**
	 * Saves the current user.
	 */
	protected function setUp(): void {
		$this->original_user = $GLOBALS['current_user'] ?? null;
	}

	/**
	 * Restores the current user.
	 */
	protected function tearDown(): void {
		$GLOBALS['current_user'] = $this->original_user;
	}

	/**
	 * Makes the current user an in-memory user holding the given roles.
	 *
	 * @param array<int, string> $roles Roles.
	 */
	private function act_as( array $roles ): void {
		$user        = new WP_User();
		$user->roles = $roles;
		$GLOBALS['current_user'] = $user;
	}

	/**
	 * Non-array or empty input yields nothing.
	 */
	public function test_non_arrays_yield_nothing(): void {
		$this->assertSame( array(), QuickLinks::get_visible( null ) );
		$this->assertSame( array(), QuickLinks::get_visible( 'x' ) );
		$this->assertSame( array(), QuickLinks::get_visible( array() ) );
	}

	/**
	 * Malformed rows are skipped without errors.
	 */
	public function test_malformed_rows_are_skipped(): void {
		$this->act_as( array( 'editor' ) );
		$rows = array(
			'string',
			array( 'label' => 'x' ),
			array( 'url' => '/x' ),
			array(
				'label' => array(),
				'url'   => '/x',
			),
			array(
				'label' => 'ok',
				'url'   => 5,
			),
			array(
				'label' => 'Good',
				'url'   => '/good',
			),
		);

		$out = QuickLinks::get_visible( $rows );
		$this->assertCount( 1, $out );
		$this->assertSame( 'Good', $out[0]['label'] );
	}

	/**
	 * Off-site and script URLs never reach the panel, even if stored.
	 */
	public function test_unsafe_urls_are_dropped(): void {
		$this->act_as( array( 'administrator' ) );
		$urls = array( 'https://evil.example.com/', '//evil.example.com/', 'javascript:alert(1)' );

		foreach ( $urls as $url ) {
			$this->assertSame(
				array(),
				QuickLinks::get_visible(
					array(
						array(
							'label' => 'x',
							'url'   => $url,
						),
					)
				),
				$url
			);
		}
	}

	/**
	 * Role restriction: matching role sees it, others don't, empty = all.
	 */
	public function test_role_filtering(): void {
		$rows = array(
			array(
				'label' => 'Admins',
				'url'   => '/a',
				'roles' => array( 'administrator' ),
			),
			array(
				'label' => 'Everyone',
				'url'   => '/e',
				'roles' => array(),
			),
		);

		$this->act_as( array( 'editor' ) );
		$labels = array_column( QuickLinks::get_visible( $rows ), 'label' );
		$this->assertSame( array( 'Everyone' ), $labels );

		$this->act_as( array( 'administrator' ) );
		$labels = array_column( QuickLinks::get_visible( $rows ), 'label' );
		$this->assertSame( array( 'Admins', 'Everyone' ), $labels );
	}

	/**
	 * Icon fallback and the mobile-visible default.
	 */
	public function test_icon_and_mobile_defaults(): void {
		$this->act_as( array( 'editor' ) );
		$out = QuickLinks::get_visible(
			array(
				array(
					'label' => 'A',
					'url'   => '/a',
					'icon'  => 'x" onload="y',
				),
				array(
					'label'          => 'B',
					'url'            => '/b',
					'icon'           => 'dashicons-star-filled',
					'mobile_visible' => false,
				),
			)
		);

		$this->assertSame( 'dashicons-admin-links', $out[0]['icon'] );
		$this->assertTrue( $out[0]['mobile_visible'] );
		$this->assertSame( 'dashicons-star-filled', $out[1]['icon'] );
		$this->assertFalse( $out[1]['mobile_visible'] );
	}
}

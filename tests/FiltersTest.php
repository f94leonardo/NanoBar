<?php
/**
 * Tests that malformed values returned by the plugin's public filters are
 * ignored instead of causing fatals or broken markup.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

use NanoBar\Frontend\Commands;
use NanoBar\Frontend\Menus;
use PHPUnit\Framework\TestCase;

/**
 * Public filters: nanobar_command_palette_items, nanobar_cache_plugins.
 */
final class FiltersTest extends TestCase {

	/**
	 * Removes the filters added by a test.
	 */
	protected function tearDown(): void {
		remove_all_filters( 'nanobar_command_palette_items' );
		remove_all_filters( 'nanobar_cache_plugins' );
	}

	/**
	 * Command palette: only well-formed, visible entries survive, with safe URLs and icons.
	 */
	public function test_command_palette_entries_are_validated(): void {
		add_filter(
			'nanobar_command_palette_items',
			static fn (): array => array(
				'not-an-array',
				array( 'label' => 'No callable' ),
				array(
					'label'      => 'Bad url',
					'url'        => 'javascript:alert(1)',
					'icon'       => 'dashicons-star-filled',
					'is_visible' => static fn (): bool => true,
				),
				array(
					'label'      => 'Hidden',
					'url'        => '/hidden',
					'icon'       => 'dashicons-star-filled',
					'is_visible' => static fn (): bool => false,
				),
				array(
					'label'      => 'Good',
					'url'        => '/good',
					'icon'       => 'not-a-dashicon',
					'is_visible' => static fn (): bool => true,
				),
			)
		);

		$items = Commands::get_extra_items();
		$this->assertCount( 1, $items );
		$this->assertSame( 'Good', $items[0]['label'] );
		$this->assertSame( 'dashicons-admin-generic', $items[0]['icon'] );
	}

	/**
	 * A filter returning a non-array leaves the palette empty rather than fataling.
	 */
	public function test_command_palette_non_array_filter(): void {
		add_filter( 'nanobar_command_palette_items', static fn (): string => 'oops' );
		$this->assertSame( array(), Commands::get_extra_items() );
	}

	/**
	 * Cache plugin registry: bad entries are skipped; node ids are restricted to DOM-safe characters.
	 */
	public function test_cache_registry_is_validated(): void {
		add_filter(
			'nanobar_cache_plugins',
			static fn (): array => array(
				'garbage',
				array( 'label' => 'No callable' ),
				array(
					'is_active'          => static fn (): bool => true,
					'label'              => '',
					'settings_url'       => '/x',
					'purge_all_node_id'  => 'a',
				),
				array(
					'id'                 => 'demo',
					'is_active'          => static fn (): bool => true,
					'label'              => 'Demo',
					'settings_url'       => '/wp-admin/x',
					'purge_all_node_id'  => 'wp-admin-bar-demo"><script>',
					'purge_page_node_id' => 'demo-page',
				),
			)
		);

		$plugin = Menus::get_active_cache_plugin();
		$this->assertNotNull( $plugin );
		$this->assertSame( 'Demo', $plugin['label'] );
		$this->assertNull( $plugin['purge_all_node_id'] );
		$this->assertSame( 'demo-page', $plugin['purge_page_node_id'] );
	}

	/**
	 * A filter returning a non-array yields no active cache plugin.
	 */
	public function test_cache_registry_non_array_filter(): void {
		add_filter( 'nanobar_cache_plugins', static fn (): string => 'oops' );
		$this->assertNull( Menus::get_active_cache_plugin() );
	}
}

<?php
/**
 * Plugin Name: AI Polish
 * Plugin URI:  https://moreonlineprofit.com/ai-polish
 * Description: Rewrite or polish WordPress content using your own OpenAI API key.
 * Version:     0.1.0
 * Author: Terry J
 * Author URI:  https://terryjett.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-polish
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_POLISH_VERSION', '0.1.0' );
define( 'AI_POLISH_PLUGIN_FILE', __FILE__ );
define( 'AI_POLISH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_POLISH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AI_POLISH_PLUGIN_DIR . 'includes/helpers.php';
require_once AI_POLISH_PLUGIN_DIR . 'admin/api-handler.php';
require_once AI_POLISH_PLUGIN_DIR . 'admin/settings-page.php';
require_once AI_POLISH_PLUGIN_DIR . 'admin/metabox.php';

function ai_polish_init(): void {
	if ( is_admin() ) {
		ai_polish_register_settings_page();
		ai_polish_register_metabox();
		ai_polish_register_ajax_endpoints();
		add_action( 'admin_enqueue_scripts', 'ai_polish_admin_enqueue' );
		add_action( 'enqueue_block_editor_assets', 'ai_polish_enqueue_block_editor_assets' );
	}
}

add_action( 'plugins_loaded', 'ai_polish_init' );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( array $links ): array {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'options-general.php?page=ai-polish' ) ) . '">Settings</a>',
			'<a href="https://moreonlineprofit.com" style="color:#46b450;font-weight:600;" target="_blank" rel="noopener noreferrer">More</a>',
			'<a href="https://qiksoft.com" target="_blank" rel="noopener noreferrer">Recommendations</a>',
		);

		return array_merge( $custom, $links );
	}
);

add_filter(
	'plugin_row_meta',
	function ( array $meta, string $plugin_file ): array {
		if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
			return $meta;
		}

		$has_view_details = false;
		foreach ( $meta as $item ) {
			if ( false !== stripos( wp_strip_all_tags( (string) $item ), 'view details' ) ) {
				$has_view_details = true;
				break;
			}
		}

		if ( ! $has_view_details ) {
			$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=ai-polish&TB_iframe=true&width=600&height=550' );
			$meta[]      = '<a href="' . esc_url( $details_url ) . '" class="thickbox open-plugin-details-modal">View details</a>';
		}

		$meta[] = '<a href="https://example.com/docs" target="_blank" rel="noopener noreferrer">Docs</a>';
		$meta[] = '<a href="https://example.com/support" target="_blank" rel="noopener noreferrer">Support</a>';

		return $meta;
	},
	10,
	2
);

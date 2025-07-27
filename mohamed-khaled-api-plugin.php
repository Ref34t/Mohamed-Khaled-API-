<?php
/**
 * Plugin Name: Mohamed Khaled API Plugin
 * Plugin URI: https://github.com/mohamedkhaled/wp-api-plugin
 * Description: WordPress plugin for API integration with caching and custom Gutenberg blocks.
 * Version: 1.2.0
 * Author: Mohamed Khaled
 * Author URI: https://mohamedkhaled.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mohamed-khaled-api-plugin
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://mohamedkhaled.dev/plugins/wp-api-plugin/
 */

namespace MohamedKhaledApiPlugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check PHP version compatibility.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Mohamed Khaled API Plugin requires PHP 7.4 or higher. Your current PHP version is ' . PHP_VERSION . '.', 'mohamed-khaled-api-plugin' );
		echo '</p></div>';
	});
	return;
}

// Define plugin constants.
define( 'MKAP_VERSION', '1.2.0' );
define( 'MKAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MKAP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoloader.
require_once MKAP_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'MohamedKhaledApiPlugin\Plugin' ) ) {
			Plugin::get_instance();
		}
	}
);

// Activation hook
register_activation_hook(
	__FILE__,
	function () {
		if ( class_exists( 'MohamedKhaledApiPlugin\Plugin' ) ) {
			Plugin::activate();
		}
	}
);

// Deactivation hook
register_deactivation_hook(
	__FILE__,
	function () {
		try {
			if ( class_exists( 'MohamedKhaledApiPlugin\Plugin' ) ) {
				Plugin::deactivate();
			}
		} catch ( Exception $e ) {
			// Log error but don't stop deactivation
			if ( function_exists( 'error_log' ) ) {
				error_log( 'MKAP Plugin deactivation error: ' . $e->getMessage() );
			}
		}
	}
);

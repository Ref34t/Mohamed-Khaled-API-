<?php
/**
 * Uninstall script for Mohamed Khaled API Plugin
 *
 * This file is called when the plugin is deleted from WordPress.
 * It removes all plugin data including transients, options, and database entries.
 *
 * @package MohamedKhaledApiPlugin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Main uninstall handler class
 *
 * @since 1.2.0
 */
class MKAP_Uninstaller {

	/**
	 * Run the uninstaller
	 *
	 * @since 1.2.0
	 */
	public static function uninstall() {
		try {
			self::remove_options();
			self::remove_transients();
			self::remove_user_meta();
			self::remove_post_meta();
			self::remove_scheduled_events();
			self::flush_cache_and_rules();
		} catch ( Exception $e ) {
			// Log error but don't prevent uninstall
			if ( function_exists( 'error_log' ) ) {
				error_log( 'MKAP Plugin Uninstall Error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Remove plugin options
	 *
	 * @since 1.2.0
	 */
	private static function remove_options() {
		try {
			$options = array(
				'mkap_plugin_version',
				'mkap_settings',
				'mkap_activation_time',
				'mkap_db_version',
				'widget_mkap_widget',
				'mkap_plugin_notices'
			);

			foreach ( $options as $option ) {
				delete_option( $option );
			}
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error removing options: ' . $e->getMessage() );
		}
	}

	/**
	 * Remove all plugin transients
	 *
	 * @since 1.2.0
	 */
	private static function remove_transients() {
		global $wpdb;

		try {
			// Delete known transients
			delete_transient( 'mkap_api_data' );
			delete_transient( 'mkap_plugin_info' );

			// Delete all plugin transients using direct database query
			$plugin_transients = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} 
					WHERE option_name LIKE %s 
					OR option_name LIKE %s",
					'_transient_mkap_%',
					'_transient_timeout_mkap_%'
				)
			);

			foreach ( $plugin_transients as $transient ) {
				$key = str_replace( array( '_transient_', '_transient_timeout_' ), '', $transient );
				delete_transient( $key );
			}

			// Clean up expired transients
			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE '_transient_timeout_mkap_%' 
				AND option_value < UNIX_TIMESTAMP()"
			);
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error removing transients: ' . $e->getMessage() );
		}
	}

	/**
	 * Remove user meta data
	 *
	 * @since 1.2.0
	 */
	private static function remove_user_meta() {
		global $wpdb;

		try {
			$user_meta_keys = array(
				'mkap_user_preferences',
				'mkap_dismissed_notices',
				'mkap_last_view',
				'mkap_api_usage'
			);

			foreach ( $user_meta_keys as $meta_key ) {
				$wpdb->delete(
					$wpdb->usermeta,
					array( 'meta_key' => $meta_key ),
					array( '%s' )
				);
			}
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error removing user meta: ' . $e->getMessage() );
		}
	}

	/**
	 * Remove post meta data
	 *
	 * @since 1.2.0
	 */
	private static function remove_post_meta() {
		global $wpdb;

		try {
			$post_meta_keys = array(
				'_mkap_api_data',
				'_mkap_cache_time',
				'_mkap_display_settings',
				'_mkap_column_visibility'
			);

			foreach ( $post_meta_keys as $meta_key ) {
				$wpdb->delete(
					$wpdb->postmeta,
					array( 'meta_key' => $meta_key ),
					array( '%s' )
				);
			}
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error removing post meta: ' . $e->getMessage() );
		}
	}

	/**
	 * Remove scheduled cron events
	 *
	 * @since 1.2.0
	 */
	private static function remove_scheduled_events() {
		try {
			// Remove specific scheduled events
			$cron_events = array(
				'mkap_hourly_cleanup',
				'mkap_daily_sync',
				'mkap_cache_cleanup',
				'mkap_api_refresh'
			);

			foreach ( $cron_events as $event ) {
				// Clear all scheduled hooks for this event
				wp_clear_scheduled_hook( $event );
				
				// Also check for any remaining scheduled events
				$timestamp = wp_next_scheduled( $event );
				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, $event );
				}
			}
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error removing scheduled events: ' . $e->getMessage() );
		}
	}

	/**
	 * Flush cache and rewrite rules
	 *
	 * @since 1.2.0
	 */
	private static function flush_cache_and_rules() {
		try {
			// Flush object cache
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			// Flush rewrite rules
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}
		} catch ( Exception $e ) {
			error_log( 'MKAP Uninstall - Error flushing cache/rules: ' . $e->getMessage() );
		}
	}
}

// Run the uninstaller
MKAP_Uninstaller::uninstall();
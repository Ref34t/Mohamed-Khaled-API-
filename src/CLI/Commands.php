<?php

namespace MohamedKhaledApiPlugin\CLI;

use MohamedKhaledApiPlugin\Api\ApiClient;
use MohamedKhaledApiPlugin\Security\Security;
use WP_CLI;

class Commands {

	/**
	 * Register all CLI commands
	 */
	public static function register_commands() {
		\WP_CLI::add_command( 'mkap refresh', [ __CLASS__, 'refresh' ] );
		\WP_CLI::add_command( 'mkap status', [ __CLASS__, 'status' ] );
		\WP_CLI::add_command( 'mkap cache', [ __CLASS__, 'cache' ] );
		\WP_CLI::add_command( 'mkap test', [ __CLASS__, 'test' ] );
	}

	/**
	 * Refresh the API cache
	 *
	 * ## EXAMPLES
	 *
	 *     wp mkap refresh
	 *     wp mkap refresh --debug
	 *
	 * ## OPTIONS
	 * 
	 * [--debug]
	 * : Show detailed debug information
	 */
	public static function refresh( $args, $assoc_args ) {
		$debug = isset( $assoc_args['debug'] );
		
		if ( $debug ) {
			WP_CLI::log( 'Starting cache refresh process...' );
		}
		
		$cache_cleared = ApiClient::clear_cache();
		
		if ( $cache_cleared ) {
			if ( $debug ) {
				WP_CLI::log( 'Cache cleared successfully.' );
				WP_CLI::log( 'Fetching fresh data from API...' );
			}
			
			$data = ApiClient::get_data( true );
			
			if ( is_wp_error( $data ) ) {
				WP_CLI::error( 'Failed to fetch fresh data: ' . $data->get_error_message() );
			} else {
				$row_count = is_array( $data ) && isset( $data['rows'] ) ? count( $data['rows'] ) : 0;
				WP_CLI::success( sprintf( 'Cache cleared and fresh data fetched successfully. %d rows retrieved.', $row_count ) );
				
				if ( $debug ) {
					WP_CLI::log( 'API Response structure:' );
					WP_CLI::log( \wp_json_encode( array_keys( $data ), JSON_PRETTY_PRINT ) );
				}
			}
		} else {
			WP_CLI::error( 'Failed to clear cache.' );
		}
	}

	/**
	 * Show plugin status and cache information
	 *
	 * ## EXAMPLES
	 *
	 *     wp mkap status
	 *     wp mkap status --format=json
	 *
	 * ## OPTIONS
	 * 
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 */
	public static function status( $args, $assoc_args ) {
		$cache_info = ApiClient::get_cache_info();
		$security_stats = Security::get_security_stats();
		
		$status_data = [
			[
				'Component' => 'Cache Status',
				'Value' => $cache_info['is_cached'] ? 'Active' : 'Inactive',
				'Details' => $cache_info['is_cached'] ? 'Data is cached' : 'No cached data'
			],
			[
				'Component' => 'Cache Duration',
				'Value' => human_time_diff( 0, $cache_info['cache_duration'] ),
				'Details' => 'Time data is cached'
			],
			[
				'Component' => 'Rate Limiting',
				'Value' => $security_stats['rate_limit_enabled'] ? 'Enabled' : 'Disabled',
				'Details' => sprintf( '%d requests per hour', $security_stats['rate_limit_requests'] )
			],
			[
				'Component' => 'Security Headers',
				'Value' => $security_stats['security_headers_enabled'] ? 'Enabled' : 'Disabled',
				'Details' => 'XSS, Clickjacking protection'
			],
			[
				'Component' => 'Plugin Version',
				'Value' => defined( 'MKAP_VERSION' ) ? MKAP_VERSION : 'Unknown',
				'Details' => 'Current plugin version'
			]
		];

		$format = $assoc_args['format'] ?? 'table';
		
		if ( $format === 'json' ) {
			WP_CLI::log( \wp_json_encode( $status_data, JSON_PRETTY_PRINT ) );
		} elseif ( $format === 'yaml' ) {
			foreach ( $status_data as $item ) {
				WP_CLI::log( sprintf( '%s:', $item['Component'] ) );
				WP_CLI::log( sprintf( '  value: %s', $item['Value'] ) );
				WP_CLI::log( sprintf( '  details: %s', $item['Details'] ) );
			}
		} else {
			WP_CLI\Utils\format_items( 'table', $status_data, [ 'Component', 'Value', 'Details' ] );
		}
	}

	/**
	 * Cache management commands
	 *
	 * ## EXAMPLES
	 *
	 *     wp mkap cache clear
	 *     wp mkap cache info
	 */
	public static function cache( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( 'Please specify a cache action: clear' );
		}

		$action = $args[0];

		switch ( $action ) {
			case 'clear':
				$cleared = ApiClient::clear_cache();
				if ( $cleared ) {
					WP_CLI::success( 'Cache cleared successfully.' );
				} else {
					WP_CLI::error( 'Failed to clear cache.' );
				}
				break;

			case 'info':
				$cache_info = ApiClient::get_cache_info();
				WP_CLI::log( 'Cache Information:' );
				WP_CLI::log( sprintf( 'Status: %s', $cache_info['is_cached'] ? 'Active' : 'Inactive' ) );
				WP_CLI::log( sprintf( 'Key: %s', $cache_info['cache_key'] ) );
				WP_CLI::log( sprintf( 'Duration: %s', human_time_diff( 0, $cache_info['cache_duration'] ) ) );
				break;

			default:
				WP_CLI::error( sprintf( 'Unknown cache action: %s. Available actions: clear, info', $action ) );
		}
	}

	/**
	 * Test API connectivity and response
	 *
	 * ## EXAMPLES
	 *
	 *     wp mkap test
	 *     wp mkap test --verbose
	 *
	 * ## OPTIONS
	 * 
	 * [--verbose]
	 * : Show detailed test information
	 */
	public static function test( $args, $assoc_args ) {
		$verbose = isset( $assoc_args['verbose'] );
		
		WP_CLI::log( 'Testing API connectivity...' );
		
		// Test basic connectivity
		$start_time = microtime( true );
		$response = wp_remote_get( ApiClient::API_ENDPOINT, [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/json',
			],
		] );
		$end_time = microtime( true );
		$response_time = round( ( $end_time - $start_time ) * 1000, 2 );
		
		if ( is_wp_error( $response ) ) {
			WP_CLI::error( sprintf( 'API connectivity test failed: %s', $response->get_error_message() ) );
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		WP_CLI::log( sprintf( 'Response Code: %d', $response_code ) );
		WP_CLI::log( sprintf( 'Response Time: %sms', $response_time ) );
		
		if ( $response_code === 200 ) {
			$data = json_decode( $body, true );
			
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
				$row_count = isset( $data['rows'] ) ? count( $data['rows'] ) : 0;
				WP_CLI::success( sprintf( 'API test successful! Retrieved %d rows.', $row_count ) );
				
				if ( $verbose && $row_count > 0 ) {
					WP_CLI::log( 'Sample data structure:' );
					$sample_row = $data['rows'][0] ?? [];
					foreach ( $sample_row as $key => $value ) {
						WP_CLI::log( sprintf( '  %s: %s', $key, is_string( $value ) ? substr( $value, 0, 50 ) : gettype( $value ) ) );
					}
				}
			} else {
				WP_CLI::error( 'API returned invalid JSON data.' );
			}
		} else {
			WP_CLI::error( sprintf( 'API test failed with HTTP status %d', $response_code ) );
		}
		
		// Test caching
		WP_CLI::log( 'Testing caching functionality...' );
		
		// Clear existing cache
		ApiClient::clear_cache();
		
		// First request (should cache)
		$start_time = microtime( true );
		$data1 = ApiClient::get_data( false );
		$end_time = microtime( true );
		$time1 = round( ( $end_time - $start_time ) * 1000, 2 );
		
		// Second request (should use cache)
		$start_time = microtime( true );
		$data2 = ApiClient::get_data( false );
		$end_time = microtime( true );
		$time2 = round( ( $end_time - $start_time ) * 1000, 2 );
		
		if ( ! is_wp_error( $data1 ) && ! is_wp_error( $data2 ) ) {
			WP_CLI::log( sprintf( 'First request (no cache): %sms', $time1 ) );
			WP_CLI::log( sprintf( 'Second request (cached): %sms', $time2 ) );
			
			if ( $time2 < $time1 ) {
				WP_CLI::success( 'Caching is working correctly!' );
			} else {
				WP_CLI::warning( 'Cache performance test inconclusive.' );
			}
		} else {
			WP_CLI::warning( 'Cache test failed due to API errors.' );
		}
	}
}

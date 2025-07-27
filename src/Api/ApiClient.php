<?php

namespace MohamedKhaledApiPlugin\Api;

use WP_Error;

class ApiClient {

	const API_ENDPOINT = 'https://miusage.com/v1/challenge/1/';
	const CACHE_KEY = 'mkap_api_data';
	const CACHE_DURATION = HOUR_IN_SECONDS;

	public static function get_data( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached_data = get_transient( self::CACHE_KEY );
			if ( false !== $cached_data && is_array( $cached_data ) ) {
				return $cached_data;
			}
		}

		$response = wp_remote_get( self::API_ENDPOINT, array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'mkap_api_error',
				sprintf( 'API request failed with status %d', $response_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'mkap_json_error',
				'Failed to parse API response as JSON'
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'mkap_invalid_response',
				'API response is not a valid array'
			);
		}

		// Transform API response structure to match expected format
		// API returns: {"title": "...", "data": {"headers": [...], "rows": {"1": {...}, "2": {...}}}}
		// Plugin expects: {"rows": [...], "title": "...", "headers": [...]}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$api_data = $data['data'];
			
			// Convert the rows object to an indexed array
			$rows = array();
			if ( isset( $api_data['rows'] ) && is_array( $api_data['rows'] ) ) {
				$rows = array_values( $api_data['rows'] );
			}
			
			$transformed_data = array(
				'rows' => $rows,
				'title' => $data['title'] ?? 'API Data',
				'headers' => $api_data['headers'] ?? array(),
				'total_records' => count( $rows ),
				'last_updated' => current_time( 'mysql' )
			);
		} else {
			// Fallback for unexpected API structure
			$transformed_data = array(
				'rows' => is_array( $data ) ? $data : array(),
				'title' => 'API Data',
				'headers' => array(),
				'total_records' => is_array( $data ) ? count( $data ) : 0,
				'last_updated' => current_time( 'mysql' )
			);
		}

		set_transient( self::CACHE_KEY, $transformed_data, self::CACHE_DURATION );

		return $transformed_data;
	}

	public static function clear_cache() {
		return delete_transient( self::CACHE_KEY );
	}

	public static function get_cache_info() {
		$cached_data = get_transient( self::CACHE_KEY );
		return array(
			'is_cached' => $cached_data !== false,
			'cache_key' => self::CACHE_KEY,
			'cache_duration' => self::CACHE_DURATION,
		);
	}
}

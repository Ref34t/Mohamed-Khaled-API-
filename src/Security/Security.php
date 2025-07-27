<?php

namespace MohamedKhaledApiPlugin\Security;

/**
 * Security class for Mohamed Khaled API Plugin
 * 
 * Implements comprehensive security measures including rate limiting,
 * input validation, output sanitization, and security headers.
 */
class Security {

	/**
	 * Rate limit configuration
	 */
	const RATE_LIMIT_REQUESTS = 60; // requests per hour
	const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;
	const RATE_LIMIT_PREFIX = 'mkap_rate_limit_';

	/**
	 * Initialize security measures
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'security_headers' ] );
		add_action( 'wp_ajax_mkap_get_data', [ __CLASS__, 'check_rate_limit' ], 1 );
		add_action( 'wp_ajax_nopriv_mkap_get_data', [ __CLASS__, 'check_rate_limit' ], 1 );
		add_action( 'wp_ajax_mkap_refresh_data', [ __CLASS__, 'check_rate_limit' ], 1 );
	}

	/**
	 * Add security headers
	 */
	public static function security_headers() {
		if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'mkap-api-data' ) {
			// Prevent clickjacking
			header( 'X-Frame-Options: SAMEORIGIN' );
			
			// Prevent MIME type sniffing
			header( 'X-Content-Type-Options: nosniff' );
			
			// Enable XSS protection
			header( 'X-XSS-Protection: 1; mode=block' );
			
			// Prevent information disclosure
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}

	/**
	 * Check rate limit for AJAX requests
	 */
	public static function check_rate_limit() {
		$user_ip = self::get_user_ip();
		$rate_limit_key = self::RATE_LIMIT_PREFIX . md5( $user_ip );
		
		$current_requests = get_transient( $rate_limit_key );
		
		if ( false === $current_requests ) {
			$current_requests = 0;
		}
		
		if ( $current_requests >= self::RATE_LIMIT_REQUESTS ) {
			wp_send_json_error( [
				'message' => __( 'Rate limit exceeded. Please try again later.', 'mohamed-khaled-api-plugin' ),
				'code' => 'rate_limit_exceeded'
			], 429 );
		}
		
		// Increment request count
		set_transient( $rate_limit_key, $current_requests + 1, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Get user IP address safely
	 * 
	 * @return string User IP address
	 */
	public static function get_user_ip() {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',            // Proxy
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
			'HTTP_X_FORWARDED',          // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxy
			'HTTP_FORWARDED',            // Proxy
			'REMOTE_ADDR'                // Standard
		];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback
	}

	/**
	 * Validate and sanitize input data
	 * 
	 * @param mixed $data Raw input data
	 * @param string $type Expected data type
	 * @return mixed Sanitized data or false on failure
	 */
	public static function validate_input( $data, $type = 'text' ) {
		switch ( $type ) {
			case 'text':
				return sanitize_text_field( $data );
				
			case 'textarea':
				return sanitize_textarea_field( $data );
				
			case 'email':
				return sanitize_email( $data );
				
			case 'url':
				return esc_url_raw( $data );
				
			case 'int':
				return absint( $data );
				
			case 'float':
				return floatval( $data );
				
			case 'boolean':
				return (bool) $data;
				
			case 'array':
				return is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : [];
				
			case 'json':
				$decoded = json_decode( $data, true );
				return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : false;
				
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Sanitize output data for display
	 * 
	 * @param mixed $data Data to sanitize
	 * @param string $context Output context (html, attribute, url, etc.)
	 * @return mixed Sanitized data
	 */
	public static function sanitize_output( $data, $context = 'html' ) {
		if ( is_null( $data ) ) {
			return '';
		}

		switch ( $context ) {
			case 'html':
				return wp_kses_post( $data );
				
			case 'text':
				return esc_html( $data );
				
			case 'attribute':
				return esc_attr( $data );
				
			case 'url':
				return esc_url( $data );
				
			case 'js':
				return wp_json_encode( $data );
				
			case 'css':
				return esc_attr( $data ); // CSS values should be escaped as attributes
				
			default:
				return esc_html( $data );
		}
	}

	/**
	 * Verify WordPress nonce with additional security checks
	 * 
	 * @param string $nonce Nonce value
	 * @param string $action Nonce action
	 * @return bool True if valid, false otherwise
	 */
	public static function verify_nonce( $nonce, $action ) {
		// Check if nonce exists
		if ( empty( $nonce ) ) {
			return false;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return false;
		}

		// Additional security: Check if request is from admin area for admin actions
		if ( strpos( $action, 'admin' ) !== false && ! is_admin() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check user capabilities with context-aware permissions
	 * 
	 * @param string $capability Required capability
	 * @param array $context Additional context for permission checking
	 * @return bool True if user has permission, false otherwise
	 */
	public static function check_permissions( $capability = 'manage_options', $context = [] ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check basic capability
		if ( ! current_user_can( $capability ) ) {
			return false;
		}

		// Additional context-based checks
		if ( ! empty( $context ) ) {
			// Check if specific action is allowed
			if ( isset( $context['action'] ) ) {
				switch ( $context['action'] ) {
					case 'refresh_cache':
						// Only admins can refresh cache
						return current_user_can( 'manage_options' );
						
					case 'view_data':
						// Editors and above can view data
						return current_user_can( 'edit_pages' );
						
					case 'debug_mode':
						// Only admins in debug mode
						return current_user_can( 'manage_options' ) && defined( 'WP_DEBUG' ) && WP_DEBUG;
						
					default:
						return true;
				}
			}
		}

		return true;
	}

	/**
	 * Log security events
	 * 
	 * @param string $event Event type
	 * @param array $data Event data
	 */
	public static function log_security_event( $event, $data = [] ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'event' => $event,
			'user_ip' => self::get_user_ip(),
			'user_id' => get_current_user_id(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
			'data' => $data
		];

		error_log( 'MKAP Security Event: ' . wp_json_encode( $log_entry ) );
	}

	/**
	 * Get security statistics
	 * 
	 * @return array Security statistics
	 */
	public static function get_security_stats() {
		return [
			'rate_limit_enabled' => true,
			'rate_limit_requests' => self::RATE_LIMIT_REQUESTS,
			'rate_limit_window' => self::RATE_LIMIT_WINDOW,
			'security_headers_enabled' => true,
			'nonce_verification_enabled' => true,
			'input_validation_enabled' => true,
			'output_sanitization_enabled' => true
		];
	}

	/**
	 * Clean up security transients
	 */
	public static function cleanup_security_data() {
		global $wpdb;
		
		// Clean up expired rate limit transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				'_transient_' . self::RATE_LIMIT_PREFIX . '%',
				time()
			)
		);
	}
}
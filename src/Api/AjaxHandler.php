<?php

namespace MohamedKhaledApiPlugin\Api;

use MohamedKhaledApiPlugin\Security\Security;

class AjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_mkap_get_data', array( $this, 'handle_ajax_request' ) );
		add_action( 'wp_ajax_nopriv_mkap_get_data', array( $this, 'handle_ajax_request' ) );
	}

	public function handle_ajax_request() {
		// Verify nonce - use standard WordPress verification for public blocks
		$nonce = Security::validate_input( $_POST['nonce'] ?? '', 'text' );
		if ( ! wp_verify_nonce( $nonce, 'mkap_ajax_nonce' ) ) {
			Security::log_security_event( 'invalid_nonce_ajax', [
				'action' => 'mkap_get_data',
				'nonce' => $nonce
			] );
			wp_send_json_error( array( 
				'message' => __( 'Security verification failed.', 'mohamed-khaled-api-plugin' ),
				'code' => 'invalid_nonce'
			), 403 );
		}

		// Validate and sanitize input
		$force_refresh = Security::validate_input( $_POST['force_refresh'] ?? false, 'boolean' );

		// Check permissions for data access - allow public access with restrictions
		if ( ! is_user_logged_in() ) {
			// Public users can view data but cannot force refresh
			$force_refresh = false;
		}
		// Note: All logged-in users have 'read' capability by default in WordPress,
		// so no additional permission check needed for basic data access

		// Additional security: limit force refresh to admins
		if ( $force_refresh && ! Security::check_permissions( 'manage_options', [ 'action' => 'refresh_cache' ] ) ) {
			Security::log_security_event( 'unauthorized_force_refresh', [
				'user_id' => get_current_user_id()
			] );
			wp_send_json_error( array( 
				'message' => __( 'You do not have permission to force refresh.', 'mohamed-khaled-api-plugin' ),
				'code' => 'unauthorized_refresh'
			), 403 );
		}

		// Fetch data
		$data = ApiClient::get_data( $force_refresh );

		if ( is_wp_error( $data ) ) {
			Security::log_security_event( 'api_error', [
				'error_code' => $data->get_error_code(),
				'error_message' => $data->get_error_message()
			] );
			wp_send_json_error( array( 
				'message' => Security::sanitize_output( $data->get_error_message(), 'text' ),
				'code' => 'api_error'
			) );
		}

		// Log successful data fetch
		Security::log_security_event( 'successful_data_fetch', [
			'force_refresh' => $force_refresh,
			'rows_count' => is_array( $data ) && isset( $data['rows'] ) ? count( $data['rows'] ) : 0
		] );

		wp_send_json_success( $data );
	}

	public static function get_ajax_data() {
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'action'   => 'mkap_get_data',
			'nonce'    => wp_create_nonce( 'mkap_ajax_nonce' ),
		);
	}
}

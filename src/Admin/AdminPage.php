<?php

namespace MohamedKhaledApiPlugin\Admin;

use MohamedKhaledApiPlugin\Api\ApiClient;
use WP_Error;

class AdminPage {

	const PAGE_SLUG = 'mkap-api-data';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_mkap_refresh_data', array( $this, 'handle_refresh_ajax' ) );
		add_action( 'wp_ajax_mkap_clear_cache', array( $this, 'handle_clear_cache_ajax' ) );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Mohamed Khaled API Data', 'mohamed-khaled-api-plugin' ),
			__( 'MK API Data', 'mohamed-khaled-api-plugin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-database',
			30
		);
	}

	public function enqueue_admin_assets( $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'mkap-admin',
			MKAP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MKAP_VERSION
		);

		wp_enqueue_script(
			'mkap-admin',
			MKAP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MKAP_VERSION,
			true
		);

		wp_localize_script( 'mkap-admin', 'mkap_admin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'mkap_admin_nonce' ),
			'i18n'     => array(
				'refreshing' => __( 'Refreshing...', 'mohamed-khaled-api-plugin' ),
				'error'      => __( 'Error refreshing data', 'mohamed-khaled-api-plugin' ),
				'success'    => __( 'Data refreshed successfully', 'mohamed-khaled-api-plugin' ),
			),
		) );
	}

	public function render_admin_page(): void {
		$page_data = $this->prepare_page_data();
		$this->render_admin_template( $page_data );
	}

	/**
	 * Prepare all data needed for the admin page
	 *
	 * @return array Array containing all page data
	 */
	private function prepare_page_data(): array {
		$data = ApiClient::get_data();
		$cache_info = ApiClient::get_cache_info();

		return array(
			'data'         => $data,
			'cache_info'   => $cache_info,
			'status_items' => $this->get_status_items( $data, $cache_info ),
			'tabs'         => $this->get_admin_tabs(),
			'plugin_info'  => $this->get_plugin_info(),
		);
	}

	/**
	 * Get status items for the general tab
	 *
	 * @param mixed $data API data
	 * @param array $cache_info Cache information
	 * @return array Status items
	 */
	private function get_status_items( $data, array $cache_info ): array {
		return array(
			'api_endpoint'    => ApiClient::API_ENDPOINT,
			'cache_status'    => $cache_info['is_cached'],
			'total_records'   => $this->get_record_count( $data ),
			'cache_duration'  => '1 hour',
			'last_updated'    => $this->format_last_updated( $data ),
		);
	}

	/**
	 * Get the number of records from API data
	 *
	 * @param mixed $data API data
	 * @return int Record count
	 */
	private function get_record_count( $data ): int {
		return is_array( $data ) && isset( $data['rows'] ) ? count( $data['rows'] ) : 0;
	}

	/**
	 * Format the last updated time
	 *
	 * @param mixed $data API data
	 * @return string Formatted time string
	 */
	private function format_last_updated( $data ): string {
		if ( is_array( $data ) && isset( $data['last_updated'] ) ) {
			return human_time_diff( strtotime( $data['last_updated'] ), current_time( 'timestamp' ) ) . ' ago';
		}
		return __( 'Never', 'mohamed-khaled-api-plugin' );
	}

	/**
	 * Get admin page tabs configuration
	 *
	 * @return array Tab configuration
	 */
	private function get_admin_tabs(): array {
		return array(
			'general'   => array(
				'id'     => 'general',
				'label'  => __( 'General', 'mohamed-khaled-api-plugin' ),
				'active' => true,
			),
			'data-view' => array(
				'id'     => 'data-view',
				'label'  => __( 'Data View', 'mohamed-khaled-api-plugin' ),
				'active' => false,
			),
			'cache'     => array(
				'id'     => 'cache',
				'label'  => __( 'Cache', 'mohamed-khaled-api-plugin' ),
				'active' => false,
			),
			'settings'  => array(
				'id'     => 'settings',
				'label'  => __( 'Settings', 'mohamed-khaled-api-plugin' ),
				'active' => false,
			),
		);
	}

	/**
	 * Get plugin information
	 *
	 * @return array Plugin information
	 */
	private function get_plugin_info(): array {
		return array(
			'name'        => __( 'Mohamed Khaled API Plugin', 'mohamed-khaled-api-plugin' ),
			'version'     => defined( 'MKAP_VERSION' ) ? MKAP_VERSION : '1.0.0',
			'description' => __( 'A professional WordPress plugin for displaying external API data with caching and performance optimization.', 'mohamed-khaled-api-plugin' ),
		);
	}

	/**
	 * Render the admin page template
	 *
	 * @param array $page_data All page data
	 */
	private function render_admin_template( array $page_data ): void {
		?>
		<div class="mkap-admin-wrap wp-mail-smtp-style">
			<?php $this->render_header( $page_data['plugin_info'] ); ?>
			<?php $this->render_navigation( $page_data['tabs'] ); ?>
			<?php $this->render_main_content( $page_data ); ?>
		</div>
		<?php
	}

	/**
	 * Render the page header section
	 *
	 * @param array $plugin_info Plugin information
	 */
	private function render_header( array $plugin_info ): void {
		?>
		<div class="mkap-header">
			<div class="mkap-logo-section">
				<div class="mkap-plugin-logo">
					<span class="dashicons dashicons-database"></span>
				</div>
				<div class="mkap-plugin-info">
					<h1 class="mkap-plugin-title">
						<?php esc_html_e( 'MK API Data', 'mohamed-khaled-api-plugin' ); ?>
					</h1>
					<p class="mkap-plugin-subtitle">
						<?php esc_html_e( 'a powerful plugin for external API data', 'mohamed-khaled-api-plugin' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the navigation tabs
	 *
	 * @param array $tabs Tab configuration
	 */
	private function render_navigation( array $tabs ): void {
		?>
		<div class="mkap-nav-tabs">
			<ul class="mkap-tab-list">
				<?php foreach ( $tabs as $tab ) : ?>
					<li class="mkap-tab <?php echo $tab['active'] ? 'active' : ''; ?>">
						<a href="#<?php echo esc_attr( $tab['id'] ); ?>" class="mkap-tab-link">
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render the main content area with all tabs
	 *
	 * @param array $page_data All page data
	 */
	private function render_main_content( array $page_data ): void {
		?>
		<div class="mkap-content-container">
			<?php $this->render_general_tab( $page_data['status_items'] ); ?>
			<?php $this->render_data_view_tab( $page_data['data'] ); ?>
			<?php $this->render_cache_tab( $page_data['cache_info'] ); ?>
			<?php $this->render_settings_tab( $page_data['plugin_info'] ); ?>
		</div>
		<?php
	}

	/**
	 * Render the general tab content
	 *
	 * @param array $status_items Status information
	 */
	private function render_general_tab( array $status_items ): void {
		?>
		<div id="general" class="mkap-tab-content active">
			<div class="mkap-section">
				<h2 class="mkap-section-title"><?php esc_html_e( 'API Status Overview', 'mohamed-khaled-api-plugin' ); ?></h2>
				<p class="mkap-section-description"><?php esc_html_e( 'Current status and configuration of the external API connection.', 'mohamed-khaled-api-plugin' ); ?></p>
				
				<?php $this->render_refresh_section(); ?>
				<?php $this->render_status_grid( $status_items ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the data view tab content
	 *
	 * @param mixed $data API data
	 */
	private function render_data_view_tab( $data ): void {
		?>
		<div id="data-view" class="mkap-tab-content" style="display: none;">
			<div class="mkap-section">
				<h2 class="mkap-section-title"><?php esc_html_e( 'External API Data', 'mohamed-khaled-api-plugin' ); ?></h2>
				<p class="mkap-section-description"><?php esc_html_e( 'Data retrieved from the external API endpoint with automatic caching.', 'mohamed-khaled-api-plugin' ); ?></p>
				
				<div class="mkap-data-content">
					<?php $this->render_data_table( $data ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the cache tab content
	 *
	 * @param array $cache_info Cache information
	 */
	private function render_cache_tab( array $cache_info ): void {
		?>
		<div id="cache" class="mkap-tab-content" style="display: none;">
			<div class="mkap-section">
				<h2 class="mkap-section-title"><?php esc_html_e( 'Cache Management', 'mohamed-khaled-api-plugin' ); ?></h2>
				<p class="mkap-section-description"><?php esc_html_e( 'Manage caching settings to optimize performance and reduce API requests.', 'mohamed-khaled-api-plugin' ); ?></p>
				
				<?php $this->render_cache_controls(); ?>
				<?php $this->render_cache_info_grid( $cache_info ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings tab content
	 *
	 * @param array $plugin_info Plugin information
	 */
	private function render_settings_tab( array $plugin_info ): void {
		?>
		<div id="settings" class="mkap-tab-content" style="display: none;">
			<div class="mkap-section">
				<h2 class="mkap-section-title"><?php esc_html_e( 'Plugin Settings', 'mohamed-khaled-api-plugin' ); ?></h2>
				<p class="mkap-section-description"><?php esc_html_e( 'Configure plugin settings and API connection parameters.', 'mohamed-khaled-api-plugin' ); ?></p>
				
				<?php $this->render_api_settings(); ?>
				<?php $this->render_cache_settings(); ?>
				<?php $this->render_plugin_info_card( $plugin_info ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the refresh section
	 */
	private function render_refresh_section(): void {
		?>
		<div class="mkap-form-row mkap-refresh-section">
			<label class="mkap-form-label"><?php esc_html_e( 'Data Refresh', 'mohamed-khaled-api-plugin' ); ?></label>
			<div class="mkap-form-field">
				<button id="mkap-refresh-btn" class="mkap-btn mkap-btn-primary">
					<span class="dashicons dashicons-update"></span>
					<span><?php esc_html_e( 'Refresh Data', 'mohamed-khaled-api-plugin' ); ?></span>
				</button>
				<p class="mkap-field-description"><?php esc_html_e( 'Click to fetch the latest data from the external API endpoint.', 'mohamed-khaled-api-plugin' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the status grid
	 *
	 * @param array $status_items Status information
	 */
	private function render_status_grid( array $status_items ): void {
		?>
		<div class="mkap-status-grid">
			<div class="mkap-status-item">
				<label><?php esc_html_e( 'API Endpoint', 'mohamed-khaled-api-plugin' ); ?></label>
				<span class="mkap-status-value"><?php echo esc_html( $status_items['api_endpoint'] ); ?></span>
			</div>
			<div class="mkap-status-item">
				<label><?php esc_html_e( 'Cache Status', 'mohamed-khaled-api-plugin' ); ?></label>
				<span class="mkap-status-value mkap-status-<?php echo $status_items['cache_status'] ? 'active' : 'inactive'; ?>">
					<?php echo $status_items['cache_status'] ? esc_html__( 'Cached', 'mohamed-khaled-api-plugin' ) : esc_html__( 'Not Cached', 'mohamed-khaled-api-plugin' ); ?>
				</span>
			</div>
			<div class="mkap-status-item">
				<label><?php esc_html_e( 'Total Records', 'mohamed-khaled-api-plugin' ); ?></label>
				<span class="mkap-status-value"><?php echo esc_html( $status_items['total_records'] ); ?></span>
			</div>
			<div class="mkap-status-item">
				<label><?php esc_html_e( 'Cache Duration', 'mohamed-khaled-api-plugin' ); ?></label>
				<span class="mkap-status-value"><?php echo esc_html( $status_items['cache_duration'] ); ?></span>
			</div>
			<div class="mkap-status-item">
				<label><?php esc_html_e( 'Last Updated', 'mohamed-khaled-api-plugin' ); ?></label>
				<span class="mkap-status-value"><?php echo esc_html( $status_items['last_updated'] ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the data table
	 *
	 * @param mixed $data API data
	 */
	private function render_data_table( $data ): void {
		if ( is_wp_error( $data ) ) {
			$this->render_error_notice( $data->get_error_message() );
			return;
		}

		if ( empty( $data ) || ! isset( $data['rows'] ) || empty( $data['rows'] ) ) {
			$this->render_warning_notice( __( 'No data available. Go to the General tab and click "Refresh Data" to fetch data from the API.', 'mohamed-khaled-api-plugin' ) );
			return;
		}

		?>
		<div class="mkap-table-container">
			<table class="mkap-data-table">
				<thead>
					<tr>
						<?php 
						if ( ! empty( $data['headers'] ) ) {
							foreach ( $data['headers'] as $header ) : 
								$safe_header = $this->validate_and_sanitize_column_name( $header );
								if ( false !== $safe_header ) :
							?>
								<th><?php echo esc_html( $safe_header ); ?></th>
							<?php 
								endif;
							endforeach;
							$columns = array_keys( $data['rows'][0] ?? array() );
						} else {
							$columns = array_keys( $data['rows'][0] ?? array() );
							foreach ( $columns as $column ) : 
								$safe_column = $this->validate_and_sanitize_column_name( $column );
								if ( false !== $safe_column ) :
							?>
								<th><?php echo esc_html( $safe_column ); ?></th>
							<?php 
								endif;
							endforeach; 
						}
						?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['rows'] as $index => $row ) : ?>
						<tr>
							<?php foreach ( $columns as $column ) : 
								$safe_column = $this->validate_and_sanitize_column_name( $column );
								if ( false !== $safe_column ) :
							?>
								<td><?php echo esc_html( $row[ $column ] ?? '' ); ?></td>
							<?php 
								endif;
							endforeach; 
							?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render cache controls section
	 */
	private function render_cache_controls(): void {
		?>
		<div class="mkap-form-row">
			<label class="mkap-form-label"><?php esc_html_e( 'Cache Control', 'mohamed-khaled-api-plugin' ); ?></label>
			<div class="mkap-form-field">
				<button id="mkap-clear-cache-btn" class="mkap-btn mkap-btn-secondary">
					<span class="dashicons dashicons-trash"></span>
					<span><?php esc_html_e( 'Clear Cache', 'mohamed-khaled-api-plugin' ); ?></span>
				</button>
				<p class="mkap-field-description"><?php esc_html_e( 'Clear the cached data to force a fresh API request on next page load.', 'mohamed-khaled-api-plugin' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render cache information grid
	 *
	 * @param array $cache_info Cache information
	 */
	private function render_cache_info_grid( array $cache_info ): void {
		?>
		<div class="mkap-cache-info-grid">
			<div class="mkap-cache-detail">
				<strong><?php esc_html_e( 'Cache Key:', 'mohamed-khaled-api-plugin' ); ?></strong>
				<code><?php echo esc_html( $cache_info['cache_key'] ); ?></code>
			</div>
			<div class="mkap-cache-detail">
				<strong><?php esc_html_e( 'Cache Type:', 'mohamed-khaled-api-plugin' ); ?></strong>
				<span>WordPress Transients</span>
			</div>
			<div class="mkap-cache-detail">
				<strong><?php esc_html_e( 'Cache Duration:', 'mohamed-khaled-api-plugin' ); ?></strong>
				<span>1 Hour</span>
			</div>
			<div class="mkap-cache-detail">
				<strong><?php esc_html_e( 'Cache Status:', 'mohamed-khaled-api-plugin' ); ?></strong>
				<span class="mkap-status-indicator <?php echo $cache_info['is_cached'] ? 'success' : 'error'; ?>">
					<?php echo $cache_info['is_cached'] ? esc_html__( 'Active', 'mohamed-khaled-api-plugin' ) : esc_html__( 'Inactive', 'mohamed-khaled-api-plugin' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API settings section
	 */
	private function render_api_settings(): void {
		?>
		<div class="mkap-form-row">
			<label class="mkap-form-label"><?php esc_html_e( 'API Endpoint', 'mohamed-khaled-api-plugin' ); ?></label>
			<div class="mkap-form-field">
				<input type="text" class="mkap-form-input" value="<?php echo esc_attr( ApiClient::API_ENDPOINT ); ?>" readonly />
				<p class="mkap-field-description"><?php esc_html_e( 'The external API endpoint URL (currently read-only).', 'mohamed-khaled-api-plugin' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render cache settings section
	 */
	private function render_cache_settings(): void {
		?>
		<div class="mkap-form-row">
			<label class="mkap-form-label"><?php esc_html_e( 'Cache Duration', 'mohamed-khaled-api-plugin' ); ?></label>
			<div class="mkap-form-field">
				<select class="mkap-form-select" disabled>
					<option value="3600" selected><?php esc_html_e( '1 Hour', 'mohamed-khaled-api-plugin' ); ?></option>
					<option value="1800"><?php esc_html_e( '30 Minutes', 'mohamed-khaled-api-plugin' ); ?></option>
					<option value="7200"><?php esc_html_e( '2 Hours', 'mohamed-khaled-api-plugin' ); ?></option>
				</select>
				<p class="mkap-field-description"><?php esc_html_e( 'How long to cache API responses (currently read-only).', 'mohamed-khaled-api-plugin' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render plugin information card
	 *
	 * @param array $plugin_info Plugin information
	 */
	private function render_plugin_info_card( array $plugin_info ): void {
		?>
		<div class="mkap-form-row">
			<label class="mkap-form-label"><?php esc_html_e( 'Plugin Info', 'mohamed-khaled-api-plugin' ); ?></label>
			<div class="mkap-form-field">
				<div class="mkap-card">
					<h3><?php echo esc_html( $plugin_info['name'] ); ?></h3>
					<p><?php esc_html_e( 'Version:', 'mohamed-khaled-api-plugin' ); ?> <?php echo esc_html( $plugin_info['version'] ); ?></p>
					<p><?php echo esc_html( $plugin_info['description'] ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render error notice
	 *
	 * @param string $message Error message
	 */
	private function render_error_notice( string $message ): void {
		?>
		<div class="mkap-notice mkap-notice-error">
			<div class="mkap-notice-content">
				<span class="dashicons dashicons-warning"></span>
				<p><?php echo esc_html( $message ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render warning notice
	 *
	 * @param string $message Warning message
	 */
	private function render_warning_notice( string $message ): void {
		?>
		<div class="mkap-notice mkap-notice-warning">
			<div class="mkap-notice-content">
				<span class="dashicons dashicons-info"></span>
				<p><?php echo esc_html( $message ); ?></p>
			</div>
		</div>
		<?php
	}

	public function handle_refresh_ajax(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mkap_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mohamed-khaled-api-plugin' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'mohamed-khaled-api-plugin' ) ) );
		}

		ApiClient::clear_cache();
		$data = ApiClient::get_data( true );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		wp_send_json_success( array( 
			'message' => __( 'Data refreshed successfully', 'mohamed-khaled-api-plugin' ),
			'data' => $data 
		) );
	}

	public function handle_clear_cache_ajax(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mkap_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mohamed-khaled-api-plugin' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'mohamed-khaled-api-plugin' ) ) );
		}

		$cleared = ApiClient::clear_cache();

		if ( $cleared ) {
			wp_send_json_success( array( 
				'message' => __( 'Cache cleared successfully', 'mohamed-khaled-api-plugin' )
			) );
		} else {
			wp_send_json_error( array( 
				'message' => __( 'Failed to clear cache', 'mohamed-khaled-api-plugin' )
			) );
		}
	}

	/**
	 * Validate and sanitize column names to prevent XSS attacks
	 *
	 * @param string $column_name Raw column name from API data
	 * @return string|false Safe column name or false if invalid
	 */
	private function validate_and_sanitize_column_name( $column_name ) {
		// Define a whitelist of allowed column name patterns
		$allowed_patterns = array(
			'/^[a-zA-Z_][a-zA-Z0-9_]*$/', // Standard identifier pattern
			'/^[a-zA-Z0-9\s_-]+$/',        // Alphanumeric with spaces, underscores, hyphens
		);

		// Define a blacklist of dangerous patterns
		$dangerous_patterns = array(
			'/<script/i',
			'/javascript:/i',
			'/on\w+=/i',
			'/<\?php/i',
			'/data:/i',
		);

		// First, check against dangerous patterns
		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $column_name ) ) {
				error_log( 'MKAP Security: Blocked dangerous column name: ' . $column_name );
				return false;
			}
		}

		// Check against allowed patterns
		$is_valid = false;
		foreach ( $allowed_patterns as $pattern ) {
			if ( preg_match( $pattern, $column_name ) ) {
				$is_valid = true;
				break;
			}
		}

		if ( ! $is_valid ) {
			error_log( 'MKAP Security: Invalid column name pattern: ' . $column_name );
			return false;
		}

		// Additional length check
		if ( strlen( $column_name ) > 100 ) {
			error_log( 'MKAP Security: Column name too long: ' . $column_name );
			return false;
		}

		// Enhanced sanitization beyond esc_html()
		$safe_column = sanitize_text_field( $column_name );
		$safe_column = preg_replace( '/[^\w\s_-]/', '', $safe_column );
		$safe_column = ucfirst( str_replace( '_', ' ', $safe_column ) );
		
		// Final validation after sanitization
		if ( empty( $safe_column ) || $safe_column !== strip_tags( $safe_column ) ) {
			error_log( 'MKAP Security: Column failed final validation: ' . $column_name );
			return false;
		}

		return $safe_column;
	}
}
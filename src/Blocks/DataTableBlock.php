<?php

namespace MohamedKhaledApiPlugin\Blocks;

use MohamedKhaledApiPlugin\Api\AjaxHandler;

class DataTableBlock {

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
	}

	public function register_block() {
		if ( ! defined( 'MKAP_PLUGIN_DIR' ) ) {
			return;
		}

		$block_json_path = MKAP_PLUGIN_DIR . 'block.json';
		if ( ! file_exists( $block_json_path ) ) {
			return;
		}

		register_block_type(
			$block_json_path,
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	public function enqueue_editor_assets() {
		if ( ! $this->validate_plugin_constants() ) {
			return;
		}

		$script_path = MKAP_PLUGIN_DIR . 'build/index.js';
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'mkap-data-table-editor',
				MKAP_PLUGIN_URL . 'build/index.js',
				array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
				MKAP_VERSION,
				true
			);

			$ajax_data = $this->prepare_ajax_data();
			
			// Provide initial data directly to the editor to avoid AJAX timing issues
			$api_data = \MohamedKhaledApiPlugin\Api\ApiClient::get_data();
			if ( ! is_wp_error( $api_data ) ) {
				$ajax_data['initial_data'] = $api_data;
			}
			
			wp_localize_script( 'mkap-data-table-editor', 'mkap_ajax', $ajax_data );
		}
	}

	public function enqueue_block_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->validate_plugin_constants() ) {
			return;
		}

		$script_path = MKAP_PLUGIN_DIR . 'assets/js/frontend.js';
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'mkap-data-table-frontend',
				MKAP_PLUGIN_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				MKAP_VERSION,
				true
			);

			wp_localize_script( 'mkap-data-table-frontend', 'mkap_ajax', $this->prepare_ajax_data() );
		}

		$style_path = MKAP_PLUGIN_DIR . 'assets/css/frontend.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'mkap-data-table-frontend',
				MKAP_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				MKAP_VERSION
			);
		}
	}

	public function render_block( $attributes ) {
		$column_visibility = array();
		if ( isset( $attributes['columnVisibility'] ) && is_array( $attributes['columnVisibility'] ) ) {
			foreach ( $attributes['columnVisibility'] as $column => $visible ) {
				if ( is_string( $column ) && strlen( $column ) <= 64 ) {
					$column_visibility[ sanitize_key( $column ) ] = (bool) $visible;
				}
			}
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'mkap-data-table-block',
			)
		);

		$block_id = 'mkap-table-' . wp_unique_id();
		$column_visibility_json = wp_json_encode( $column_visibility );

		// Check if we're in the editor context - sanitize GET parameter to prevent XSS
		$context = sanitize_text_field( $_GET['context'] ?? '' );
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && $context === 'edit';
		
		// Get data for server-side rendering in editor
		$api_data = null;
		if ( $is_editor ) {
			$api_data = \MohamedKhaledApiPlugin\Api\ApiClient::get_data();
		}

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; ?>>
			<div id="<?php echo esc_attr( $block_id ); ?>" 
				class="mkap-data-table-container"
				data-column-visibility='<?php echo esc_attr( $column_visibility_json ); ?>'>
				
				<?php if ( $is_editor && ! is_wp_error( $api_data ) && isset( $api_data['rows'] ) && ! empty( $api_data['rows'] ) ) : ?>
					<!-- Server-side rendered table for editor -->
					<div class="mkap-table-wrapper">
						<table class="mkap-data-table">
							<thead>
								<tr>
									<?php 
									$columns = array_keys( $api_data['rows'][0] );
									foreach ( $columns as $column ) :
										if ( ! isset( $column_visibility[ $column ] ) || $column_visibility[ $column ] !== false ) :
									?>
										<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $column ) ) ); ?></th>
									<?php 
										endif;
									endforeach; 
									?>
								</tr>
							</thead>
							<tbody>
								<?php 
								$display_rows = array_slice( $api_data['rows'], 0, 5 ); // Show first 5 rows in editor
								foreach ( $display_rows as $row ) : 
								?>
									<tr>
										<?php foreach ( $columns as $column ) :
											if ( ! isset( $column_visibility[ $column ] ) || $column_visibility[ $column ] !== false ) :
										?>
											<td><?php echo esc_html( $this->sanitize_table_cell_data( $row[ $column ] ?? '' ) ); ?></td>
										<?php 
											endif;
										endforeach; 
										?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( count( $api_data['rows'] ) > 5 ) : ?>
							<p class="mkap-preview-note">
								<?php esc_html_e( 'Preview showing first 5 rows. Full table will display on frontend.', 'mohamed-khaled-api-plugin' ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<!-- Loading/error states for frontend and fallback -->
					<div class="mkap-loading">
						<p><?php esc_html_e( 'Loading data...', 'mohamed-khaled-api-plugin' ); ?></p>
					</div>
					
					<div class="mkap-error" style="display: none;">
						<p><?php esc_html_e( 'Unable to load data. Please try again.', 'mohamed-khaled-api-plugin' ); ?></p>
					</div>
					
					<div class="mkap-table-wrapper" style="display: none;">
						<!-- Table will be inserted here by JavaScript -->
					</div>
				<?php endif; ?>
				
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate that required plugin constants are defined
	 *
	 * @return bool True if all constants are defined
	 */
	private function validate_plugin_constants() {
		return defined( 'MKAP_PLUGIN_URL' ) && defined( 'MKAP_VERSION' ) && defined( 'MKAP_PLUGIN_DIR' );
	}

	/**
	 * Prepare AJAX data with i18n strings
	 *
	 * @return array AJAX configuration data
	 */
	private function prepare_ajax_data() {
		$ajax_data = AjaxHandler::get_ajax_data();
		$ajax_data['i18n'] = array(
			'no_data' => __( 'No data available.', 'mohamed-khaled-api-plugin' ),
			'loading' => __( 'Loading data...', 'mohamed-khaled-api-plugin' ),
			'error'   => __( 'Error loading data. Please try again.', 'mohamed-khaled-api-plugin' ),
		);

		return $ajax_data;
	}

	/**
	 * Sanitize table cell data to prevent XSS and ensure safe output
	 *
	 * @param mixed $data The raw data from API
	 * @return string Sanitized data safe for HTML output
	 */
	private function sanitize_table_cell_data( $data ) {
		// Convert to string first
		$data = (string) $data;
		
		// Define allowed columns that might contain HTML (none in this case)
		$allowed_html_columns = array(); // Add column names that should allow basic HTML
		
		// For now, strip all HTML and return plain text
		// This can be expanded if specific columns need HTML support
		$sanitized = wp_strip_all_tags( $data );
		
		// Additional validation: limit length to prevent display issues
		if ( strlen( $sanitized ) > 200 ) {
			$sanitized = substr( $sanitized, 0, 197 ) . '...';
		}
		
		return $sanitized;
	}
}
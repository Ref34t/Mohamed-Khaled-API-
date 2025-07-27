<?php

namespace MohamedKhaledApiPlugin;

use MohamedKhaledApiPlugin\Admin\AdminPage;
use MohamedKhaledApiPlugin\Api\AjaxHandler;
use MohamedKhaledApiPlugin\Blocks\DataTableBlock;
use MohamedKhaledApiPlugin\CLI\Commands;
use MohamedKhaledApiPlugin\Security\Security;

class Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		
		// Initialize security measures
		Security::init();
		
		new AjaxHandler();
		if ( is_admin() ) {
			new AdminPage();
		}
		new DataTableBlock();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register_commands();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'mohamed-khaled-api-plugin',
			false,
			dirname( MKAP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public static function activate() {
		flush_rewrite_rules();
	}

	public static function deactivate() {
		// Clean up safely without causing errors
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'mkap_api_data' );
		}
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}
}

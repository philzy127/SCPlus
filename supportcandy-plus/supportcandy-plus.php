<?php
/**
 * Plugin Name: SupportCandy Plus
 * Description: A collection of enhancements for the SupportCandy plugin.
 * Version: 2.3.0
 * Author: Jules
 * Author URI: https://example.com
 * Text Domain: supportcandy-plus
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class SupportCandy_Plus {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	private function define_constants() {
		define( 'SCP_PLUGIN_FILE', __FILE__ );
		define( 'SCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'SCP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
		define( 'SCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'SCP_VERSION', '2.3.0' );
	}

	private function includes() {
		include_once SCP_PLUGIN_PATH . 'includes/class-scp-admin-settings.php';
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function on_plugins_loaded() {
		// Main initialization logic.
	}

	public function get_custom_field_id_by_name( $field_name ) {
		global $wpdb;
		if ( empty( $field_name ) ) {
			return 0;
		}
		// Correctly construct the table name using the WordPress prefix.
		$table_name = $wpdb->prefix . 'psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return 0;
		}
		$field_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table_name}` WHERE name = %s",
				$field_name
			)
		);
		return $field_id ? (int) $field_id : 0;
	}

	public function enqueue_scripts() {
		$options = get_option( 'scp_settings', [] );

		wp_register_script(
			'supportcandy-plus-frontend',
			SCP_PLUGIN_URL . 'assets/js/supportcandy-plus-frontend.js',
			array( 'jquery' ),
			SCP_VERSION,
			true
		);

		$localized_data = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpsc_get_individual_ticket' ),
			'features' => [
				'hover_card'         => [
					'enabled' => ! empty( $options['enable_hover_card'] ),
					'delay'   => ! empty( $options['hover_card_delay'] ) ? absint( $options['hover_card_delay'] ) : 1000,
				],
				'hide_empty_columns' => [
					'enabled' => ! empty( $options['enable_hide_empty_columns'] ),
				],
				'ticket_type_hiding' => [
					'enabled'       => ! empty( $options['enable_ticket_type_hiding'] ),
					'field_id'      => $this->get_custom_field_id_by_name( ! empty( $options['ticket_type_custom_field_name'] ) ? $options['ticket_type_custom_field_name'] : '' ),
					'types_to_hide' => ! empty( $options['ticket_types_to_hide'] ) ? array_map( 'trim', explode( "\n", $options['ticket_types_to_hide'] ) ) : [],
				],
				'conditional_hiding' => [
					'enabled' => ! empty( $options['enable_conditional_hiding'] ),
					'rules'   => isset( $options['conditional_hiding_rules'] ) ? $options['conditional_hiding_rules'] : [],
					'columns' => $this->get_supportcandy_columns(),
				],
			],
		];

		wp_localize_script( 'supportcandy-plus-frontend', 'scp_settings', $localized_data );
		wp_enqueue_script( 'supportcandy-plus-frontend' );
	}

	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'toplevel_page_supportcandy-plus' !== $hook_suffix ) {
			return;
		}
		// Enqueue Select2 styles and scripts, which are bundled with WordPress.
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );

		wp_enqueue_style(
			'supportcandy-plus-admin',
			SCP_PLUGIN_URL . 'assets/admin/css/supportcandy-plus-admin.css',
			array( 'select2' ), // Add select2 as a dependency
			SCP_VERSION
		);
		wp_enqueue_script(
			'supportcandy-plus-admin',
			SCP_PLUGIN_URL . 'assets/admin/js/supportcandy-plus-admin.js',
			array( 'jquery', 'select2' ), // Add select2 as a dependency
			SCP_VERSION,
			true
		);
	}

	public function get_supportcandy_columns() {
		global $wpdb;
		$columns = []; // Start with an empty array.

		// Correctly construct the table name using the WordPress prefix.
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			// Correctly select the SLUG for the key and the NAME for the display text.
			$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					// The key is the slug, and the value is the display name.
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}
		return $columns;
	}
}

function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

$GLOBALS['supportcandy_plus'] = supportcandy_plus();
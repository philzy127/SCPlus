<?php
/**
 * Plugin Name: SupportCandy Plus
 * Description: A collection of enhancements for the SupportCandy plugin.
 * Version: 2.3.1
 * Author: StackBoost
 * Author URI: https://stackBoost.net
 * Text Domain: supportcandy-plus
 * Domain Path: /languages
 * Requires Plugins:  supportcandy
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
		define( 'SCP_VERSION', '2.3.1' );
	}

	private function includes() {
		include_once SCP_PLUGIN_PATH . 'includes/class-scp-admin-settings.php';
		include_once SCP_PLUGIN_PATH . 'includes/class-scp-queue-macro.php';
		SCP_Queue_Macro::get_instance();

		// After Ticket Survey Module
		include_once SCP_PLUGIN_PATH . 'includes/modules/after-ticket-survey/class-scp-ats.php';
		if ( class_exists( 'SCP_After_Ticket_Survey' ) ) {
			SCP_After_Ticket_Survey::get_instance();
		}
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
		// Use the literal table name as specified by the user.
		$table_name = 'wpya_psmsc_custom_fields';
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
					'enabled' => ! empty( $options['enable_right_click_card'] ),
				],
				'hide_empty_columns' => [
					'enabled' => ! empty( $options['enable_hide_empty_columns'] ),
					'hide_priority' => ! empty( $options['enable_hide_priority_column'] ),
				],
				'hide_reply_close' => [
					'enabled' => ! empty( $options['hide_reply_close_for_users'] ),
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
				'after_hours_notice' => [
					'enabled'          => ! empty( $options['enable_after_hours_notice'] ),
					'start_hour'       => ! empty( $options['after_hours_start'] ) ? (int) $options['after_hours_start'] : 17,
					'end_hour'         => ! empty( $options['before_hours_end'] ) ? (int) $options['before_hours_end'] : 8,
					'include_weekends' => ! empty( $options['include_all_weekends'] ),
					'holidays'         => ! empty( $options['holidays'] ) ? array_map( 'trim', explode( "\n", $options['holidays'] ) ) : [],
					'message'          => ! empty( $options['after_hours_message'] ) ? wpautop( wp_kses_post( $options['after_hours_message'] ) ) : '',
				],
			],
		];

		wp_localize_script( 'supportcandy-plus-frontend', 'scp_settings', $localized_data );
		wp_enqueue_script( 'supportcandy-plus-frontend' );
	}

	public function enqueue_admin_scripts( $hook_suffix ) {
		$allowed_hooks = [
			'toplevel_page_supportcandy-plus',
			'supportcandy-plus_page_scp-conditional-hiding',
			'supportcandy-plus_page_scp-queue-macro',
			'supportcandy-plus_page_scp-after-hours',
			'supportcandy-plus_page_scp-how-to-use',
			'supportcandy-plus_page_scp-ats-manage-questions',
			'supportcandy-plus_page_scp-ats-view-results',
			'supportcandy-plus_page_scp-ats-settings',
		];

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'supportcandy-plus-admin',
			SCP_PLUGIN_URL . 'assets/admin/css/supportcandy-plus-admin.css',
			array(),
			SCP_VERSION
		);
		wp_enqueue_script(
			'supportcandy-plus-admin',
			SCP_PLUGIN_URL . 'assets/admin/js/supportcandy-plus-admin.js',
			array( 'jquery' ),
			SCP_VERSION,
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			'supportcandy-plus-admin',
			'scp_admin_ajax',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'scp_test_queue_macro_nonce' ),
			]
		);
	}

	public function get_supportcandy_columns() {
		global $wpdb;
		$columns = [];

		// Use the literal table name as specified by the user.
		$custom_fields_table = 'wpya_psmsc_custom_fields';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}
		asort( $columns ); // Sort the columns alphabetically by name.
		return $columns;
	}
}

function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

$GLOBALS['supportcandy_plus'] = supportcandy_plus();
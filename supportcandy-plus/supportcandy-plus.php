<?php
/**
 * Plugin Name: SupportCandy Plus!
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
		add_action( 'init', array( $this, 'apply_date_time_formats' ) );
	}

	public function on_plugins_loaded() {
		// Main initialization logic.
	}

	/**
	 * Helper function for logging debug messages to a file.
	 */
	private function log_message( $message ) {
		$log_file = SCP_PLUGIN_PATH . 'debug.log';
		$timestamp = wp_date( 'Y-m-d H:i:s' );
		$log_entry = sprintf( "[%s] %s\n", $timestamp, print_r( $message, true ) );
		file_put_contents( $log_file, $log_entry, FILE_APPEND );
	}

	/**
	 * Apply the date/time formatting rules.
	 */
	public function apply_date_time_formats() {
		$this->log_message( 'Running apply_date_time_formats...' );
		$options = get_option( 'scp_settings', [] );
		if ( empty( $options['enable_date_time_formatting'] ) ) {
			$this->log_message( 'Date formatting feature is disabled. Aborting.' );
			return;
		}
		$this->log_message( 'Date formatting feature is enabled.' );
		$rules = isset( $options['date_format_rules'] ) && is_array( $options['date_format_rules'] ) ? $options['date_format_rules'] : [];

		if ( empty( $rules ) ) {
			$this->log_message( 'No date formatting rules found. Aborting.' );
			return;
		}
		$this->log_message( 'Found ' . count( $rules ) . ' rules.' );

		// Store rules in a more accessible format.
		$this->formatted_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['column'] ) && 'default' !== $rule['format_type'] ) {
				$this->formatted_rules[ $rule['column'] ] = $rule;
			}
		}

		if ( empty( $this->formatted_rules ) ) {
			return;
		}

		// Add a single filter for all datetime custom fields.
		add_filter( 'wpsc_ticket_field_val_datetime', array( $this, 'format_date_time_callback' ), 10, 4 );

		// Add filters for standard fields.
		$standard_fields = [ 'date_created', 'last_reply_on', 'date_closed', 'date_updated' ];
		foreach ( $standard_fields as $field ) {
			if ( isset( $this->formatted_rules[ $field ] ) ) {
				add_filter( 'wpsc_ticket_field_val_' . $field, array( $this, 'format_date_time_callback' ), 10, 4 );
			}
		}
	}

	/**
	 * Callback function to format the date/time value.
	 */
	public function format_date_time_callback( $value, $cf, $ticket, $module ) {

		$this->log_message( '---' );
		$this->log_message( 'Filter triggered. Initial value: ' . $value );

		// CONTEXT CHECK: Exit if not in a valid ticket list view.
		$is_admin_list = is_admin() && get_current_screen() && get_current_screen()->id === 'toplevel_page_wpsc-tickets';
		$is_frontend_list = isset( $_POST['is_frontend'] ) && $_POST['is_frontend'] === '1';

		if ( ! $is_admin_list && ! $is_frontend_list ) {
			$this->log_message( 'Context is not a valid ticket list. Bailing.' );
			return $value;
		}
		$this->log_message( 'Context is a valid ticket list.' );

		// GET SLUG: Reliably get the field slug from the filter name.
		$current_filter = current_filter();
		if ( strpos( $current_filter, 'wpsc_ticket_field_val_' ) === 0 ) {
			$field_slug = substr( $current_filter, 22 );
		} else {
			$this->log_message( 'Could not determine field slug from filter name. Bailing.' );
			return $value;
		}

		// For datetime custom fields, the slug is 'datetime', but we need the specific cf slug.
		if ( 'datetime' === $field_slug && is_object( $cf ) ) {
			$field_slug = $cf->slug;
		}
		$this->log_message( 'Field Slug: ' . $field_slug );

		// FIND RULE: Check if a rule exists for this slug.
		if ( ! isset( $this->formatted_rules[ $field_slug ] ) ) {
			$this->log_message( 'No rule found for this slug. Bailing.' );
			return $value;
		}
		$rule = $this->formatted_rules[ $field_slug ];
		$this->log_message( 'Rule found: ' . print_r( $rule, true ) );

		// GET DATE OBJECT: Get the raw date property from the ticket.
		$date_object = $ticket->{$field_slug};

		// VALIDATE DATE OBJECT: The most critical step. If it's not a valid DateTime object, bail.
		if ( ! ( $date_object instanceof DateTime ) ) {
			$this->log_message( 'Value is not a valid DateTime object. Bailing.' );
			return $value;
		}

		// APPLY FORMAT: If all checks pass, format the date.
		$timestamp = $date_object->getTimestamp();
		$new_value = $value;
		switch ( $rule['format_type'] ) {
			case 'date_only':
				$new_value = wp_date( get_option( 'date_format' ), $timestamp );
				break;
			case 'time_only':
				$new_value = wp_date( get_option( 'time_format' ), $timestamp );
				break;
			case 'date_and_time':
				$new_value = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
				break;
			case 'custom':
				if ( ! empty( $rule['custom_format'] ) ) {
					$new_value = wp_date( $rule['custom_format'], $timestamp );
				}
				break;
		}

		$this->log_message( 'Formatting successful. New value: ' . $new_value );
		return $new_value;
	}


	public function get_custom_field_id_by_name( $field_name ) {
		global $wpdb;
		if ( empty( $field_name ) ) {
			return 0;
		}
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
			'supportcandy-plus_page_scp-date-time-formatting',
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

		if ( 'supportcandy-plus_page_scp-date-time-formatting' === $hook_suffix ) {
			wp_enqueue_script(
				'scp-date-time-formatting',
				SCP_PLUGIN_URL . 'assets/admin/js/scp-date-time-formatting.js',
				array( 'jquery' ),
				SCP_VERSION,
				true
			);
		}

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

		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';

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

	/**
	 * Get all date-based columns for the settings page.
	 */
	public function get_date_columns() {
		global $wpdb;
		$columns = [];

		// Standard SupportCandy date fields.
		$standard_fields = [
			'date_created' => __( 'Date Created', 'supportcandy-plus' ),
			'last_reply_on'   => __( 'Last Reply', 'supportcandy-plus' ),
			'date_closed'  => __( 'Date Closed', 'supportcandy-plus' ),
			'date_updated' => __( 'Date Updated', 'supportcandy-plus' ),
		];

		// Get custom fields of type 'datetime'.
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT slug, name FROM `{$custom_fields_table}` WHERE type = %s",
					'datetime'
				),
				ARRAY_A
			);
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}

		// Merge and sort.
		$all_columns = array_merge( $standard_fields, $columns );
		asort( $all_columns );

		return $all_columns;
	}
}

function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

$GLOBALS['supportcandy_plus'] = supportcandy_plus();

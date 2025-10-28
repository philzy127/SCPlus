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
	private $custom_field_data_cache = null;

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
		include_once SCP_PLUGIN_PATH . 'includes/class-scp-utm.php';
		SCP_UTM::get_instance();

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
	 * This is intentionally pointed to the UTM log file for unified debugging.
	 */
	private function log_message( $message ) {
		$log_file = SCP_PLUGIN_PATH . 'scp-utm-debug.log';
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

		// Add filters for all potential standard fields. The callback will check if a rule exists.
		$standard_fields = [ 'date_created', 'last_reply_on', 'date_closed', 'date_updated' ];
		foreach ( $standard_fields as $field ) {
			add_filter( 'wpsc_ticket_field_val_' . $field, array( $this, 'format_date_time_callback' ), 10, 4 );
		}
	}

	/**
	 * Callback function to format the date/time value.
	 */
	public function format_date_time_callback( $value, $cf, $ticket, $module ) {

		// CONTEXT CHECK
		$is_admin_list    = is_admin() && get_current_screen() && get_current_screen()->id === 'toplevel_page_wpsc-tickets';
		$is_frontend_list = isset( $_POST['is_frontend'] ) && '1' === $_POST['is_frontend'];
		if ( ! $is_admin_list && ! $is_frontend_list ) {
			return $value;
		}

		// GET SLUG
		$current_filter = current_filter();
		$field_slug     = null;
		if ( strpos( $current_filter, 'wpsc_ticket_field_val_datetime' ) !== false ) {
			if ( is_object( $cf ) && isset( $cf->slug ) ) {
				$field_slug = $cf->slug;
			}
		} else {
			if ( strpos( $current_filter, 'wpsc_ticket_field_val_' ) === 0 ) {
				$field_slug = substr( $current_filter, 22 );
			}
		}

		if ( ! $field_slug ) {
			return $value;
		}

		// FIND RULE
		if ( ! isset( $this->formatted_rules[ $field_slug ] ) ) {
			return $value;
		}
		$rule = $this->formatted_rules[ $field_slug ];

		// THE OFFICIAL METHOD: Change the display mode on the field object.
		if ( is_object( $cf ) ) {
			$cf->date_display_as = 'date';
		}

		// GET AND VALIDATE DATE OBJECT
		// Note: The new documentation confirms the property name matches the slug,
		// e.g., $ticket->last_reply_on. The previous special case was incorrect.
		$date_object = $ticket->{$field_slug};
		if ( ! ( $date_object instanceof DateTime ) ) {
			return $value;
		}

		// APPLY FORMAT
		$timestamp         = $date_object->getTimestamp();
		$new_value         = $value;
		$short_date_format = 'm/d/Y';
		$long_date_format  = 'F j, Y';
		$time_format       = get_option( 'time_format' );
		$date_format       = ! empty( $rule['use_long_date'] ) ? $long_date_format : $short_date_format;

		if ( ! empty( $rule['show_day_of_week'] ) ) {
			$day_prefix  = ! empty( $rule['use_long_date'] ) ? 'l, ' : 'D, ';
			$date_format = $day_prefix . $date_format;
		}

		switch ( $rule['format_type'] ) {
			case 'date_only':
				$new_value = wp_date( $date_format, $timestamp );
				break;
			case 'time_only':
				$new_value = wp_date( $time_format, $timestamp );
				break;
			case 'date_and_time':
				$new_value = wp_date( $date_format . ' ' . $time_format, $timestamp );
				break;
			case 'custom':
				if ( ! empty( $rule['custom_format'] ) ) {
					$new_value = wp_date( $rule['custom_format'], $timestamp );
				}
				break;
		}

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

	/**
	 * Get all standard and custom columns for the UTM settings page.
	 */
	public function get_scp_utm_columns() {
		global $wpdb;
		$columns = [];

		// Standard SupportCandy fields.
		$standard_fields = [
			'id'              => __( 'Ticket ID', 'supportcandy-plus' ),
			'subject'         => __( 'Subject', 'supportcandy-plus' ),
			'description'     => __( 'Description', 'supportcandy-plus' ),
			'status'          => __( 'Status', 'supportcandy-plus' ),
			'category'        => __( 'Category', 'supportcandy-plus' ),
			'priority'        => __( 'Priority', 'supportcandy-plus' ),
			'customer'        => __( 'Customer', 'supportcandy-plus' ),
			'name'            => __( 'Customer Name', 'supportcandy-plus' ),
			'email'           => __( 'Customer Email', 'supportcandy-plus' ),
			'agent_created'   => __( 'Created By Agent', 'supportcandy-plus' ),
			'assigned_agent'  => __( 'Assigned Agent', 'supportcandy-plus' ),
			'prev_assignee'   => __( 'Previous Assignee', 'supportcandy-plus' ),
			'usergroups'      => __( 'Usergroups', 'supportcandy-plus' ),
			'date_created'    => __( 'Date Created', 'supportcandy-plus' ),
			'last_reply_on'   => __( 'Last Reply On', 'supportcandy-plus' ),
			'last_reply_by'   => __( 'Last Reply By', 'supportcandy-plus' ),
			'last_reply_source' => __( 'Last Reply Source', 'supportcandy-plus' ),
			'date_closed'     => __( 'Date Closed', 'supportcandy-plus' ),
			'date_updated'    => __( 'Date Updated', 'supportcandy-plus' ),
			'source'          => __( 'Source', 'supportcandy-plus' ),
			'ip_address'      => __( 'IP Address', 'supportcandy-plus' ),
			'os'              => __( 'Operating System', 'supportcandy-plus' ),
			'browser'         => __( 'Browser', 'supportcandy-plus' ),
			'tags'            => __( 'Tags', 'supportcandy-plus' ),
			'add_recipients'  => __( 'Additional Recipients', 'supportcandy-plus' ),
			'rating'          => __( 'Rating', 'supportcandy-plus' ),
			'sf_date'         => __( 'Satisfaction Survey Date', 'supportcandy-plus' ),
			'sf_feedback'     => __( 'Satisfaction Survey Feedback', 'supportcandy-plus' ),
		];

		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );
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

	/**
	 * Get all custom fields with their types and options.
	 * This is a more comprehensive data fetcher than get_supportcandy_columns.
	 * Results are cached for the duration of the request.
	 */
	public function get_all_custom_field_data() {
		if ( ! is_null( $this->custom_field_data_cache ) ) {
			return $this->custom_field_data_cache;
		}

		global $wpdb;
		$fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		$options_table = $wpdb->prefix . 'psmsc_options';
		$results = [];

		$query = "
            SELECT
                cf.id,
                cf.slug,
                cf.name,
                cf.type,
                opt.id as option_id,
                opt.name as option_name
            FROM {$fields_table} AS cf
            LEFT JOIN {$options_table} AS opt ON cf.id = opt.custom_field
            ORDER BY cf.slug, opt.name ASC
        ";

		$db_results = $wpdb->get_results( $query, ARRAY_A );

		if ( $db_results ) {
			foreach ( $db_results as $row ) {
				$slug = $row['slug'];
				if ( ! isset( $results[ $slug ] ) ) {
					$results[ $slug ] = [
						'id'      => (int) $row['id'],
						'slug'    => $slug,
						'name'    => $row['name'],
						'type'    => $row['type'],
						'options' => [],
					];
				}
				if ( ! empty( $row['option_id'] ) ) {
					$results[ $slug ]['options'][ $row['option_id'] ] = $row['option_name'];
				}
			}
		}

		$this->custom_field_data_cache = $results;
		return $this->custom_field_data_cache;
	}
}

function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

$GLOBALS['supportcandy_plus'] = supportcandy_plus();
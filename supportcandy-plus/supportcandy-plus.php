<?php
/**
 * Plugin Name: SupportCandy Plus
 * Description: An addon for SupportCandy that adds advanced features.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: supportcandy-plus
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SCP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The main SupportCandy_Plus class.
 */
final class SupportCandy_Plus {

	/**
	 * The single instance of the class.
	 *
	 * @var SupportCandy_Plus
	 */
	private static $instance = null;

	/**
	 * Settings cache.
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Custom field data cache.
	 *
	 * @var array|null
	 */
	private $custom_field_data_cache = null;

	/**
	 * Main SupportCandy_Plus Instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		add_action( 'init', array( $this, 'load_plugin' ) );
	}

	/**
	 * Check if SupportCandy is active.
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'SupportCandy' ) ) {
			add_action( 'admin_notices', array( $this, 'dependency_missing_notice' ) );
		}
	}

	/**
	 * Display a notice if SupportCandy is not active.
	 */
	public function dependency_missing_notice() {
		echo '<div class="error"><p>' . esc_html__( 'SupportCandy Plus requires the SupportCandy plugin to be installed and active.', 'supportcandy-plus' ) . '</p></div>';
	}


	/**
	 * Load the plugin's features.
	 */
	public function load_plugin() {
		if ( ! class_exists( 'SupportCandy' ) ) {
			return; // Don't load if the base plugin isn't active.
		}

		// Load text domain for localization.
		load_plugin_textdomain( 'supportcandy-plus', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Load admin settings.
		if ( is_admin() ) {
			require_once SCP_PLUGIN_PATH . 'includes/class-scp-admin-settings.php';
			require_once SCP_PLUGIN_PATH . 'assets/admin/js/admin-scripts-loader.php'; // Correctly load admin scripts.
		}

		// Load features based on settings.
		$this->load_features();
	}


	/**
	 * Load features based on saved settings.
	 */
	private function load_features() {
		$settings = $this->get_settings();

		if ( ! empty( $settings['enable_right_click_card'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/right-click-card.php';
		}
		if ( ! empty( $settings['enable_hide_empty_columns'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/hide-empty-columns.php';
		}
		if ( ! empty( $settings['enable_hide_priority_column'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/hide-priority-column.php';
		}
		if ( ! empty( $settings['hide_reply_close_for_users'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/hide-reply-close.php';
		}
		if ( ! empty( $settings['enable_ticket_type_hiding'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/ticket-type-hiding.php';
		}
		if ( ! empty( $settings['enable_conditional_hiding'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/conditional-hiding.php';
		}
		if ( ! empty( $settings['enable_after_hours_notice'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/after-hours-notice.php';
		}
		if ( ! empty( $settings['enable_queue_macro'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/queue-macro.php';
		}
		if ( ! empty( $settings['enable_date_time_formatting'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/date-time-formatting.php';
		}
		if ( ! empty( $settings['enable_utm'] ) ) {
			require_once SCP_PLUGIN_PATH . 'includes/features/unified-ticket-macro.php';
		}
	}

	/**
	 * Get a setting from the options table.
	 */
	public function get_settings() {
		if ( null === $this->settings_cache ) {
			$this->settings_cache = get_option( 'scp_settings', [] );
		}
		return $this->settings_cache;
	}

	/**
	 * Get all SupportCandy custom fields.
	 */
	public function get_supportcandy_columns() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'psmsc_custom_fields';
		$columns    = [];
		$results    = $wpdb->get_results( "SELECT slug, name FROM {$table_name} WHERE is_active = 1 ORDER BY name ASC" );
		if ( $results ) {
			foreach ( $results as $row ) {
				$columns[ $row->slug ] = $row->name;
			}
		}
		return $columns;
	}

	/**
	 * Get standard and custom columns for date formatting.
	 */
	public function get_date_columns() {
		$standard_columns = [
			'date_created'   => __( 'Date Created', 'supportcandy-plus' ),
			'last_reply_on'  => __( 'Last Reply On', 'supportcandy-plus' ),
			'date_closed'    => __( 'Date Closed', 'supportcandy-plus' ),
			'first_response' => __( 'First Response', 'supportcandy-plus' ),
		];

		// Fetch only custom fields of type 'date'.
		global $wpdb;
		$table_name  = $wpdb->prefix . 'psmsc_custom_fields';
		$custom_cols = [];
		$results     = $wpdb->get_results( "SELECT slug, name FROM {$table_name} WHERE type = 'date' AND is_active = 1 ORDER BY name ASC" );
		if ( $results ) {
			foreach ( $results as $row ) {
				$custom_cols[ $row->slug ] = $row->name;
			}
		}

		return array_merge( $standard_columns, $custom_cols );
	}

	/**
	 * Get all standard and custom columns for the UTM feature.
	 * This is a dedicated function to avoid breaking other features.
	 */
	public function get_scp_utm_columns() {
		$standard_fields = [
			'id'             => __( 'Ticket ID', 'supportcandy-plus' ),
			'subject'        => __( 'Subject', 'supportcandy-plus' ),
			'status'         => __( 'Status', 'supportcandy-plus' ),
			'category'       => __( 'Category', 'supportcandy-plus' ),
			'priority'       => __( 'Priority', 'supportcandy-plus' ),
			'customer'       => __( 'Customer Name', 'supportcandy-plus' ),
			'customer_email' => __( 'Customer Email', 'supportcandy-plus' ),
			'date_created'   => __( 'Date Created', 'supportcandy-plus' ),
			'agent_created'  => __( 'Agent Created', 'supportcandy-plus' ),
			'agent_assigned' => __( 'Agent Assigned', 'supportcandy-plus' ),
			'last_reply_by'  => __( 'Last Reply By', 'supportcandy-plus' ),
			'last_reply_on'  => __( 'Last Reply On', 'supportcandy-plus' ),
			'date_closed'    => __( 'Date Closed', 'supportcandy-plus' ),
			'created_by_type' => __( 'Created By Type', 'supportcandy-plus' ),
			'source'         => __( 'Source', 'supportcandy-plus' ),
			'ip_address'     => __( 'IP Address', 'supportcandy-plus' ),
			'os'             => __( 'Operating System', 'supportcandy-plus' ),
			'browser'        => __( 'Browser', 'supportcandy-plus' ),
		];

		$custom_fields = $this->get_supportcandy_columns();
		$all_columns   = array_merge( $standard_fields, $custom_fields );
		asort( $all_columns );
		return $all_columns;
	}

	/**
	 * Efficiently get all custom field data, including names, slugs, and options.
	 * Caches the result to prevent multiple queries within a single request.
	 *
	 * @return array An associative array of custom fields, indexed by slug.
	 *               Each field contains its id, name, slug, and an 'options' array.
	 */
	public function get_all_custom_field_data() {
		if ( null !== $this->custom_field_data_cache ) {
			return $this->custom_field_data_cache;
		}

		global $wpdb;
		$cf_table     = $wpdb->prefix . 'psmsc_custom_fields';
		$options_table = $wpdb->prefix . 'psmsc_options';

		$fields = [];

		// Step 1: Get all active custom fields.
		$custom_fields = $wpdb->get_results( "SELECT id, name, slug FROM {$cf_table} WHERE is_active = 1" );

		if ( ! $custom_fields ) {
			$this->custom_field_data_cache = [];
			return [];
		}

		$field_ids = wp_list_pluck( $custom_fields, 'id' );

		// Step 2: Get all options for these fields in a single query.
		$options_sql = "SELECT custom_field, option_value, option_name FROM {$options_table} WHERE custom_field IN (" . implode( ',', array_map( 'absint', $field_ids ) ) . ')';
		$all_options = $wpdb->get_results( $options_sql );

		// Detailed logging for diagnostics.
		$log_file = SCP_PLUGIN_PATH . 'scp-utm-debug.log';
		$log_message = "--------------------------------\n";
		$log_message .= "Timestamp: " . date( 'Y-m-d H:i:s' ) . "\n";
		$log_message .= "Function: get_all_custom_field_data\n";
		$log_message .= "Custom Fields Query: " . $wpdb->last_query . "\n";
		$log_message .= "Custom Fields Found: " . count( $custom_fields ) . "\n";
		$log_message .= "Options Query: " . $options_sql . "\n";
		$log_message .= "Options Found: " . count( $all_options ) . "\n";
		error_log( $log_message, 3, $log_file );

		// Step 3: Map options to their respective fields.
		$options_map = [];
		foreach ( $all_options as $option ) {
			if ( ! isset( $options_map[ $option->custom_field ] ) ) {
				$options_map[ $option->custom_field ] = [];
			}
			$options_map[ $option->custom_field ][ $option->option_value ] = $option->option_name;
		}

		// Step 4: Combine field data with its options.
		foreach ( $custom_fields as $field ) {
			$fields[ $field->slug ] = [
				'id'      => $field->id,
				'name'    => $field->name,
				'slug'    => $field->slug,
				'options' => isset( $options_map[ $field->id ] ) ? $options_map[ $field->id ] : [],
			];
		}

		$this->custom_field_data_cache = $fields;
		return $this->custom_field_data_cache;
	}
}

/**
 * Returns the main instance of SupportCandy_Plus.
 */
function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

// Initialize the plugin.
supportcandy_plus();
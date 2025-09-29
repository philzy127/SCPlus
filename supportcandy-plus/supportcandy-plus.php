<?php
/**
 * Plugin Name: SupportCandy Plus
 * Description: A collection of enhancements for the SupportCandy plugin.
 * Version: 2.0.0
 * Author: Jules
 * Author URI: https://example.com
 * Text Domain: supportcandy-plus
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class SupportCandy_Plus {

	/**
	 * The single instance of the class.
	 *
	 * @var SupportCandy_Plus
	 */
	private static $instance = null;

	/**
	 * Main SupportCandy_Plus Instance.
	 *
	 * Ensures only one instance of SupportCandy_Plus is loaded or can be loaded.
	 *
	 * @static
	 * @return SupportCandy_Plus - Main instance.
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
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define constants.
	 */
	private function define_constants() {
		define( 'SCP_PLUGIN_FILE', __FILE__ );
		define( 'SCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'SCP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
		define( 'SCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'SCP_VERSION', '2.1.0' );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		include_once SCP_PLUGIN_PATH . 'includes/class-scp-admin-settings.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * On plugins loaded.
	 */
	public function on_plugins_loaded() {
		// Main initialization logic.
	}

	/**
	 * Get the ID of a SupportCandy custom field by its name.
	 *
	 * @param string $field_name The name of the custom field.
	 * @return int The ID of the custom field, or 0 if not found.
	 */
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
				"SELECT id FROM {$table_name} WHERE name = %s",
				$field_name
			)
		);
		return $field_id ? (int) $field_id : 0;
	}

	/**
	 * Enqueue scripts and styles.
	 */
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

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Our settings page hook is toplevel_page_supportcandy-plus
		if ( 'toplevel_page_supportcandy-plus' !== $hook_suffix ) {
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
	}

	/**
	 * Gets a list of available columns (standard + custom).
	 */
	public function get_supportcandy_columns() {
		global $wpdb;
		$columns = [
			'id'          => __( 'Ticket ID', 'supportcandy-plus' ),
			'subject'     => __( 'Subject', 'supportcandy-plus' ),
			'status'      => __( 'Status', 'supportcandy-plus' ),
			'category'    => __( 'Category', 'supportcandy-plus' ),
			'priority'    => __( 'Priority', 'supportcandy-plus' ),
			'customer'    => __( 'Customer', 'supportcandy-plus' ),
			'agent'       => __( 'Agent', 'supportcandy-plus' ),
			'last_reply'  => __( 'Last Reply', 'supportcandy-plus' ),
			'date'        => __( 'Date', 'supportcandy-plus' ),
		];

		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) === $custom_fields_table ) {
			$custom_fields = $wpdb->get_results( "SELECT name, label FROM {$custom_fields_table}", ARRAY_A );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					// The key passed to JS should match what's in the rule settings.
					$columns[ 'cust_' . $field['name'] ] = $field['label'];
				}
			}
		}
		return $columns;
	}
}

/**
 * Main instance of SupportCandy_Plus.
 *
 * Returns the main instance of SupportCandy_Plus.
 *
 * @return SupportCandy_Plus
 */
function supportcandy_plus() {
	return SupportCandy_Plus::get_instance();
}

// Global for backwards compatibility.
$GLOBALS['supportcandy_plus'] = supportcandy_plus();
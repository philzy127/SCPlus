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
	}

	/**
	 * On plugins loaded.
	 */
	public function on_plugins_loaded() {
		// Main initialization logic.
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
			'2.0.0',
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
				'column_hider'       => [
					'enabled'         => ! empty( $options['enable_column_hider'] ),
				],
				'ticket_type_hiding' => [
					'enabled'       => ! empty( $options['enable_ticket_type_hiding'] ),
					'types_to_hide' => ! empty( $options['ticket_types_to_hide'] ) ? array_map( 'trim', explode( "\n", $options['ticket_types_to_hide'] ) ) : [],
				],
				'conditional_hiding' => [
					'enabled'           => ! empty( $options['enable_conditional_hiding'] ),
					'filter_name'       => ! empty( $options['view_filter_name'] ) ? $options['view_filter_name'] : '',
					'hide_in_view'      => ! empty( $options['columns_to_hide_in_view'] ) ? array_map( 'trim', explode( "\n", $options['columns_to_hide_in_view'] ) ) : [],
					'show_only_in_view' => ! empty( $options['columns_to_show_in_view'] ) ? array_map( 'trim', explode( "\n", $options['columns_to_show_in_view'] ) ) : [],
				],
			],
		];

		wp_localize_script( 'supportcandy-plus-frontend', 'scp_settings', $localized_data );

		wp_enqueue_script( 'supportcandy-plus-frontend' );
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
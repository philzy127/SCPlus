<?php
/**
 * Unified Ticket Macro - Admin Settings
 *
 * @package SupportCandy_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SCPUTM_Admin Class.
 */
class SCPUTM_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the admin settings.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_scputm_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'admin_head', array( $this, 'print_custom_styles' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'supportcandy-plus',
			__( 'Unified Ticket Macro', 'supportcandy-plus' ),
			__( 'Unified Ticket Macro', 'supportcandy-plus' ),
			'manage_options',
			'scp-utm',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings sections and fields.
	 */
	public function register_settings() {
		add_settings_section(
			'scputm_section',
			__( 'Unified Ticket Macro Fields', 'supportcandy-plus' ),
			null,
			'scp-utm'
		);

		add_settings_field(
			'scputm_selected_fields',
			__( 'Fields to Display', 'supportcandy-plus' ),
			array( $this, 'render_fields_selector' ),
			'scp-utm',
			'scputm_section'
		);
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			do_settings_sections( 'scp-utm' );
			?>
			<p class="submit">
				<button type="button" id="scp-utm-save-settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'supportcandy-plus' ); ?></button>
				<span class="spinner"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the dual-column fields selector.
	 */
	public function render_fields_selector() {
		$options         = get_option( 'scp_settings', [] );
		$all_columns     = supportcandy_plus()->get_supportcandy_columns();
		$selected_slugs  = isset( $options['scputm_selected_fields'] ) && is_array( $options['scputm_selected_fields'] ) ? $options['scputm_selected_fields'] : [];

		$available_columns = array_diff_key( $all_columns, array_flip( $selected_slugs ) );
		$selected_columns  = array_intersect_key( $all_columns, array_flip( $selected_slugs ) );

		// Ensure the order of selected columns is preserved.
		$ordered_selected = [];
		foreach ($selected_slugs as $slug) {
			if (isset($selected_columns[$slug])) {
				$ordered_selected[$slug] = $selected_columns[$slug];
			}
		}

		?>
		<div class="scp-utm-container">
			<div class="scp-utm-box">
				<h3><?php esc_html_e( 'Available Fields', 'supportcandy-plus' ); ?></h3>
				<select multiple id="scp_utm_available_fields" size="10">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="scp-utm-buttons">
				<button type="button" class="button" id="scp_utm_add_all" title="<?php esc_attr_e( 'Add All', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
				<button type="button" class="button" id="scp_utm_add" title="<?php esc_attr_e( 'Add', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
				<button type="button" class="button" id="scp_utm_remove" title="<?php esc_attr_e( 'Remove', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
				<button type="button" class="button" id="scp_utm_remove_all" title="<?php esc_attr_e( 'Remove All', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
			</div>
			<div class="scp-utm-box">
				<h3><?php esc_html_e( 'Selected Fields', 'supportcandy-plus' ); ?></h3>
				<select multiple name="scp_settings[scputm_selected_fields][]" id="scp_utm_selected_fields" size="10">
					<?php foreach ( $ordered_selected as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="scp-utm-buttons">
					<button type="button" class="button" id="scp_utm_move_top" title="<?php esc_attr_e( 'Move to Top', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
					<button type="button" class="button" id="scp_utm_move_up" title="<?php esc_attr_e( 'Move Up', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
					<button type="button" class="button" id="scp_utm_move_down" title="<?php esc_attr_e( 'Move Down', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
					<button type="button" class="button" id="scp_utm_move_bottom" title="<?php esc_attr_e( 'Move to Bottom', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Select the fields you want to include in the macro. The order of fields in the "Selected Fields" box will be the order they appear in the email.', 'supportcandy-plus' ); ?></p>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our settings page
		if ( 'supportcandy-plus_page_scp-utm' !== $hook ) {
			return;
		}

		$script_path = plugin_dir_path( SCP_PLUGIN_FILE ) . 'assets/admin/js/scp-admin-utm.js';
		$script_url  = plugin_dir_url( SCP_PLUGIN_FILE ) . 'assets/admin/js/scp-admin-utm.js';

		wp_enqueue_script(
			'scp-admin-utm',
			$script_url,
			array( 'jquery' ),
			file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
			true
		);

		wp_localize_script(
			'scp-admin-utm',
			'scp_utm_admin_params',
			array(
				'nonce'                 => wp_create_nonce( 'scputm_save_settings_nonce' ),
				'save_success_message'  => __( 'Settings saved successfully!', 'supportcandy-plus' ),
				'save_error_message'    => __( 'An error occurred. Please try again.', 'supportcandy-plus' ),
			)
		);
	}

	/**
	 * AJAX handler for saving settings.
	 */
	public function ajax_save_settings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'scputm_save_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'supportcandy-plus' ) ) );
		}

		// Sanitize and get the selected fields
		$selected_fields = isset( $_POST['selected_fields'] ) && is_array( $_POST['selected_fields'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_fields'] ) )
			: array();

		// Get all settings, update the UTM fields, and save
		$settings = get_option( 'scp_settings', array() );
		$settings['scputm_selected_fields'] = $selected_fields;
		update_option( 'scp_settings', $settings );

		wp_send_json_success();
	}

	/**
	 * Print custom styles for the settings page.
	 */
	public function print_custom_styles() {
		$screen = get_current_screen();
		if ( ! $screen || 'supportcandy-plus_page_scp-utm' !== $screen->id ) {
			return;
		}
		?>
		<style type="text/css">
			#scp_utm_move_top .dashicons,
			#scp_utm_move_up .dashicons {
				transform: rotate(-90deg);
			}
			#scp_utm_move_down .dashicons,
			#scp_utm_move_bottom .dashicons {
				transform: rotate(90deg);
			}
		</style>
		<?php
	}
}

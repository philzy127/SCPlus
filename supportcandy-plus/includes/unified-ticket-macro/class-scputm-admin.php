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

		add_settings_field(
			'scputm_use_sc_order',
			__( 'Field Order', 'supportcandy-plus' ),
			array( $this, 'render_use_sc_order_checkbox' ),
			'scp-utm',
			'scputm_section'
		);

		// Section for the renaming rules
		add_settings_section(
			'scputm_rules_section',
			__( 'Rename Field Titles', 'supportcandy-plus' ),
			'__return_false', // No description needed
			'scp-utm'
		);

		// Field for the renaming rules
		add_settings_field(
			'scputm_rename_rules',
			__( 'Renaming Rules', 'supportcandy-plus' ),
			array( $this, 'render_rules_builder' ),
			'scp-utm',
			'scputm_rules_section'
		);
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<div id="scp-utm-toast-container"></div>
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
		$selected_slugs  = isset( $options['utm_columns'] ) && is_array( $options['utm_columns'] ) ? $options['utm_columns'] : [];

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
				<div class="scp-utm-selected-wrapper">
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
		</div>
		<p class="description"><?php esc_html_e( 'Select the fields you want to include in the macro. The order of fields in the "Selected Fields" box will be the order they appear in the email.', 'supportcandy-plus' ); ?></p>
		<?php
	}

	/**
	 * Render the checkbox for using SupportCandy field order.
	 */
	public function render_use_sc_order_checkbox() {
		$options      = get_option( 'scp_settings', [] );
		$use_sc_order = isset( $options['use_supportcandy_order'] ) ? (bool) $options['use_supportcandy_order'] : false;
		?>
		<label>
			<input type="checkbox" name="scp_settings[use_supportcandy_order]" id="scp_use_supportcandy_order" value="1" <?php checked( $use_sc_order ); ?> />
			<?php esc_html_e( 'Use SupportCandy Field Order', 'supportcandy-plus' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'If checked, the fields will be ordered according to the global settings in SupportCandy -> Ticket Form Fields. The manual sorting controls will be disabled.', 'supportcandy-plus' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the rules builder UI.
	 */
	public function render_rules_builder() {
		$options      = get_option( 'scp_settings', [] );
		$all_columns  = supportcandy_plus()->get_supportcandy_columns();
		$rename_rules = isset( $options['scputm_rename_rules'] ) && is_array( $options['scputm_rename_rules'] ) ? $options['scputm_rename_rules'] : [];
		?>
		<div id="scp-utm-rules-container">
			<?php
			if ( ! empty( $rename_rules ) ) :
				foreach ( $rename_rules as $rule ) :
					?>
					<div class="scp-utm-rule-row">
						<span><?php esc_html_e( 'Display', 'supportcandy-plus' ); ?></span>
						<select class="scp-utm-rule-field">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'as', 'supportcandy-plus' ); ?></span>
						<input type="text" class="scp-utm-rule-name" value="<?php echo esc_attr( $rule['name'] ); ?>" />
						<button type="button" class="button scp-utm-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-trash"></span></button>
					</div>
					<?php
				endforeach;
			endif;
			?>
		</div>
		<button type="button" id="scp-utm-add-rule" class="button"><?php esc_html_e( 'Add Rule', 'supportcandy-plus' ); ?></button>
		<p class="description"><?php esc_html_e( 'Here you can rename the titles of fields for the email output. For example, you could change "ID" to "Ticket Number".', 'supportcandy-plus' ); ?></p>

		<script type="text/template" id="scp-utm-rule-template">
			<div class="scp-utm-rule-row">
				<span><?php esc_html_e( 'Display', 'supportcandy-plus' ); ?></span>
				<select class="scp-utm-rule-field">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span><?php esc_html_e( 'as', 'supportcandy-plus' ); ?></span>
				<input type="text" class="scp-utm-rule-name" value="" />
				<button type="button" class="button scp-utm-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'supportcandy-plus' ); ?>"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
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

		// Sanitize and get the rename rules
		$rename_rules = array();
		if ( isset( $_POST['rename_rules'] ) && is_array( $_POST['rename_rules'] ) ) {
			foreach ( wp_unslash( $_POST['rename_rules'] ) as $rule ) {
				if ( ! empty( $rule['field'] ) && ! empty( $rule['name'] ) ) {
					$rename_rules[] = array(
						'field' => sanitize_text_field( $rule['field'] ),
						'name'  => sanitize_text_field( $rule['name'] ),
					);
				}
			}
		}

		// Sanitize and get the order setting
		$use_sc_order = isset( $_POST['use_sc_order'] ) && 'true' === $_POST['use_sc_order'];

		// Get all settings, update the UTM fields, and save
		$settings = get_option( 'scp_settings', array() );
		$settings['utm_columns'] = $selected_fields;
		$settings['scputm_rename_rules']    = $rename_rules;
		$settings['use_supportcandy_order'] = $use_sc_order;
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
			.scp-utm-selected-wrapper {
				display: flex;
				align-items: flex-start;
			}
			.scp-utm-selected-wrapper .scp-utm-buttons {
				margin-left: 5px;
				display: flex;
				flex-direction: column;
			}

			#scp_utm_add .dashicons,
			#scp_utm_remove .dashicons {
				transform: scale(1.3);
			}

			#scp_utm_move_up .dashicons {
				transform: rotate(-90deg) scale(1.3);
			}

			#scp_utm_move_down .dashicons {
				transform: rotate(90deg) scale(1.3);
			}

			.scp-utm-rule-row {
				display: flex;
				align-items: center;
				margin-bottom: 10px;
			}

			.scp-utm-rule-row span,
			.scp-utm-rule-row select,
			.scp-utm-rule-row input {
				margin-right: 10px;
			}

			.scp-utm-remove-rule.button {
				display: inline-flex;
				align-items: center;
				justify-content: center;
			}
			.scp-utm-remove-rule .dashicons {
				font-size: 18px;
				margin: 0;
			}

			#scp-utm-toast-container {
				position: fixed;
				top: 40px;
				right: 20px;
				z-index: 9999;
				width: 300px;
			}

			.scp-utm-toast {
				background-color: #333;
				color: #fff;
				padding: 15px;
				margin-bottom: 10px;
				border-radius: 4px;
				opacity: 0;
				transition: opacity 0.3s ease-in-out;
			}

			.scp-utm-toast.show {
				opacity: 1;
			}

			.scp-utm-toast.error {
				background-color: #d9534f;
			}
		</style>
		<?php
	}
}

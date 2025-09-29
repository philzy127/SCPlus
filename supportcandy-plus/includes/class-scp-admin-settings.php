<?php
/**
 * SupportCandy Plus Admin Settings
 *
 * @package SupportCandy_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SCP_Admin_Settings Class.
 */
class SCP_Admin_Settings {

	/**
	 * Initialize the settings page.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the admin menu item.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'SupportCandy Plus Settings', 'supportcandy-plus' ),
			__( 'SupportCandy Plus', 'supportcandy-plus' ),
			'manage_options',
			'supportcandy-plus',
			array( $this, 'settings_page_content' )
		);
	}

	/**
	 * Render the settings page content.
	 */
	public function settings_page_content() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'scp_settings' );
				do_settings_sections( 'supportcandy-plus' );
				submit_button( __( 'Save Settings', 'supportcandy-plus' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the settings.
	 */
	public function register_settings() {
		register_setting( 'scp_settings', 'scp_settings', array( $this, 'sanitize_settings' ) );

		// General Settings Section.
		add_settings_section(
			'scp_general_section',
			__( 'General Settings', 'supportcandy-plus' ),
			null,
			'supportcandy-plus'
		);

		// Feature Toggles.
		add_settings_field(
			'scp_enable_hover_card',
			__( 'Enable Ticket Hover Card', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'supportcandy-plus',
			'scp_general_section',
			array(
				'label_for' => 'scp_enable_hover_card',
				'id'        => 'scp_enable_hover_card',
				'name'      => 'enable_hover_card',
				'desc'      => __( 'Enable a floating card with ticket details on hover.', 'supportcandy-plus' ),
			)
		);

		add_settings_field(
			'scp_enable_column_hider',
			__( 'Enable Dynamic Column Hiding', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'supportcandy-plus',
			'scp_general_section',
			array(
				'label_for' => 'scp_enable_column_hider',
				'id'        => 'scp_enable_column_hider',
				'name'      => 'enable_column_hider',
				'desc'      => __( 'Hide empty columns and the "Priority" column when all are low.', 'supportcandy-plus' ),
			)
		);

		add_settings_field(
			'scp_enable_ticket_type_hiding',
			__( 'Enable Ticket Type Hiding for Non-Agents', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'supportcandy-plus',
			'scp_general_section',
			array(
				'label_for' => 'scp_enable_ticket_type_hiding',
				'id'        => 'scp_enable_ticket_type_hiding',
				'name'      => 'enable_ticket_type_hiding',
				'desc'      => __( 'Hide specific ticket types from non-agent users.', 'supportcandy-plus' ),
			)
		);

		// Column Hiding Settings Section.
		add_settings_section(
			'scp_column_hiding_section',
			__( 'Conditional Column Hiding', 'supportcandy-plus' ),
			null,
			'supportcandy-plus'
		);

		add_settings_field(
			'scp_view_filter_name',
			__( 'Filter Name to Trigger Hiding', 'supportcandy-plus' ),
			array( $this, 'render_text_field' ),
			'supportcandy-plus',
			'scp_column_hiding_section',
			array(
				'label_for' => 'scp_view_filter_name',
				'id'        => 'scp_view_filter_name',
				'name'      => 'view_filter_name',
				'desc'      => __( 'e.g., "Network Access Requests"', 'supportcandy-plus' ),
			)
		);

		add_settings_field(
			'scp_columns_to_hide_in_view',
			__( 'Columns to Hide in Special View', 'supportcandy-plus' ),
			array( $this, 'render_text_field' ),
			'supportcandy-plus',
			'scp_column_hiding_section',
			array(
				'label_for' => 'scp_columns_to_hide_in_view',
				'id'        => 'scp_columns_to_hide_in_view',
				'name'      => 'columns_to_hide_in_view',
				'desc'      => __( 'Comma-separated list of column names (e.g., "Name").', 'supportcandy-plus' ),
			)
		);

		add_settings_field(
			'scp_columns_to_show_in_view',
			__( 'Columns to Show Only in Special View', 'supportcandy-plus' ),
			array( $this, 'render_text_field' ),
			'supportcandy-plus',
			'scp_column_hiding_section',
			array(
				'label_for' => 'scp_columns_to_show_in_view',
				'id'        => 'scp_columns_to_show_in_view',
				'name'      => 'columns_to_show_in_view',
				'desc'      => __( 'Comma-separated list of column names (e.g., "Anticipated Start Date").', 'supportcandy-plus' ),
			)
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args The field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'scp_settings' );
		$value   = isset( $options[ $args['name'] ] ) ? 1 : 0;
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['name'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args The field arguments.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'scp_settings' );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
		echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['name'] ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Sanitize the settings.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();

		// Sanitize checkboxes.
		$checkboxes = array( 'enable_hover_card', 'enable_column_hider', 'enable_ticket_type_hiding' );
		foreach ( $checkboxes as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = 1;
			}
		}

		// Sanitize text fields.
		$text_fields = array( 'view_filter_name', 'columns_to_hide_in_view', 'columns_to_show_in_view' );
		foreach ( $text_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		return $sanitized_input;
	}
}

new SCP_Admin_Settings();
<?php
/**
 * SupportCandy Plus Admin Settings (Advanced)
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
		add_menu_page(
			__( 'SupportCandy Plus Settings', 'supportcandy-plus' ),
			__( 'SupportCandy Plus', 'supportcandy-plus' ),
			'manage_options',
			'supportcandy-plus',
			array( $this, 'settings_page_content' ),
			'dashicons-plus-alt',
			3
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
	 * Register the settings sections and fields.
	 */
	public function register_settings() {
		register_setting( 'scp_settings', 'scp_settings', array( $this, 'sanitize_settings' ) );

		// Section: Ticket Hover Card
		add_settings_section( 'scp_hover_card_section', __( 'Ticket Hover Card', 'supportcandy-plus' ), null, 'supportcandy-plus' );
		add_settings_field( 'scp_enable_hover_card', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_hover_card_section', [ 'id' => 'enable_hover_card', 'desc' => 'Enable a floating card with ticket details on hover.' ] );
		add_settings_field( 'scp_hover_card_delay', __( 'Hover Delay (ms)', 'supportcandy-plus' ), array( $this, 'render_number_field' ), 'supportcandy-plus', 'scp_hover_card_section', [ 'id' => 'hover_card_delay', 'desc' => 'Time to wait before showing the card. Default: 1000.', 'default' => 1000 ] );

		// Section: Automatic Column Cleanup
		add_settings_section( 'scp_dynamic_hiding_section', __( 'Automatic Column Cleanup', 'supportcandy-plus' ), array( $this, 'render_column_cleanup_description' ), 'supportcandy-plus' );
		add_settings_field( 'scp_enable_column_hider', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_dynamic_hiding_section', [ 'id' => 'enable_column_hider', 'desc' => 'Enable automatic hiding of empty columns.' ] );

		// Section: Ticket Type Hiding
		add_settings_section( 'scp_ticket_type_section', __( 'Hide Ticket Types from Non-Agents', 'supportcandy-plus' ), array( $this, 'render_ticket_type_hiding_description' ), 'supportcandy-plus' );
		add_settings_field( 'scp_enable_ticket_type_hiding', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );
		add_settings_field( 'scp_ticket_types_to_hide', __( 'Ticket Types to Hide', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_types_to_hide', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );

		// Section: Conditional Column Hiding
		add_settings_section( 'scp_conditional_hiding_section', __( 'Conditional Column Hiding by Filter', 'supportcandy-plus' ), null, 'supportcandy-plus' );
		add_settings_field( 'scp_enable_conditional_hiding', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_conditional_hiding_section', [ 'id' => 'enable_conditional_hiding', 'desc' => 'Show or hide columns based on the selected view filter.' ] );
		add_settings_field( 'scp_view_filter_name', __( 'Filter Name for Special View', 'supportcandy-plus' ), array( $this, 'render_text_field' ), 'supportcandy-plus', 'scp_conditional_hiding_section', [ 'id' => 'view_filter_name', 'desc' => 'The exact name of the filter to trigger this rule, e.g., "Network Access Requests".' ] );
		add_settings_field( 'scp_columns_to_hide_in_view', __( 'Columns to HIDE in Special View', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_conditional_hiding_section', [ 'id' => 'columns_to_hide_in_view', 'desc' => 'Columns to hide when the special filter is active. One per line.' ] );
		add_settings_field( 'scp_columns_to_show_in_view', __( 'Columns to SHOW ONLY in Special View', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_conditional_hiding_section', [ 'id' => 'columns_to_show_in_view', 'desc' => 'Columns that are normally hidden but should appear for this view. One per line.' ] );
	}

	/**
	 * Render the description for the Automatic Column Cleanup section.
	 */
	public function render_column_cleanup_description() {
		echo '<p>' . esc_html__( 'This feature automatically hides any column in the ticket list that is completely empty, creating a cleaner interface.', 'supportcandy-plus' ) . '</p>';
	}

	/**
	 * Render the description for the Hide Ticket Types section.
	 */
	public function render_ticket_type_hiding_description() {
		echo '<p>' . esc_html__( 'This feature hides specified ticket categories from the dropdown menu for any user who is not an agent.', 'supportcandy-plus' ) . '</p>';
	}

	/**
	 * Render a checkbox field.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? 1 : 0;
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		if ( ! empty( $args['desc'] ) ) echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
	}

	/**
	 * Render a text field.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );
		echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( ! empty( $args['desc'] ) ) echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
	}

	/**
	 * Render a number field.
	 */
	public function render_number_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );
		echo '<input type="number" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="small-text">';
		if ( ! empty( $args['desc'] ) ) echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
	}

	/**
	 * Render a textarea field.
	 */
	public function render_textarea_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		echo '<textarea id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" rows="5" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
	}

	/**
	 * Sanitize the settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = [];
		$options         = get_option( 'scp_settings', [] );

		// Checkboxes
		$checkboxes = [ 'enable_hover_card', 'enable_column_hider', 'enable_ticket_type_hiding', 'enable_conditional_hiding' ];
		foreach ( $checkboxes as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = 1;
			}
		}

		// Text fields
		$text_fields = [ 'priority_column_name', 'low_priority_text', 'ticket_type_custom_field_name', 'view_filter_name' ];
		foreach ( $text_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		// Number fields
		if ( isset( $input['hover_card_delay'] ) ) {
			$sanitized_input['hover_card_delay'] = absint( $input['hover_card_delay'] );
		}

		// Textarea fields
		$textarea_fields = [ 'ticket_types_to_hide', 'columns_to_hide_in_view', 'columns_to_show_in_view' ];
		foreach ( $textarea_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = sanitize_textarea_field( $input[ $key ] );
			}
		}

		return $sanitized_input;
	}
}

new SCP_Admin_Settings();
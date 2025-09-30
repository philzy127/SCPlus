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
	 * Add the admin menu and sub-menu items.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'SupportCandy Plus', 'supportcandy-plus' ),
			__( 'SupportCandy Plus', 'supportcandy-plus' ),
			'manage_options',
			'supportcandy-plus',
			array( $this, 'general_settings_page_content' ),
			'dashicons-plus-alt',
			3
		);

		add_submenu_page(
			'supportcandy-plus',
			__( 'General Settings', 'supportcandy-plus' ),
			__( 'General Settings', 'supportcandy-plus' ),
			'manage_options',
			'supportcandy-plus',
			array( $this, 'general_settings_page_content' )
		);

		add_submenu_page(
			'supportcandy-plus',
			__( 'Conditional Hiding', 'supportcandy-plus' ),
			__( 'Conditional Hiding', 'supportcandy-plus' ),
			'manage_options',
			'scp-conditional-hiding',
			array( $this, 'conditional_hiding_page_content' )
		);

		add_submenu_page(
			'supportcandy-plus',
			__( 'After Hours Notice', 'supportcandy-plus' ),
			__( 'After Hours Notice', 'supportcandy-plus' ),
			'manage_options',
			'scp-after-hours',
			array( $this, 'after_hours_page_content' )
		);
	}

	/**
	 * Render the General settings page content.
	 */
	public function general_settings_page_content() {
		$this->render_settings_page_wrapper( 'supportcandy-plus' );
	}

	/**
	 * Render the Conditional Hiding settings page content.
	 */
	public function conditional_hiding_page_content() {
		$this->render_settings_page_wrapper( 'scp-conditional-hiding' );
	}

	/**
	 * Render the After Hours Notice settings page content.
	 */
	public function after_hours_page_content() {
		$this->render_settings_page_wrapper( 'scp-after-hours' );
	}

	/**
	 * Render a generic settings page wrapper.
	 *
	 * @param string $page_slug The slug of the page to render sections for.
	 */
	private function render_settings_page_wrapper( $page_slug ) {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'scp_settings' );
				echo '<input type="hidden" name="scp_settings[page_slug]" value="' . esc_attr( $page_slug ) . '">';
				do_settings_sections( $page_slug );
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

		// Page: General Settings
		// Section: Ticket Details Card
		add_settings_section( 'scp_right_click_card_section', __( 'Ticket Details Card', 'supportcandy-plus' ), null, 'supportcandy-plus' );
		add_settings_field( 'scp_enable_right_click_card', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_right_click_card_section', [ 'id' => 'enable_right_click_card', 'desc' => 'Shows a card with ticket details on right-click.' ] );

		add_settings_section( 'scp_separator_1', '', array( $this, 'render_hr_separator' ), 'supportcandy-plus' );

		// Section: General Cleanup
		add_settings_section( 'scp_general_cleanup_section', __( 'General Cleanup', 'supportcandy-plus' ), null, 'supportcandy-plus' );
		add_settings_field( 'scp_enable_hide_empty_columns', __( 'Hide Empty Columns', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );
		add_settings_field( 'scp_enable_hide_priority_column', __( 'Hide Priority Column if all \'Low\'', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );

		add_settings_section( 'scp_separator_2', '', array( $this, 'render_hr_separator' ), 'supportcandy-plus' );

		// Section: Ticket Type Hiding
		add_settings_section( 'scp_ticket_type_section', __( 'Hide Ticket Types from Non-Agents', 'supportcandy-plus' ), array( $this, 'render_ticket_type_hiding_description' ), 'supportcandy-plus' );
		add_settings_field( 'scp_enable_ticket_type_hiding', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );
		add_settings_field( 'scp_ticket_type_custom_field_name', __( 'Custom Field Name', 'supportcandy-plus' ), array( $this, 'render_text_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_type_custom_field_name', 'desc' => 'The name of the custom field for ticket types (e.g., "Ticket Category"). The plugin will find the ID dynamically.' ] );
		add_settings_field( 'scp_ticket_types_to_hide', __( 'Ticket Types to Hide', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_types_to_hide', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );

		// Page: Conditional Hiding
		// Section: Conditional Column Hiding
		add_settings_section(
			'scp_conditional_hiding_section',
			__( 'Conditional Column Hiding Rules', 'supportcandy-plus' ),
			array( $this, 'render_conditional_hiding_description' ),
			'scp-conditional-hiding'
		);
		add_settings_field(
			'scp_enable_conditional_hiding',
			__( 'Enable Feature', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'scp-conditional-hiding',
			'scp_conditional_hiding_section',
			[ 'id' => 'enable_conditional_hiding', 'desc' => 'Enable the rule-based system to show or hide columns.' ]
		);
		add_settings_field(
			'scp_conditional_hiding_rules',
			__( 'Rules', 'supportcandy-plus' ),
			array( $this, 'render_conditional_hiding_rules_builder' ),
			'scp-conditional-hiding',
			'scp_conditional_hiding_section'
		);

		// Page: After Hours Notice
		// Section: After Hours Notice
		add_settings_section(
			'scp_after_hours_section',
			__( 'After Hours Notice', 'supportcandy-plus' ),
			array( $this, 'render_after_hours_description' ),
			'scp-after-hours'
		);
		add_settings_field( 'scp_enable_after_hours_notice', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] );
		add_settings_field( 'scp_after_hours_start', __( 'After Hours Start (24h)', 'supportcandy-plus' ), array( $this, 'render_number_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'after_hours_start', 'default' => '17', 'desc' => 'The hour when after-hours starts (e.g., 17 for 5 PM).' ] );
		add_settings_field( 'scp_before_hours_end', __( 'Before Hours End (24h)', 'supportcandy-plus' ), array( $this, 'render_number_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'before_hours_end', 'default' => '8', 'desc' => 'The hour when business hours resume (e.g., 8 for 8 AM).' ] );
		add_settings_field( 'scp_include_all_weekends', __( 'Include All Weekends', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] );
		add_settings_field( 'scp_holidays', __( 'Holidays', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'holidays', 'desc' => 'List holidays, one per line, in YYYY-MM-DD format (e.g., 2024-12-25). The notice will show all day on these dates.' ] );
		add_settings_field( 'scp_after_hours_message', __( 'After Hours Message', 'supportcandy-plus' ), array( $this, 'render_wp_editor_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'after_hours_message', 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
	}

	/**
	 * Render the description for the Hide Ticket Types section.
	 */
	public function render_ticket_type_hiding_description() {
		echo '<p>' . esc_html__( 'This feature hides specified ticket categories from the dropdown menu for any user who is not an agent.', 'supportcandy-plus' ) . '</p>';
	}

	/**
	 * Render the description for the Conditional Hiding section.
	 */
	public function render_conditional_hiding_description() {
		echo '<p>' . esc_html__( 'Create rules to show or hide columns based on the selected ticket view. This allows for powerful customization of the ticket list for different contexts.', 'supportcandy-plus' ) . '</p>';
	}

	/**
	 * Render the description for the After Hours Notice section.
	 */
	public function render_after_hours_description() {
		echo '<p>' . esc_html__( 'This feature shows a customizable message at the top of the "Create Ticket" form if a user is accessing it outside of your defined business hours.', 'supportcandy-plus' ) . '</p>';
	}

	/**
	 * Renders a horizontal rule separator.
	 */
	public function render_hr_separator() {
		echo '<hr>';
	}

	/**
	 * Render the rule builder interface.
	 */
	public function render_conditional_hiding_rules_builder() {
		$options = get_option( 'scp_settings', [] );
		$rules   = isset( $options['conditional_hiding_rules'] ) && is_array( $options['conditional_hiding_rules'] ) ? $options['conditional_hiding_rules'] : [];
		$views   = $this->get_supportcandy_views();
		$columns = supportcandy_plus()->get_supportcandy_columns();
		?>
		<div id="scp-rules-container">
			<?php
			if ( ! empty( $rules ) ) {
				foreach ( $rules as $index => $rule ) {
					$this->render_rule_template( $index, $rule, $views, $columns );
				}
			} else {
				echo '<p id="scp-no-rules-message">' . esc_html__( 'No rules defined yet. Click "Add New Rule" to start.', 'supportcandy-plus' ) . '</p>';
			}
			?>
		</div>
		<button type="button" class="button" id="scp-add-rule"><?php esc_html_e( 'Add New Rule', 'supportcandy-plus' ); ?></button>

		<div class="scp-rule-template-wrapper" style="display: none;">
			<script type="text/template" id="scp-rule-template">
				<?php $this->render_rule_template( '__INDEX__', [], $views, $columns ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Renders the HTML for a single rule row.
	 */
	private function render_rule_template( $index, $rule, $views, $columns ) {
		$action    = $rule['action'] ?? 'hide';
		$condition = $rule['condition'] ?? 'in_view';
		$view_id   = $rule['view'] ?? '';
		$selected_cols = $rule['columns'] ?? [];
		?>
		<div class="scp-rule">
			<select name="scp_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][action]">
				<option value="show" <?php selected( $action, 'show' ); ?>><?php esc_html_e( 'SHOW', 'supportcandy-plus' ); ?></option>
				<option value="hide" <?php selected( $action, 'hide' ); ?>><?php esc_html_e( 'HIDE', 'supportcandy-plus' ); ?></option>
			</select>

			<select name="scp_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][columns]" class="scp-rule-columns">
				<?php foreach ( $columns as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $rule['columns'] ?? '', $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="scp_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][condition]">
				<option value="in_view" <?php selected( $condition, 'in_view' ); ?>><?php esc_html_e( 'WHEN IN VIEW', 'supportcandy-plus' ); ?></option>
				<option value="not_in_view" <?php selected( $condition, 'not_in_view' ); ?>><?php esc_html_e( 'WHEN NOT IN VIEW', 'supportcandy-plus' ); ?></option>
			</select>

			<select name="scp_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][view]">
				<?php foreach ( $views as $id => $name ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $view_id, $id ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button scp-remove-rule">&times;</button>
		</div>
		<?php
	}

	/**
	 * Gets the list of available SupportCandy views/filters from the wp_options table.
	 */
	private function get_supportcandy_views() {
		$views = [ '0' => __( 'Default View (All Tickets)', 'supportcandy-plus' ) ];

		$raw_filters = get_option( 'wpsc-atl-default-filters' );
		if ( empty( $raw_filters ) ) {
			return $views;
		}

		$filter_data = maybe_unserialize( $raw_filters );
		if ( ! is_array( $filter_data ) ) {
			return $views;
		}

		foreach ( $filter_data as $id => $details ) {
			if ( ! empty( $details['is_enable'] ) && ! empty( $details['label'] ) ) {
				$views[ $id ] = $details['label'];
			}
		}

		return $views;
	}


	/**
	 * Render a checkbox field.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? 1 : 0;
		// Add a hidden field with value 0. This ensures that when the checkbox is unchecked, a value of '0' is still submitted.
		echo '<input type="hidden" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="0">';
		// The actual checkbox. If checked, its value '1' will overwrite the hidden field's value.
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
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
	 * Render a WP Editor (WYSIWYG) field.
	 */
	public function render_wp_editor_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$content = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '<strong>CHP Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(202) 996-8415</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
		wp_editor(
			$content,
			'scp_settings_' . esc_attr( $args['id'] ),
			[
				'textarea_name' => 'scp_settings[' . esc_attr( $args['id'] ) . ']',
				'media_buttons' => false,
				'textarea_rows' => 10,
				'teeny'         => true,
			]
		);
		if ( ! empty( $args['desc'] ) ) echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
	}

	/**
	 * Sanitize the settings.
	 */
	public function sanitize_settings( $input ) {
		// Get the full array of currently saved settings.
		$saved_settings = get_option( 'scp_settings', [] );
		if ( ! is_array( $saved_settings ) ) {
			$saved_settings = [];
		}

		// Identify which page was submitted.
		$page_slug = $input['page_slug'] ?? 'supportcandy-plus';

		// Define which options belong to which page.
		$page_options = [
			'supportcandy-plus'      => [ 'enable_right_click_card', 'enable_hide_empty_columns', 'enable_hide_priority_column', 'enable_ticket_type_hiding', 'ticket_type_custom_field_name', 'ticket_types_to_hide' ],
			'scp-conditional-hiding' => [ 'enable_conditional_hiding', 'conditional_hiding_rules' ],
			'scp-after-hours'        => [ 'enable_after_hours_notice', 'after_hours_start', 'before_hours_end', 'include_all_weekends', 'holidays', 'after_hours_message' ],
		];

		// Get the list of options for the page that was just saved.
		$current_page_options = $page_options[ $page_slug ] ?? [];

		// Loop through the options for the CURRENT page and update them in our main settings array.
		foreach ( $current_page_options as $key ) {
			if ( isset( $input[ $key ] ) ) {
				// The field exists in the submitted form data.
				$saved_settings[ $key ] = $input[ $key ];
			} else {
				// The field does not exist in the form data. This happens with unchecked checkboxes
				// or fields that can be entirely removed (like the rules builder).
				// We set it to a safe default (0 for checkboxes, empty array for rules).
				if ( 'conditional_hiding_rules' === $key ) {
					$saved_settings[ $key ] = [];
				} elseif ( in_array( $key, [ 'enable_right_click_card', 'enable_hide_empty_columns', 'enable_hide_priority_column', 'enable_ticket_type_hiding', 'enable_conditional_hiding', 'enable_after_hours_notice', 'include_all_weekends' ] ) ) {
					$saved_settings[ $key ] = 0; // Handles all checkboxes.
				}
			}
		}

		// Now, sanitize the ENTIRE merged array.
		$sanitized_input = [];
		foreach ( $saved_settings as $key => $value ) {
			switch ( $key ) {
				// Checkboxes
				case 'enable_right_click_card':
				case 'enable_hide_empty_columns':
				case 'enable_hide_priority_column':
				case 'enable_ticket_type_hiding':
				case 'enable_conditional_hiding':
				case 'enable_after_hours_notice':
				case 'include_all_weekends':
					$sanitized_input[ $key ] = (int) $value;
					break;

				// Number fields
				case 'after_hours_start':
				case 'before_hours_end':
					$sanitized_input[ $key ] = absint( $value );
					break;

				// Text fields
				case 'ticket_type_custom_field_name':
					$sanitized_input[ $key ] = sanitize_text_field( $value );
					break;

				// Textarea
				case 'ticket_types_to_hide':
				case 'holidays':
					$sanitized_input[ $key ] = sanitize_textarea_field( $value );
					break;

				// WP Editor
				case 'after_hours_message':
					$sanitized_input[ $key ] = wp_kses_post( $value );
					break;

				// Array field (rules)
				case 'conditional_hiding_rules':
					if ( is_array( $value ) ) {
						$sanitized_rules = [];
						foreach ( $value as $rule ) {
							if ( ! is_array( $rule ) ) {
								continue;
							}
							$sanitized_rule            = [];
							$sanitized_rule['action']    = isset( $rule['action'] ) && in_array( $rule['action'], [ 'show', 'hide' ], true ) ? $rule['action'] : 'hide';
							$sanitized_rule['condition'] = isset( $rule['condition'] ) && in_array( $rule['condition'], [ 'in_view', 'not_in_view' ], true ) ? $rule['condition'] : 'in_view';
							$sanitized_rule['view']      = isset( $rule['view'] ) ? sanitize_text_field( $rule['view'] ) : '0';
							$sanitized_rule['columns']   = isset( $rule['columns'] ) ? sanitize_text_field( $rule['columns'] ) : '';
							$sanitized_rules[]         = $sanitized_rule;
						}
						$sanitized_input[ $key ] = $sanitized_rules;
					} else {
						$sanitized_input[ $key ] = [];
					}
					break;

				// We don't need to save the page slug itself.
				case 'page_slug':
					break;

				// Default case for any other fields that might exist.
				default:
					if ( is_string( $value ) ) {
						$sanitized_input[ $key ] = sanitize_text_field( $value );
					} else {
						$sanitized_input[ $key ] = $value;
					}
					break;
			}
		}

		return $sanitized_input;
	}
}

new SCP_Admin_Settings();
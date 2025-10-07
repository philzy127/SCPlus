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
		add_action( 'admin_menu', array( $this, 'add_how_to_use_admin_menu' ), 200 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Render a select dropdown field.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		$class   = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : 'regular';
		$choices = ! empty( $args['choices'] ) && is_array( $args['choices'] ) ? $args['choices'] : [];

		echo '<select id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" class="' . $class . '">';

		if ( isset( $args['placeholder'] ) ) {
			echo '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
		}

		foreach ( $choices as $choice_val => $choice_label ) {
			echo '<option value="' . esc_attr( $choice_val ) . '" ' . selected( $value, $choice_val, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}
		echo '</select>';

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
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
			'dashicons-tickets',
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
			__( 'Queue Macro', 'supportcandy-plus' ),
			__( 'Queue Macro', 'supportcandy-plus' ),
			'manage_options',
			'scp-queue-macro',
			array( $this, 'queue_macro_page_content' )
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
	 * Add the "How To Use" submenu item with a high priority to ensure it's last.
	 */
	public function add_how_to_use_admin_menu() {
		add_submenu_page(
			'supportcandy-plus',
			__( 'How To Use', 'supportcandy-plus' ),
			__( 'How To Use', 'supportcandy-plus' ),
			'manage_options',
			'scp-how-to-use',
			array( $this, 'how_to_use_page_content' )
		);
	}

	/**
	 * Render the How To Use page content.
	 */
	public function how_to_use_page_content() {
		if ( file_exists( SCP_PLUGIN_PATH . 'includes/admin/how-to-use-page.php' ) ) {
			include_once SCP_PLUGIN_PATH . 'includes/admin/how-to-use-page.php';
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Error', 'supportcandy-plus' ) . '</h1><p>' . esc_html__( 'Instruction file not found.', 'supportcandy-plus' ) . '</p></div>';
		}
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
	 * Render the Queue Macro settings page content.
	 */
	public function queue_macro_page_content() {
		$this->render_settings_page_wrapper( 'scp-queue-macro' );
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
		add_settings_field( 'scp_enable_hide_priority_column', __( 'Hide Priority Column', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );

		add_settings_section( 'scp_separator_2', '', array( $this, 'render_hr_separator' ), 'supportcandy-plus' );

		// Section: Ticket Type Hiding
		add_settings_section( 'scp_ticket_type_section', __( 'Hide Ticket Types from Non-Agents', 'supportcandy-plus' ), array( $this, 'render_ticket_type_hiding_description' ), 'supportcandy-plus' );
		add_settings_field( 'scp_enable_ticket_type_hiding', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );

		// Create a choices array for the custom fields dropdown.
		$custom_fields_choices = [];
		$all_custom_fields     = supportcandy_plus()->get_supportcandy_columns();
		if ( ! empty( $all_custom_fields ) ) {
			foreach ( $all_custom_fields as $slug => $name ) {
				// Use the field name for both the value and the label.
				$custom_fields_choices[ $name ] = $name;
			}
		}

		add_settings_field(
			'scp_ticket_type_custom_field_name',
			__( 'Custom Field Name', 'supportcandy-plus' ),
			array( $this, 'render_select_field' ),
			'supportcandy-plus',
			'scp_ticket_type_section',
			[
				'id'          => 'ticket_type_custom_field_name',
				'placeholder' => __( '-- Select a Custom Field --', 'supportcandy-plus' ),
				'choices'     => $custom_fields_choices,
				'desc'        => __( 'The custom field that represents the ticket type (e.g., "Ticket Category").', 'supportcandy-plus' ),
			]
		);

		add_settings_field( 'scp_ticket_types_to_hide', __( 'Ticket Types to Hide', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_types_to_hide', 'class' => 'regular-text', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );

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
		add_settings_field( 'scp_holidays', __( 'Holidays', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] );
		add_settings_field( 'scp_after_hours_message', __( 'After Hours Message', 'supportcandy-plus' ), array( $this, 'render_wp_editor_field' ), 'scp-after-hours', 'scp_after_hours_section', [ 'id' => 'after_hours_message', 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );

		// Page: Queue Macro
		add_settings_section(
			'scp_queue_macro_section',
			__( 'Queue Macro Settings', 'supportcandy-plus' ),
			null,
			'scp-queue-macro'
		);

		add_settings_field(
			'scp_enable_queue_macro',
			__( 'Enable Feature', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'scp-queue-macro',
			'scp_queue_macro_section',
			[
				'id'   => 'enable_queue_macro',
				'desc' => __( 'Adds a {{queue_count}} macro to show customers their queue position.', 'supportcandy-plus' ),
			]
		);

		$all_custom_fields = supportcandy_plus()->get_supportcandy_columns();
		$default_fields    = [
			'category' => __( 'Category', 'supportcandy-plus' ),
			'priority' => __( 'Priority', 'supportcandy-plus' ),
			'status'   => __( 'Status', 'supportcandy-plus' ),
		];
		$all_type_fields   = array_merge( $default_fields, $all_custom_fields );
		asort( $all_type_fields );

		add_settings_field(
			'scp_queue_macro_type_field',
			__( 'Ticket Type Field', 'supportcandy-plus' ),
			array( $this, 'render_select_field' ),
			'scp-queue-macro',
			'scp_queue_macro_section',
			[
				'id'      => 'queue_macro_type_field',
				'choices' => $all_type_fields,
				'desc'    => __( 'The field that distinguishes your queues (e.g., category, priority).', 'supportcandy-plus' ),
			]
		);

		add_settings_field( 'scp_queue_macro_statuses', __( 'Non-Closed Statuses', 'supportcandy-plus' ), array( $this, 'render_statuses_dual_list_field' ), 'scp-queue-macro', 'scp_queue_macro_section', [ 'id' => 'queue_macro_statuses', 'desc' => 'Select which ticket statuses should count toward the queue.' ] );

		add_settings_field( 'scp_queue_macro_test', __( 'Test Queue Counts', 'supportcandy-plus' ), array( $this, 'render_test_button_field' ), 'scp-queue-macro', 'scp_queue_macro_section' );
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
		<input type="hidden" name="scp_settings[conditional_hiding_rules]" value="">
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
				<option value="show_only" <?php selected( $action, 'show_only' ); ?>><?php esc_html_e( 'SHOW ONLY', 'supportcandy-plus' ); ?></option>
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
	 * Render the dual list for statuses.
	 */
	public function render_statuses_dual_list_field( $args ) {
		global $wpdb;
		$options           = get_option( 'scp_settings', [] );
		$selected_statuses = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : [];

		$status_table = $wpdb->prefix . 'psmsc_statuses';
		$all_statuses = $wpdb->get_results( "SELECT id, name FROM {$status_table} ORDER BY name ASC" );

		$available_statuses_map = [];
		$selected_statuses_map  = [];

		if ( $all_statuses ) {
			foreach ( $all_statuses as $status ) {
				if ( in_array( (int) $status->id, $selected_statuses, true ) ) {
					$selected_statuses_map[ $status->id ] = $status->name;
				} else {
					$available_statuses_map[ $status->id ] = $status->name;
				}
			}
		}
		?>
		<input type="hidden" name="scp_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="">
		<div class="dual-list-container">
			<div class="dual-list-box" style="display: inline-block; vertical-align: top;">
				<h3><?php _e( 'Available Statuses', 'supportcandy-plus' ); ?></h3>
				<select multiple id="scp_available_statuses" size="8" style="width: 200px; height: 150px;">
					<?php foreach ( $available_statuses_map as $id => $name ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="dual-buttons" style="display: inline-block; vertical-align: middle; margin: 0 10px;">
				<button type="button" class="button" id="scp_add_status" style="display: block; margin-bottom: 5px;">&rarr;</button>
				<button type="button" class="button" id="scp_remove_status" style="display: block;">&larr;</button>
			</div>
			<div class="dual-list-box" style="display: inline-block; vertical-align: top;">
				<h3><?php _e( 'Selected Statuses', 'supportcandy-plus' ); ?></h3>
				<select multiple name="scp_settings[<?php echo esc_attr( $args['id'] ); ?>][]" id="scp_selected_statuses" size="8" style="width: 200px; height: 150px;">
					<?php foreach ( $selected_statuses_map as $id => $name ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
		<?php
	}

	/**
	 * Render a checkbox field.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = ! empty( $options[ $args['id'] ] ) ? 1 : 0;
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
		$class   = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : 'large-text';
		echo '<textarea id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" rows="5" class="' . $class . '">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
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
	 * Render the test button for queue macro.
	 */
	public function render_test_button_field() {
		?>
		<p><?php _e( 'Click the button to see the current queue counts based on your saved settings.', 'supportcandy-plus' ); ?></p>
		<p>
			<button type="button" id="scp_test_queue_macro_button" class="button"><?php _e( 'Run Test', 'supportcandy-plus' ); ?></button>
		</p>
		<div id="scp_test_results" style="display:none; border: 1px solid #ddd; padding: 10px; margin-top: 10px; max-height: 200px; overflow-y: auto; background-color: #fff;">
			<h4><?php _e( 'Test Results', 'supportcandy-plus' ); ?></h4>
			<div id="scp_test_results_content"></div>
		</div>
		<?php
	}

	/**
	 * Sanitize the settings with a simpler, more robust method.
	 */
	public function sanitize_settings( $input ) {
		// Get the full array of currently saved settings from the database.
		$existing_settings = get_option( 'scp_settings', [] );
		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = [];
		}

		// Merge the newly submitted settings into the existing settings.
		// This correctly handles settings spread across multiple tabs.
		$merged_settings = array_merge( $existing_settings, $input );

		// Now, sanitize the ENTIRE merged array.
		$sanitized_output = [];
		foreach ( $merged_settings as $key => $value ) {
			switch ( $key ) {
				// Checkboxes (booleans stored as 1 or 0)
				case 'enable_right_click_card':
				case 'enable_hide_empty_columns':
				case 'enable_hide_priority_column':
				case 'enable_ticket_type_hiding':
				case 'enable_conditional_hiding':
				case 'enable_after_hours_notice':
				case 'include_all_weekends':
				case 'enable_queue_macro':
					$sanitized_output[ $key ] = (int) $value;
					break;

				// Integer fields
				case 'after_hours_start':
				case 'before_hours_end':
				case 'ats_ticket_question_id':
				case 'ats_technician_question_id':
					$sanitized_output[ $key ] = absint( $value );
					break;

				// Simple text fields
				case 'ticket_type_custom_field_name':
				case 'ats_background_color':
				case 'ats_ticket_url_base':
				case 'queue_macro_type_field':
					$sanitized_output[ $key ] = sanitize_text_field( $value );
					break;

				// Textareas (with line breaks)
				case 'ticket_types_to_hide':
				case 'holidays':
					$sanitized_output[ $key ] = sanitize_textarea_field( $value );
					break;

				// WP Editor (allows safe HTML)
				case 'after_hours_message':
					$sanitized_output[ $key ] = wp_kses_post( $value );
					break;

				// Array of rules for Conditional Hiding
				case 'conditional_hiding_rules':
					if ( is_array( $value ) ) {
						$sanitized_rules = [];
						foreach ( $value as $rule ) {
							if ( ! is_array( $rule ) ) {
								continue;
							}
							$sanitized_rule            = [];
							$sanitized_rule['action']    = isset( $rule['action'] ) && in_array( $rule['action'], [ 'show', 'hide', 'show_only' ], true ) ? $rule['action'] : 'hide';
							$sanitized_rule['condition'] = isset( $rule['condition'] ) && in_array( $rule['condition'], [ 'in_view', 'not_in_view' ], true ) ? $rule['condition'] : 'in_view';
							$sanitized_rule['view']      = isset( $rule['view'] ) ? sanitize_text_field( $rule['view'] ) : '0';
							$sanitized_rule['columns']   = isset( $rule['columns'] ) ? sanitize_text_field( $rule['columns'] ) : '';
							$sanitized_rules[]         = $sanitized_rule;
						}
						$sanitized_output[ $key ] = $sanitized_rules;
					} else {
						$sanitized_output[ $key ] = []; // Default to empty array if not an array.
					}
					break;

				// Array of integers for Queue Macro statuses
				case 'queue_macro_statuses':
					if ( is_array( $value ) ) {
						$sanitized_output[ $key ] = array_map( 'absint', $value );
					} else {
						$sanitized_output[ $key ] = []; // Default to empty array.
					}
					break;

				// Default case for any other fields that might exist.
				default:
					if ( is_string( $value ) ) {
						$sanitized_output[ $key ] = sanitize_text_field( $value );
					} else {
						$sanitized_output[ $key ] = $value;
					}
					break;
			}
		}

		return $sanitized_output;
	}
}

new SCP_Admin_Settings();
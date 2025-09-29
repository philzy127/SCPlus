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

		// Section: General Cleanup
		add_settings_section( 'scp_general_cleanup_section', __( 'General Cleanup', 'supportcandy-plus' ), null, 'supportcandy-plus' );
		add_settings_field( 'scp_enable_hide_empty_columns', __( 'Hide Empty Columns', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );


		// Section: Ticket Type Hiding
		add_settings_section( 'scp_ticket_type_section', __( 'Hide Ticket Types from Non-Agents', 'supportcandy-plus' ), array( $this, 'render_ticket_type_hiding_description' ), 'supportcandy-plus' );
		add_settings_field( 'scp_enable_ticket_type_hiding', __( 'Enable Feature', 'supportcandy-plus' ), array( $this, 'render_checkbox_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );
		add_settings_field( 'scp_ticket_type_custom_field_name', __( 'Custom Field Name', 'supportcandy-plus' ), array( $this, 'render_text_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_type_custom_field_name', 'desc' => 'The name of the custom field for ticket types (e.g., "Ticket Category"). The plugin will find the ID dynamically.' ] );
		add_settings_field( 'scp_ticket_types_to_hide', __( 'Ticket Types to Hide', 'supportcandy-plus' ), array( $this, 'render_textarea_field' ), 'supportcandy-plus', 'scp_ticket_type_section', [ 'id' => 'ticket_types_to_hide', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );

		// Section: Conditional Column Hiding
		add_settings_section(
			'scp_conditional_hiding_section',
			__( 'Conditional Column Hiding Rules', 'supportcandy-plus' ),
			array( $this, 'render_conditional_hiding_description' ),
			'supportcandy-plus'
		);
		add_settings_field(
			'scp_enable_conditional_hiding',
			__( 'Enable Feature', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'supportcandy-plus',
			'scp_conditional_hiding_section',
			[ 'id' => 'enable_conditional_hiding', 'desc' => 'Enable the rule-based system to show or hide columns.' ]
		);
		add_settings_field(
			'scp_conditional_hiding_rules',
			__( 'Rules', 'supportcandy-plus' ),
			array( $this, 'render_conditional_hiding_rules_builder' ),
			'supportcandy-plus',
			'scp_conditional_hiding_section'
		);
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
	 * Render the description for the Conditional Hiding section.
	 */
	public function render_conditional_hiding_description() {
		echo '<p>' . esc_html__( 'Create rules to show or hide columns based on the selected ticket view. This allows for powerful customization of the ticket list for different contexts.', 'supportcandy-plus' ) . '</p>';
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

			<select name="scp_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][columns][]" multiple class="scp-rule-columns">
				<?php foreach ( $columns as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( (string) $key, $selected_cols, true ) ? 'selected' : ''; ?>>
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
		$checkboxes = [ 'enable_hover_card', 'enable_hide_empty_columns', 'enable_ticket_type_hiding', 'enable_conditional_hiding' ];
		foreach ( $checkboxes as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = 1;
			}
		}

		// Text fields
		$text_fields = [ 'ticket_type_custom_field_name' ];
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
		$textarea_fields = [ 'ticket_types_to_hide' ];
		foreach ( $textarea_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized_input[ $key ] = sanitize_textarea_field( $input[ $key ] );
			}
		}

		// Sanitize the conditional hiding rules array
		if ( isset( $input['conditional_hiding_rules'] ) && is_array( $input['conditional_hiding_rules'] ) ) {
			$sanitized_rules = [];
			foreach ( $input['conditional_hiding_rules'] as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				$sanitized_rule = [];
				$sanitized_rule['action'] = isset( $rule['action'] ) && in_array( $rule['action'], [ 'show', 'hide' ] ) ? $rule['action'] : 'hide';
				$sanitized_rule['condition'] = isset( $rule['condition'] ) && in_array( $rule['condition'], [ 'in_view', 'not_in_view' ] ) ? $rule['condition'] : 'in_view';
				$sanitized_rule['view'] = isset( $rule['view'] ) ? absint( $rule['view'] ) : 0;

				if ( isset( $rule['columns'] ) && is_array( $rule['columns'] ) ) {
					$sanitized_rule['columns'] = array_map( 'sanitize_text_field', $rule['columns'] );
				} else {
					$sanitized_rule['columns'] = [];
				}
				$sanitized_rules[] = $sanitized_rule;
			}
			$sanitized_input['conditional_hiding_rules'] = $sanitized_rules;
		}

		return $sanitized_input;
	}
}

new SCP_Admin_Settings();
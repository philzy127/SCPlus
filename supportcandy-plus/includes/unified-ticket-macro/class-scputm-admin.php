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
	}

	/**
	 * Add the admin menu and sub-menu items.
	 */
	public function add_admin_menu() {
		$options = get_option( 'scp_settings', [] );
		if ( empty( $options['enable_utm'] ) ) {
			return;
		}

		add_submenu_page(
			'supportcandy-plus',
			__( 'Unified Ticket Macro', 'supportcandy-plus' ),
			__( 'Unified Ticket Macro', 'supportcandy-plus' ),
			'manage_options',
			'scp-utm',
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
				do_settings_sections( 'scp-utm' );
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

		// Add enable toggle to main settings page.
		add_settings_section(
			'scputm_section',
			__( 'Unified Ticket Macro', 'supportcandy-plus' ),
			null,
			'supportcandy-plus'
		);

		add_settings_field(
			'scp_enable_utm',
			__( 'Enable Feature', 'supportcandy-plus' ),
			array( $this, 'render_checkbox_field' ),
			'supportcandy-plus',
			'scputm_section',
			[
				'id'   => 'enable_utm',
				'desc' => __( 'Adds {{scp_unified_ticket}} macro to show a cached list of ticket fields.', 'supportcandy-plus' ),
			]
		);

		// Section for field selector.
		add_settings_section(
			'scputm_field_selector_section',
			__( 'Field Selector', 'supportcandy-plus' ),
			null,
			'scp-utm'
		);

		add_settings_field(
			'scputm_selected_fields',
			__( 'Fields to Display', 'supportcandy-plus' ),
			array( $this, 'render_field_selector' ),
			'scp-utm',
			'scputm_field_selector_section',
			[
				'id'   => 'scputm_selected_fields',
				'desc' => __( 'Select the fields to include in the macro output. Drag to reorder.', 'supportcandy-plus' ),
			]
		);
	}

	/**
	 * Render a checkbox field.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'scp_settings', [] );
		$value   = ! empty( $options[ $args['id'] ] ) ? 1 : 0;
		echo '<input type="hidden" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="0">';
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="scp_settings[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render the dual list for fields.
	 */
	public function render_field_selector( $args ) {
		$options         = get_option( 'scp_settings', [] );
		$selected_fields = isset( $options[ $args['id'] ] ) && is_array($options[ $args['id'] ]) ? $options[ $args['id'] ] : [];

		$all_columns       = supportcandy_plus()->get_supportcandy_columns();
		$available_columns = array_diff_key( $all_columns, array_flip( $selected_fields ) );
		?>
		<input type="hidden" name="scp_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="">
		<div class="dual-list-container scputm-dual-list">
			<div class="dual-list-box">
				<h3><?php _e( 'Available Columns', 'supportcandy-plus' ); ?></h3>
				<select multiple id="scputm_available_fields" size="10" class="dual-list-select">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="dual-buttons">
				<button type="button" class="button" id="scputm_add_all_fields">&gt;&gt;</button>
				<button type="button" class="button" id="scputm_add_field">&gt;</button>
				<button type="button" class="button" id="scputm_remove_field">&lt;</button>
				<button type="button" class="button" id="scputm_remove_all_fields">&lt;&lt;</button>
			</div>
			<div class="dual-list-box">
				<h3><?php _e( 'Selected Columns', 'supportcandy-plus' ); ?></h3>
				<select multiple name="scp_settings[<?php echo esc_attr( $args['id'] ); ?>][]" id="scputm_selected_fields" size="10" class="dual-list-select">
					<?php foreach ( $selected_fields as $slug ) : ?>
						<?php if ( isset( $all_columns[ $slug ] ) ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $all_columns[ $slug ] ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
		<?php
	}
}

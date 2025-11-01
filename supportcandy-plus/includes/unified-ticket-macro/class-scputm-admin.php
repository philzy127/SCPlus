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
		<div class="scp-utm-dual-list-container">
			<div class="dual-list-box">
				<h3><?php esc_html_e( 'Available Fields', 'supportcandy-plus' ); ?></h3>
				<select multiple id="scp_utm_available_fields" size="10">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="dual-list-buttons">
				<button type="button" class="button" id="scp_utm_add_all">&gt;&gt;</button>
				<button type="button" class="button" id="scp_utm_add">&gt;</button>
				<button type="button" class="button" id="scp_utm_remove">&lt;</button>
				<button type="button" class="button" id="scp_utm_remove_all">&lt;&lt;</button>
			</div>
			<div class="dual-list-box">
				<h3><?php esc_html_e( 'Selected Fields', 'supportcandy-plus' ); ?></h3>
				<select multiple name="scp_settings[scputm_selected_fields][]" id="scp_utm_selected_fields" size="10">
					<?php foreach ( $ordered_selected as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Select the fields you want to include in the macro. The order of fields in the "Selected Fields" box will be the order they appear in the email.', 'supportcandy-plus' ); ?></p>
		<?php
	}
}

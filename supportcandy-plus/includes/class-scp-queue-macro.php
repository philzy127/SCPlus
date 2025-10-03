<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SCP_Queue_Macro {

	private static $instance = null;

	private $table_name = 'psmsc_tickets';
	private $status_table_name = 'psmsc_statuses';
	private $custom_fields_table_name = 'wpya_psmsc_custom_fields';
	private $options_table_name = 'wpya_psmsc_options';
	private $priorities_table_name = 'psmsc_priorities';
	private $categories_table_name = 'psmsc_categories';

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$options = get_option( 'scp_settings', [] );
		if ( ! empty( $options['enable_queue_macro'] ) ) {
			add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
			add_filter( 'wpsc_create_ticket_email_data', array( $this, 'replace_queue_count_in_email' ), 10, 2 );
			add_action( 'wp_ajax_scp_test_queue_macro', array( $this, 'test_queues_ajax_handler' ) );
		}
	}

	/**
	 * Add custom macros to the list.
	 */
	public function register_macro( $macros ) {
		$macros[] = array(
			'tag'   => '{{queue_count}}',
			'title' => esc_attr__( 'Queue Count', 'supportcandy-plus' ),
		);
		return $macros;
	}

	/**
	 * Replace the queue count macro in the new ticket email.
	 */
	public function replace_queue_count_in_email( $data, $thread ) {
		if ( strpos( $data['body'], '{{queue_count}}' ) === false ) {
			return $data;
		}

		global $wpdb;
		$options    = get_option( 'scp_settings', [] );
		$type_field = isset( $options['queue_macro_type_field'] ) ? $options['queue_macro_type_field'] : 'category';
		$statuses   = isset( $options['queue_macro_statuses'] ) ? $options['queue_macro_statuses'] : [];

		if ( empty( $type_field ) || empty( $statuses ) ) {
			$data['body'] = str_replace( '{{queue_count}}', '0', $data['body'] );
			return $data;
		}

		$type_value = '';
		if ( isset( $_POST[ $type_field ] ) ) {
			$type_value = sanitize_text_field( wp_unslash( $_POST[ $type_field ] ) );
		}

		if ( is_null( $type_value ) || $type_value === '' ) {
			$data['body'] = str_replace( '{{queue_count}}', '0', $data['body'] );
			return $data;
		}

		$table        = $wpdb->prefix . $this->table_name;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%d' ) );

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE `{$type_field}` = %s AND `status` IN ($placeholders)",
			array_merge( array( $type_value ), $statuses )
		);

		$count = (int) $wpdb->get_var( $sql );

		$data['body'] = str_replace( '{{queue_count}}', $count, $data['body'] );

		return $data;
	}

	/**
	 * AJAX handler for testing queue counts.
	 */
	public function test_queues_ajax_handler() {
		check_ajax_referer( 'scp_test_queue_macro_nonce', 'nonce' );

		global $wpdb;
		$options    = get_option( 'scp_settings', [] );
		$type_field = isset( $options['queue_macro_type_field'] ) ? $options['queue_macro_type_field'] : 'category';
		$statuses   = isset( $options['queue_macro_statuses'] ) ? $options['queue_macro_statuses'] : [];

		if ( empty( $statuses ) ) {
			wp_send_json_error( __( 'No non-closed statuses are configured.', 'supportcandy-plus' ) );
			return;
		}

		// Whitelist the type field to prevent SQL injection.
		$custom_fields_table = $this->custom_fields_table_name;
		$custom_field_keys   = $wpdb->get_col( "SELECT slug FROM `{$custom_fields_table}` WHERE `field` = 'ticket'" );
		$default_fields      = array( 'category', 'priority', 'status' );
		$allowed_fields      = array_merge( $default_fields, $custom_field_keys ? $custom_field_keys : array() );

		if ( ! in_array( $type_field, $allowed_fields, true ) ) {
			wp_send_json_error( sprintf( __( 'Invalid ticket type field: %s', 'supportcandy-plus' ), $type_field ) );
			return;
		}

		$id_to_name_map = array();

		// Custom field options.
		$options_table = $this->options_table_name;
		$options       = $wpdb->get_results( "SELECT id, name FROM `{$options_table}`" );
		if ( $options ) {
			foreach ( $options as $option ) {
				$id_to_name_map[ $option->id ] = $option->name;
			}
		}

		// Statuses.
		$status_table   = $wpdb->prefix . $this->status_table_name;
		$status_options = $wpdb->get_results( "SELECT id, name FROM `{$status_table}`" );
		if ( $status_options ) {
			foreach ( $status_options as $option ) {
				$id_to_name_map[ $option->id ] = $option->name;
			}
		}

		// Priorities.
		$priorities_table = $wpdb->prefix . $this->priorities_table_name;
		$priority_options = $wpdb->get_results( "SELECT id, name FROM `{$priorities_table}`" );
		if ( $priority_options ) {
			foreach ( $priority_options as $option ) {
				$id_to_name_map[ $option->id ] = $option->name;
			}
		}

		// Categories.
		$categories_table = $wpdb->prefix . $this->categories_table_name;
		$category_options = $wpdb->get_results( "SELECT id, name FROM `{$categories_table}`" );
		if ( $category_options ) {
			foreach ( $category_options as $option ) {
				$id_to_name_map[ $option->id ] = $option->name;
			}
		}

		$table             = $wpdb->prefix . $this->table_name;
		$type_values_query = "SELECT DISTINCT `{$type_field}` FROM `{$table}`";
		$type_values       = $wpdb->get_col( $type_values_query );

		$results      = array();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%d' ) );

		foreach ( $type_values as $type_value ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE `{$type_field}` = %s AND `status` IN ($placeholders)",
				array_merge( array( $type_value ), $statuses )
			);
			$count = $wpdb->get_var( $sql );
			$name  = isset( $id_to_name_map[ $type_value ] ) ? $id_to_name_map[ $type_value ] : $type_value;
			$results[ $name ] = $count;
		}

		wp_send_json_success( $results );
	}
}
<?php
/**
 * Unified Ticket Macro - Core Logic
 *
 * @package SupportCandy_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SCPUTM_Core Class.
 */
class SCPUTM_Core {

	private static $instance = null;
	private $is_intercepting = false;
	private $field_types_map = [];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the core logic.
	 */
	private function __construct() {
		error_log('[UTM] SCPUTM_Core::__construct() - Enter');

		$options = get_option( 'scp_settings', [] );
		if ( empty( $options['enable_utm'] ) ) {
			error_log('[UTM] SCPUTM_Core::__construct() - Exit (Feature Disabled)');
			return;
		}

		add_action( 'init', array( $this, 'prime_field_types_cache' ), 10 );
		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_prime_cache_on_creation' ), 5, 1 );

		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
		error_log('[UTM] SCPUTM_Core::__construct() - Exit (Feature Enabled)');
	}

	public function scputm_prime_cache_on_creation( $ticket ) {
		error_log('[UTM] scputm_prime_cache_on_creation() - Enter');
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			error_log('[UTM] scputm_prime_cache_on_creation() - Exit (Invalid Ticket Object)');
			return;
		}

		$html_to_cache = $this->_scputm_build_live_utm_html( $ticket );

		// Use a transient for instant availability. Expires in 1 minute.
		set_transient( 'scputm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
		error_log('[UTM] scputm_prime_cache_on_creation() - Transient set.');

		error_log('[UTM] scputm_prime_cache_on_creation() - Exit');
	}

	/**
	 * Cache the field type definitions on init.
	 */
	public function prime_field_types_cache() {
		if ( ! empty( $this->field_types_map ) ) {
			return;
		}

		// This static property is only reliably available on 'init' action.
		$all_fields = WPSC_Custom_Field::$custom_fields;
		if ( empty( $all_fields ) ) {
			return;
		}

		$this->field_types_map = array();
		foreach ( $all_fields as $slug => $field_object ) {
			$field_type_class           = $field_object->type;
			$this->field_types_map[ $slug ] = $field_type_class::$slug;
		}
	}

	public function register_macro( $macros ) {
		error_log('[UTM] register_macro() - Enter');
		$macros[] = array( 'tag' => '{{scp_unified_ticket}}', 'title' => esc_attr__( 'Unified Ticket Macro', 'supportcandy-plus' ) );
		error_log('[UTM] register_macro() - Exit');
		return $macros;
	}

	private function _scputm_build_live_utm_html( $ticket ) {
		error_log('[UTM] _scputm_build_live_utm_html() - Enter');
		$options = get_option( 'scp_settings', [] );
		$selected_fields = isset( $options['utm_columns'] ) && is_array( $options['utm_columns'] ) ? $options['utm_columns'] : [];
		$rename_rules_raw = isset( $options['scputm_rename_rules'] ) && is_array( $options['scputm_rename_rules'] ) ? $options['scputm_rename_rules'] : [];

		// Create a simple map for the rename rules for easy lookup.
		$rename_rules_map = [];
		foreach ( $rename_rules_raw as $rule ) {
			if ( isset( $rule['field'] ) && ! empty( $rule['name'] ) ) {
				$rename_rules_map[ $rule['field'] ] = $rule['name'];
			}
		}

		// Get all available columns to map slugs to friendly names.
		$all_columns = supportcandy_plus()->get_supportcandy_columns();

		$use_sc_order = ! empty( $options['use_supportcandy_order'] );
		if ( $use_sc_order ) {
			// Get the official SupportCandy field order.
			$supportcandy_tff_fields = get_option( 'wpsc-tff', [] );
			$sc_ordered_slugs        = array_keys( $supportcandy_tff_fields );

			// 1. Find the fields that are in both lists, and keep the SC order.
			$ordered_part = array_intersect( $sc_ordered_slugs, $selected_fields );

			// 2. Find the fields that are in the UTM list but NOT in the SC list.
			$unmatched_part = array_diff( $selected_fields, $sc_ordered_slugs );

			// 3. Combine them. This preserves the SC order and appends the rest.
			$selected_fields = array_merge( $ordered_part, $unmatched_part );
		}

		if ( empty( $selected_fields ) ) {
			error_log('[UTM] _scputm_build_live_utm_html() - Exit (No Fields)');
			return '<table></table>';
		}

		// Use our reliably cached field types map.
		$field_types_map = $this->field_types_map;
		if ( empty( $field_types_map ) ) {
			return '<table></table>';
		}

		$html_output  = '<table>';

		foreach ( $selected_fields as $field_slug ) {

			$field_value = $ticket->{$field_slug};

			// Skip empty fields.
			if ( empty( $field_value ) ) {
				continue;
			}

			// Skip "zero dates" in both string and object formats.
			if (
				( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ||
				( $field_value instanceof DateTime && '0000-00-00 00:00:00' === $field_value->format('Y-m-d H:i:s') )
			) {
				continue;
			}

			// Check if a rename rule exists for this field.
			$field_name    = isset( $rename_rules_map[ $field_slug ] ) ? $rename_rules_map[ $field_slug ] : ( isset( $all_columns[ $field_slug ] ) ? $all_columns[ $field_slug ] : $field_slug );
			$display_value = '';
			$field_type    = isset( $field_types_map[ $field_slug ] ) ? $field_types_map[ $field_slug ] : 'unknown';

			switch ( $field_type ) {

				// Primitives
				case 'cf_textfield':
				case 'cf_textarea':
				case 'cf_email':
				case 'cf_url':
				case 'cf_number':
				case 'cf_time':
				case 'df_id':
				case 'df_subject':
				case 'df_ip_address':
				case 'df_browser':
				case 'df_os':
				case 'df_source':
				case 'df_last_reply_source':
				case 'df_user_type':
				case 'df_customer_name':
				case 'df_customer_email':
					$display_value = (string) $field_value;
					break;

				// HTML Content
				case 'cf_html':
				case 'df_description':
					$display_value = $field_value; // Do not escape HTML content.
					break;

				// Date/Time Objects
				case 'cf_date':
					$display_value = $field_value->format( get_option( 'date_format' ) );
					break;
				case 'cf_datetime':
				case 'df_date_created':
				case 'df_date_updated':
				case 'df_date_closed':
				case 'df_last_reply_on':
					$display_value = $field_value->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					break;

				// Single Object References
				case 'cf_single_select':
				case 'cf_radio_button':
				case 'cf_file-attachment-single':
				case 'df_status':
				case 'df_priority':
				case 'df_category':
				case 'df_customer':
				case 'df_agent_created':
				case 'df_last_reply_by':
					$display_value = $field_value->name;
					break;

				// Array of Object References
				case 'cf_multi_select':
				case 'cf_checkbox':
				case 'cf_file-attachment-multiple':
				case 'df_assigned_agent':
				case 'df_prev_assignee':
				case 'df_tags':
				case 'df_add_recipients':
					if ( is_array( $field_value ) ) {
						$names = array();
						foreach ( $field_value as $item ) {
							$names[] = $item->name;
						}
						$display_value = implode( ', ', $names );
					}
					break;

				// Safety Net Default
				default:
					error_log( "[UTM] Warning: Unsupported field type '" . esc_html( $field_type ) . "' encountered for field '" . esc_html( $field_slug ) . "'. This field was not included in the macro." );
					$display_value = ''; // Skip the field in the email.
					break;
			}

			// Add to output only if there's a value to display.
			if ( ! empty( $display_value ) ) {
				// Don't escape known HTML fields.
				if ( 'cf_html' === $field_type || 'df_description' === $field_type ) {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . $display_value . '</td></tr>';
				} else {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . esc_html( $display_value ) . '</td></tr>';
				}
			}
		}
		$html_output .= '</table>';
		error_log('[UTM] _scputm_build_live_utm_html() - Exit');
		return $html_output;
	}

	public function scputm_replace_utm_macro( $data, $thread ) {

		if ( ! is_array($data) || !isset($data['body']) || strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			return $data;
		}
		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			return $data;
		}

		// Prioritize the transient for the initial "new ticket" email.
		$transient_html = get_transient( 'scputm_temp_cache_' . $ticket->id );
		if ( $transient_html !== false ) {
			$final_html = $transient_html;
		} else {
			// For all existing tickets, build the HTML live.
			$final_html = $this->_scputm_build_live_utm_html( $ticket );
		}

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $final_html, $data['body'] );

		return $data;
	}
}

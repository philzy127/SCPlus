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

	public static function get_instance() {
		error_log('[UTM_Core] get_instance() called. Current action: ' . current_action());
		if ( is_null( self::$instance ) ) {
			error_log('[UTM_Core] Creating new SCPUTM_Core instance.');
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the core logic.
	 */
	private function __construct() {
		error_log('[UTM_Core] __construct() - Enter. Current action: ' . current_action());

		$options = get_option( 'scp_settings', [] );
		if ( empty( $options['enable_utm'] ) ) {
			error_log('[UTM_Core] __construct() - UTM is disabled. Bailing out.');
			return;
		}
		error_log('[UTM_Core] __construct() - UTM is enabled. Adding hooks...');

		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_prime_cache_on_creation' ), 5, 1 );

		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_cust_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		// Ticket modification hooks
		add_filter( 'wpsc_add_private_note_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_ticket_status_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_agentonly_fields_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_ticket_category_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_ticket_fields_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_ticket_priority_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_change_ticket_subject_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_delete_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		// Non-ticket hooks
		add_filter( 'wpsc_guest_login_otp_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 3 );
		add_filter( 'wpsc_user_reg_otp_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 3 );

		// Background process hook
		add_filter( 'wpsc_en_send_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
	}

	public function scputm_prime_cache_on_creation( $ticket ) {
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			return;
		}

		$html_to_cache = $this->_scputm_build_live_utm_html( $ticket );

		// Use a transient for instant availability. Expires in 1 minute.
		set_transient( 'scputm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
	}

	public function register_macro( $macros ) {
		$macros[] = array( 'tag' => '{{scp_unified_ticket}}', 'title' => esc_attr__( 'Unified Ticket Macro', 'supportcandy-plus' ) );
		return $macros;
	}

	private function _scputm_build_live_utm_html( $ticket ) {
		// Ensure the custom field schema is loaded, especially for AJAX/background contexts.
		if ( empty( WPSC_Custom_Field::$custom_fields ) ) {
			error_log('[UTM_Core] _scputm_build_live_utm_html() - WPSC_Custom_Field::$custom_fields is empty. Calling apply_schema().');
			WPSC_Custom_Field::apply_schema();
		} else {
			error_log('[UTM_Core] _scputm_build_live_utm_html() - WPSC_Custom_Field::$custom_fields is already populated.');
		}

		// Defensively reload the customer object if it's not fully populated.
		if ( ! is_a( $ticket->customer, 'WPSC_Customer' ) || ! $ticket->customer->id ) {
			error_log('[UTM_Core] _scputm_build_live_utm_html() - Incomplete customer object detected. Reloading customer for Ticket ID: ' . $ticket->id);
			$ticket->customer = new WPSC_Customer( $ticket->customer_id );
		}

		error_log('[UTM_Core] _scputm_build_live_utm_html() - Enter');
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
			return '<table></table>';
		}

		// Use the official API to get a complete list of all field types.
		$all_fields      = WPSC_Custom_Field::$custom_fields;
		error_log('[UTM_Core] _scputm_build_live_utm_html() - All Fields Contents: ' . print_r($all_fields, true));
		$field_types_map = array();
		foreach ( $all_fields as $slug => $field_object ) {
			$field_type_class = $field_object->type;
			if ( $field_type_class ) {
				$field_types_map[ $slug ] = $field_type_class::$slug;
			}
		}
		error_log('[UTM_Core] _scputm_build_live_utm_html() - Field Types Map: ' . print_r($field_types_map, true));

		$html_output  = '<table>';

		foreach ( $selected_fields as $field_slug ) {

			$field_value = $ticket->{$field_slug};

			if ( empty( $field_value ) ) {
				continue;
			}

			if (
				( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ||
				( $field_value instanceof DateTime && '0000-00-00 00:00:00' === $field_value->format('Y-m-d H:i:s') )
			) {
				continue;
			}

			$field_name    = isset( $rename_rules_map[ $field_slug ] ) ? $rename_rules_map[ $field_slug ] : ( isset( $all_columns[ $field_slug ] ) ? $all_columns[ $field_slug ] : $field_slug );
			$display_value = '';
			$field_type    = isset( $field_types_map[ $field_slug ] ) ? $field_types_map[ $field_slug ] : 'unknown';

			error_log('[UTM_Core] _scputm_build_live_utm_html() - Processing Field: ' . $field_slug . ' | Type: ' . $field_type . ' | Value: ' . print_r($field_value, true));

			switch ( $field_type ) {

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

				case 'cf_html':
				case 'df_description':
					$display_value = $field_value;
					break;

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

				default:
					$display_value = '';
					break;
			}

			if ( ! empty( $display_value ) ) {
				if ( 'cf_html' === $field_type || 'df_description' === $field_type ) {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . $display_value . '</td></tr>';
				} else {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . esc_html( $display_value ) . '</td></tr>';
				}
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}

	public function scputm_replace_utm_macro( ...$args ) {
		$original_data = $args[0]; // Keep a copy of the original data for the catch block.

		try {
			error_log( '[UTM_Core] scputm_replace_utm_macro() - ENTERED. Current hook: ' . current_filter() );
			error_log( '[UTM_Core] scputm_replace_utm_macro() - All args: ' . print_r( $args, true ) );

			// The first argument is always the data to be filtered.
			$data = $args[0];

			// Determine if the macro exists in the body. The body can be in an array key or an object property.
			$body_content = '';
			if ( is_array( $data ) && isset( $data['body'] ) ) {
				$body_content = $data['body'];
			} elseif ( is_object( $data ) && isset( $data->body ) ) {
				$body_content = $data->body;
			}

			if ( strpos( $body_content, '{{scp_unified_ticket}}' ) === false ) {
				return $data;
			}

			// Find the ticket object from the arguments passed by the hook.
			$ticket = null;
			foreach ( $args as $arg ) {
				if ( is_a( $arg, 'WPSC_Ticket' ) ) {
					$ticket = $arg;
					break;
				}
				if ( is_a( $arg, 'WPSC_Thread' ) ) {
					$ticket = $arg->ticket;
					break;
				}
				if ( 'wpsc_en_send_data' === current_filter() && is_a( $arg, 'WPSC_Background_Email' ) && ! empty( $arg->ticket_id ) ) {
					$ticket = new WPSC_Ticket( $arg->ticket_id );
					break;
				}
			}

			// If no ticket object is found, it's a non-ticket email (e.g., OTP). Replace macro with empty string.
			if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
				error_log( '[UTM_Core] scputm_replace_utm_macro() - EXIT (No valid WPSC_Ticket object found for hook: ' . current_filter() . ')' );
				$final_html = '';
			} else {
				error_log( '[UTM_Core] scputm_replace_utm_macro() - Processing Ticket ID: ' . $ticket->id );
				if ( 'wpsc_create_ticket_email_data' === current_filter() ) {
					$final_html = get_transient( 'scputm_temp_cache_' . $ticket->id );
					if ( false === $final_html ) {
						$final_html = $this->_scputm_build_live_utm_html( $ticket );
						error_log( '[UTM_Core] scputm_replace_utm_macro() - WARNING: Transient cache missed for new ticket. Regenerating live.' );
					} else {
						error_log( '[UTM_Core] scputm_replace_utm_macro() - Using transient cache for new ticket.' );
					}
				} else {
					$final_html = $this->_scputm_build_live_utm_html( $ticket );
					error_log( '[UTM_Core] scputm_replace_utm_macro() - Generating live HTML for existing ticket event: ' . current_filter() );
				}
			}

			error_log( '[UTM_Core] scputm_replace_utm_macro() - Final HTML: ' . $final_html );

			// Replace the macro in the original data structure.
			if ( is_array( $data ) && isset( $data['body'] ) ) {
				$data['body'] = str_replace( '{{scp_unified_ticket}}', $final_html, $data['body'] );
			} elseif ( is_object( $data ) && isset( $data->body ) ) {
				$data->body = str_replace( '{{scp_unified_ticket}}', $final_html, $data->body );
			}

			error_log( '[UTM_Core] scputm_replace_utm_macro() - Final data structure: ' . print_r( $data, true ) );

			return $data;

		} catch ( Throwable $e ) {
			error_log( '[UTM_Core] FATAL ERROR in scputm_replace_utm_macro(): ' . $e->getMessage() . ' on line ' . $e->getLine() . ' of ' . $e->getFile() );
			error_log( '[UTM_Core] Stack Trace: ' . $e->getTraceAsString() );
			return $original_data; // Return original, unmodified data to prevent email failure.
		}
	}
}

<?php
/**
 * Unified Ticket Macro Feature
 *
 * @package SupportCandy_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SCP_Unified_Ticket_Macro Class
 */
class SCP_Unified_Ticket_Macro {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'replace_macro' ), 10, 2 );
	}

	/**
	 * Register the {{scp_unified_ticket}} macro.
	 */
	public function register_macro( $macros ) {
		$macros['scp_unified_ticket'] = __( 'Unified Ticket Details', 'supportcandy-plus' );
		return $macros;
	}

	/**
	 * Replace the macro with the formatted ticket data.
	 */
	public function replace_macro( $body, $thread ) {
		if ( false === strpos( $body, '{{scp_unified_ticket}}' ) ) {
			return $body;
		}

		if ( ! $thread || ! $thread->ticket ) {
			return str_replace( '{{scp_unified_ticket}}', '', $body ); // Remove macro if ticket not found.
		}

		// Load the full ticket object.
		$ticket = new WPSC_Ticket( $thread->ticket );
		if ( ! $ticket->id ) {
			return str_replace( '{{scp_unified_ticket}}', '', $body );
		}

		$settings        = get_option( 'scp_settings', [] );
		$selected_fields = isset( $settings['utm_columns'] ) ? $settings['utm_columns'] : [];
		$all_columns_map = supportcandy_plus()->get_scp_utm_columns();
		$custom_fields_map = supportcandy_plus()->get_all_custom_field_data();

		$output = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
		$output .= '<tbody>';

		// Create a comprehensive but safe debug snapshot.
		$debug_snapshot = [
			'timestamp'         => date( 'Y-m-d H:i:s' ),
			'feature'           => 'Unified Ticket Macro',
			'ticket_id'         => $ticket->id,
			'selected_fields'   => $selected_fields,
			'ticket_data_array' => $ticket->to_array(),
			'custom_fields_raw' => $ticket->custom_fields,
			'data_found'        => [], // This will be populated below.
		];

		foreach ( $selected_fields as $slug ) {
			$field_label = isset( $all_columns_map[ $slug ] ) ? $all_columns_map[ $slug ] : $slug;
			$field_value = null;

			if ( isset( $custom_fields_map[ $slug ] ) ) {
				// It's a custom field.
				$cf_id = $custom_fields_map[ $slug ]['id'];
				if ( isset( $ticket->custom_fields[ $cf_id ] ) ) {
					$raw_value = $ticket->custom_fields[ $cf_id ];
					// Check if there are predefined options for this custom field.
					if ( ! empty( $custom_fields_map[ $slug ]['options'] ) && isset( $custom_fields_map[ $slug ]['options'][ $raw_value ] ) ) {
						// Map the stored value (ID) to its label.
						$field_value = $custom_fields_map[ $slug ]['options'][ $raw_value ];
					} else {
						// For fields like text, date, etc., that don't have predefined options.
						$field_value = $raw_value;
					}
				}
			} else {
				// It's a standard field.
				$value = null;
				switch ( $slug ) {
					case 'customer':
						$value = $ticket->created_by->name;
						break;
					case 'customer_email':
						$value = $ticket->created_by->email;
						break;
					case 'last_reply_on':
						$value = $ticket->last_reply; // Property name mismatch.
						break;
					default:
						// Use direct access to allow the __get magic method to work.
						$value = $ticket->$slug;
						if ( is_null( $value ) && isset( $thread->$slug ) ) {
							// Fallback to the thread object for fields like ip_address.
							$value = $thread->$slug;
						}
						break;
				}
				$field_value = $value;
			}

			// Format and append only if the value is not empty.
			if ( ! is_null( $field_value ) && '' !== $field_value ) {
				$formatted_value = $this->format_field_value( $field_value, $slug );
				$output         .= sprintf(
					'<tr><td style="width: 30%%;"><strong>%s:</strong></td><td>%s</td></tr>',
					esc_html( $field_label ),
					wp_kses_post( $formatted_value )
				);
				// Add to the snapshot for logging.
				$debug_snapshot['data_found'][ $slug ] = [
					'label'      => $field_label,
					'raw_value'  => is_object( $field_value ) ? 'Object' : ( is_array( $field_value ) ? 'Array' : $field_value ),
					'final_value' => $formatted_value,
				];
			}
		}

		$output .= '</tbody></table>';

		// Log the safe debug snapshot.
		$log_message = 'UTM Safe Debug Snapshot:' . "\n" . print_r( $debug_snapshot, true );
		error_log( $log_message, 3, SCP_PLUGIN_PATH . 'scp-utm-debug.log' );


		return str_replace( '{{scp_unified_ticket}}', $output, $body );
	}

	/**
	 * Format the field value based on its type.
	 */
	private function format_field_value( $value, $slug ) {
		// Handle DateTime objects.
		if ( $value instanceof DateTime ) {
			return $value->format( 'F j, Y, g:i a' );
		}

		// Handle other object types that might have a 'name' property (e.g., status, category).
		if ( is_object( $value ) && isset( $value->name ) ) {
			return $value->name;
		}

		// For other values, just return them as is.
		return $value;
	}
}

new SCP_Unified_Ticket_Macro();
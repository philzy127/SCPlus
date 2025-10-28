<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SCP_UTM {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$options = get_option( 'scp_settings', [] );
		if ( ! empty( $options['enable_utm'] ) ) {
			add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );

			// Hook into all relevant email notification filters to make the macro truly unified.
			$email_data_filters = [
				'wpsc_create_ticket_email_data',
				'wpsc_agent_reply_email_data',
				'wpsc_customer_reply_email_data',
				'wpsc_close_ticket_email_data',
			];

			foreach ( $email_data_filters as $filter ) {
				add_filter( $filter, array( $this, 'replace_utm_in_email' ), 10, 2 );
			}
		}
	}

	/**
	 * Add custom macros to the list.
	 */
	public function register_macro( $macros ) {
		$macros[] = array(
			'tag'   => '{{scp_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'supportcandy-plus' ),
		);
		return $macros;
	}

	/**
	 * Replace the UTM placeholder in notification emails.
	 * This function is hooked into multiple filters and acts as the core logic.
	 */
	public function replace_utm_in_email( $data, $thread ) {
		// Check if the macro exists in the email body.
		if ( strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			return $data;
		}

		// Get the ticket object from the thread.
		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			$this->log_message( 'Could not get a valid ticket object from the thread.' );
			// Replace with empty string if we can't get a valid ticket.
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}

		// --- SAFE DEBUGGING PROTOCOL ---
		// As per the technical guide, never log the full $ticket object.
		// Instead, build a simple array with the specific data points you need.
		$debug_snapshot = array(
			'ticket_id'   => $ticket->id,
			'subject'     => $ticket->subject,
			'status_name' => $ticket->status->name,
			'raw_db_data' => $ticket->to_array(), // Safest way to get all core data.
		);
		$this->log_message( 'Running UTM replacement for ticket. Safe debug snapshot: ' . print_r( $debug_snapshot, true ) );
		// --- END SAFE DEBUGGING ---

		// Get the admin-configured settings.
		$options = get_option( 'scp_settings', [] );
		$selected_columns = isset( $options['utm_columns'] ) ? $options['utm_columns'] : [];
		if ( empty( $selected_columns ) ) {
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}

		// Get maps of all possible field slugs to their display names and metadata.
		$all_columns_map = supportcandy_plus()->get_scp_utm_columns();
		$custom_fields_meta = supportcandy_plus()->get_all_custom_field_data();
		$output_rows = '';

		// Iterate through the selected columns and build the table rows.
		foreach ( $selected_columns as $slug ) {
			$value = null;
			$label = isset( $all_columns_map[ $slug ] ) ? $all_columns_map[ $slug ] : ucfirst( str_replace( '_', ' ', $slug ) );

			// Safely get the raw value for the field.
			$raw_value = $ticket->$slug;

			// If the raw value is null, it's likely a custom field.
			// The WPSC_Ticket object stores custom field values in the `custom_fields` array.
			if ( is_null( $raw_value ) && isset( $ticket->custom_fields ) && is_array( $ticket->custom_fields ) ) {
				foreach ( $ticket->custom_fields as $cf_obj ) {
					if ( is_object( $cf_obj ) && isset( $cf_obj->slug ) && $cf_obj->slug === $slug ) {
						$raw_value = $cf_obj->value;
						break;
					}
				}
			}

			// Process the raw value to get a displayable string.
			if ( isset( $custom_fields_meta[ $slug ] ) ) {
				// It's a custom field. Let's resolve its value.
				$field_meta = $custom_fields_meta[ $slug ];
				if ( ! empty( $field_meta['options'] ) && isset( $field_meta['options'][ $raw_value ] ) ) {
					// This is a choice-based field (select, radio) and we found the label.
					$value = $field_meta['options'][ $raw_value ];
				} else {
					// It's a text, date, or other type of field with no options.
					$value = (string) $raw_value;
				}
			} elseif ( is_object( $raw_value ) && property_exists( $raw_value, 'name' ) ) {
				// Standard field that is an object (e.g., status, priority).
				$value = $raw_value->name;
			} elseif ( is_array( $raw_value ) ) {
				// Standard field that is an array.
				$value = implode( ', ', $raw_value );
			} else {
				// Standard field that is a simple string or number.
				$value = (string) $raw_value;
			}

			// Only add the row if the value is not empty.
			if ( $value !== null && $value !== '' ) {
				$output_rows .= '<tr>';
				$output_rows .= '<td style="font-weight: bold; padding: 5px; background-color:#f7f7f7;">' . esc_html( $label ) . '</td>';
				$output_rows .= '<td style="padding: 5px;">' . wp_kses_post( $value ) . '</td>';
				$output_rows .= '</tr>';
			}
		}

		// Build the final HTML table.
		if ( ! empty( $output_rows ) ) {
			$html_table = '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;"><tbody>' . $output_rows . '</tbody></table>';
			$data['body'] = str_replace( '{{scp_unified_ticket}}', $html_table, $data['body'] );
		} else {
			// If no fields had a value, remove the macro completely.
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
		}

		return $data;
	}

	/**
	 * Helper function for logging debug messages to a file.
	 */
	private function log_message( $message ) {
		$log_file = SCP_PLUGIN_PATH . 'scp-utm-debug.log';
		$timestamp = wp_date( 'Y-m-d H:i:s' );
		$log_entry = sprintf( "[%s] %s\n", $timestamp, print_r( $message, true ) );
		file_put_contents( $log_file, $log_entry, FILE_APPEND );
	}
}

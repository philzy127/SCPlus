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
	 * Replace the UTM in the new ticket email.
	 */
	public function replace_utm_in_email( $data, $thread ) {
		$this->log_message( '--- Starting UTM Replacement Process ---' );
		if ( strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			$this->log_message( 'Macro {{scp_unified_ticket}} not found in email body. Skipping.' );
			return $data;
		}
		$this->log_message( 'Macro found in email body.' );

		$options = get_option( 'scp_settings', [] );
		$selected_columns = isset( $options['utm_columns'] ) ? $options['utm_columns'] : [];
		$this->log_message( 'Retrieved settings. Selected columns: ' . print_r( $selected_columns, true ) );

		if ( empty( $selected_columns ) ) {
			$this->log_message( 'No columns selected in settings. Replacing macro with empty string.' );
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}

		if ( ! class_exists( 'WPSC_Ticket' ) ) {
			$this->log_message( 'WPSC_Ticket class does not exist. Cannot proceed.' );
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}

		if ( ! isset( $thread->ticket ) || ! $thread->ticket ) {
			$this->log_message( 'Thread object does not contain a valid ticket ID.' );
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}
		$this->log_message( 'Loading ticket with ID: ' . $thread->ticket );
		$ticket = new WPSC_Ticket( $thread->ticket );

		if ( ! $ticket->id ) {
			$this->log_message( 'Failed to load ticket object.' );
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}
		$this->log_message( 'Ticket object loaded successfully.' );

		$all_columns_map = supportcandy_plus()->get_scp_utm_columns();
		$output = '<table>';
		$this->log_message( 'Starting to iterate through selected columns.' );

		foreach ( $selected_columns as $slug ) {
			$this->log_message( "Processing column with slug: '{$slug}'" );
			$value = null;
			$label = isset( $all_columns_map[ $slug ] ) ? $all_columns_map[ $slug ] : ucfirst( str_replace( '_', ' ', $slug ) );
			$this->log_message( "  - Label: '{$label}'" );

			// Standard fields
			if ( property_exists( $ticket, $slug ) ) {
				$value = $ticket->$slug;
				$this->log_message( "  - Found as a standard property. Raw value: " . print_r( $value, true ) );
			}

			// Custom fields are in a separate property
			if ( is_null( $value ) && isset( $ticket->custom_fields ) && is_array( $ticket->custom_fields ) ) {
				$this->log_message( '  - Not a standard property or was null. Checking custom fields...' );
				foreach ( $ticket->custom_fields as $cf ) {
					if ( is_object( $cf ) && isset( $cf->slug ) && $cf->slug === $slug ) {
						$value = $cf->value;
						$this->log_message( "  - Found as a custom field. Raw value: " . print_r( $value, true ) );
						break;
					}
				}
			}

			// Handle special cases and object values
			if ( is_object( $value ) && property_exists( $value, 'name' ) ) {
				$value = $value->name;
				$this->log_message( "  - Value is an object with a 'name' property. Processed value: '{$value}'" );
			} elseif ( is_array( $value ) ) {
				$value = implode( ', ', $value );
				$this->log_message( "  - Value is an array. Processed value: '{$value}'" );
			}

			if ( $value !== null && $value !== '' ) {
				$this->log_message( "  - Value is not empty. Adding to table." );
				$output .= '<tr>';
				$output .= '<td style="font-weight: bold; padding-right: 15px;">' . esc_html( $label ) . ':</td>';
				$output .= '<td>' . wp_kses_post( (string) $value ) . '</td>';
				$output .= '</tr>';
			} else {
				$this->log_message( "  - Value is null or empty. Skipping." );
			}
		}

		$output .= '</table>';
		$this->log_message( "Finished processing. Final HTML output:\n" . $output );

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $output, $data['body'] );

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

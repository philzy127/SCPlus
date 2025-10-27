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
		if ( strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			return $data;
		}

		$options = get_option( 'scp_settings', [] );
		$selected_columns = isset( $options['utm_columns'] ) ? $options['utm_columns'] : [];

		if ( empty( $selected_columns ) ) {
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}

		// The WPSC_Thread object is passed to this filter, not the ticket object.
		// We need to get the ticket ID from the thread to load the full ticket.
		if ( ! class_exists( 'WPSC_Ticket' ) || ! isset( $thread->ticket ) ) {
			$data['body'] = str_replace( '{{scp_unified_ticket}}', '', $data['body'] );
			return $data;
		}
		$ticket = new WPSC_Ticket( $thread->ticket );

		$all_columns_map = supportcandy_plus()->get_scp_utm_columns();
		$output = '<table>';

		foreach ( $selected_columns as $slug ) {
			$value = null;
			$label = isset( $all_columns_map[ $slug ] ) ? $all_columns_map[ $slug ] : ucfirst( str_replace( '_', ' ', $slug ) );

			// Standard fields
			if ( property_exists( $ticket, $slug ) ) {
				$value = $ticket->$slug;
			}

			// Custom fields are in a separate property
			if ( isset( $ticket->custom_fields ) && is_array( $ticket->custom_fields ) ) {
				foreach ( $ticket->custom_fields as $cf ) {
					if ( is_object( $cf ) && isset( $cf->slug ) && $cf->slug === $slug ) {
						$value = $cf->value;
						break;
					}
				}
			}

			// Handle special cases and object values
			if ( is_object( $value ) && property_exists( $value, 'name' ) ) {
				$value = $value->name;
			} elseif ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			if ( $value !== null && $value !== '' ) {
				$output .= '<tr>';
				$output .= '<td style="font-weight: bold; padding-right: 15px;">' . esc_html( $label ) . ':</td>';
				$output .= '<td>' . wp_kses_post( $value ) . '</td>';
				$output .= '</tr>';
			}
		}

		$output .= '</table>';

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $output, $data['body'] );

		return $data;
	}
}

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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the core logic.
	 */
	private function __construct() {
		$options = get_option( 'scp_settings', [] );
		if ( empty( $options['enable_utm'] ) ) {
			return;
		}

		// Cache builder hooks
		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_reply_ticket', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_status', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_priority', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_assign_agent', array( $this, 'scputm_update_utm_cache' ), 10, 1 );

		// Macro replacer hooks
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		// Register macro
		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
	}

	/**
	 * Register the macro.
	 */
	public function register_macro( $macros ) {
		$macros[] = array(
			'tag'   => '{{scp_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'supportcandy-plus' ),
		);
		return $macros;
	}

	/**
	 * Cache Builder Function.
	 *
	 * @param mixed $ticket_or_thread_or_id A WPSC_Ticket object, WPSC_Thread object, or a ticket ID.
	 */
	public function scputm_update_utm_cache( $ticket_or_thread_or_id ) {

		$ticket = null;

		if ( is_a( $ticket_or_thread_or_id, 'WPSC_Ticket' ) ) {
			$ticket = $ticket_or_thread_or_id;
		} elseif ( is_a( $ticket_or_thread_or_id, 'WPSC_Thread' ) ) {
			$ticket = $ticket_or_thread_or_id->ticket;
		} elseif ( is_numeric( $ticket_or_thread_or_id ) ) {
			$ticket = new WPSC_Ticket( intval( $ticket_or_thread_or_id ) );
		}

		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			return; // Exit if we couldn't get a valid ticket object.
		}

		$options = get_option( 'scp_settings', [] );
		$selected_fields = isset( $options['scputm_selected_fields'] ) && is_array( $options['scputm_selected_fields'] ) ? $options['scputm_selected_fields'] : [];
		$field_map = isset( $options['scputm_field_map'] ) && is_array( $options['scputm_field_map'] ) ? $options['scputm_field_map'] : [];

		if ( empty( $selected_fields ) ) {
			$html_output = '<table></table>';
		} else {
			$html_output = '<table>';

			foreach ( $selected_fields as $field_slug ) {
				$field_value = $ticket->{$field_slug};
				if ( ! empty( $field_value ) ) {
					$field_name = isset( $field_map[ $field_slug ] ) ? $field_map[ $field_slug ] : $field_slug;

					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', $field_value );
					}

					$html_output .= '<tr>';
					$html_output .= '<td>' . esc_html( $field_name ) . ':</td>';
					$html_output .= '<td>' . esc_html( $field_value ) . '</td>';
					$html_output .= '</tr>';
				}
			}
			$html_output .= '</table>';
		}

		$misc_data = $ticket->misc;
		$misc_data['scputm_utm_html'] = $html_output;
		$ticket->misc = $misc_data;
		$ticket->save();
	}


	/**
	 * Macro Replacer Function.
	 *
	 * @param array      $data   Email data.
	 * @param WPSC_Thread $thread Thread object.
	 * @return array
	 */
	public function scputm_replace_utm_macro( $data, $thread ) {

		if ( strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			return $data;
		}

		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			return $data;
		}

		$misc_data = $ticket->misc;
		$cached_html = isset( $misc_data['scputm_utm_html'] ) ? $misc_data['scputm_utm_html'] : '';

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $cached_html, $data['body'] );

		return $data;
	}
}

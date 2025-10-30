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

		// === NEW ARCHITECTURE: DELAY THE EMAIL ===

		// 1. When a new ticket is created, schedule our delayed email job.
		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_schedule_delayed_email' ), 10, 1 );

		// 2. Hook into WordPress's scheduler for our custom event.
		add_action( 'scputm_send_delayed_email_hook', array( $this, 'scputm_send_delayed_email_action' ), 10, 1 );

		// 3. Intercept and disable the default "New Ticket Created" email to prevent it from sending instantly.
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_disable_default_new_ticket_email' ), 999, 1 );

		// === STANDARD CACHE AND MACRO HOOKS ===

		// Hooks for updating the cache on ticket updates (replies, status changes, etc.).
		add_action( 'wpsc_after_reply_ticket', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_status', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_priority', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_assign_agent', array( $this, 'scputm_update_utm_cache' ), 10, 1 );

		// Hooks for replacing the macro in all relevant emails.
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		// Register macro tag.
		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
	}

	/**
	 * Schedule the background job to build the cache and send the delayed email.
	 */
	public function scputm_schedule_delayed_email( $ticket_id ) {
		wp_schedule_single_event( time() + 15, 'scputm_send_delayed_email_hook', array( 'ticket_id' => $ticket_id ) );
	}

	/**
	 * The action that runs via WP-Cron to perform the delayed tasks.
	 */
	public function scputm_send_delayed_email_action( $args ) {
		$ticket_id = isset( $args['ticket_id'] ) ? intval( $args['ticket_id'] ) : 0;
		if ( ! $ticket_id ) {
			return;
		}

		// First, build and save the cache so it's ready for the email.
		$this->scputm_update_utm_cache( $ticket_id );

		// Temporarily remove our disabling filter so the email can be sent.
		remove_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_disable_default_new_ticket_email' ), 999 );

		// Manually trigger the "New Ticket Created" email notification.
		if ( class_exists('WPSC_Email') ) {
			$wpsc_email = new WPSC_Email();
			if ( method_exists( $wpsc_email, 'create_ticket' ) ) {
				$wpsc_email->create_ticket( $ticket_id );
			}
		}

		// Add the disabling filter back for the next ticket submission.
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_disable_default_new_ticket_email' ), 999, 1 );
	}

	/**
	 * Disables the default "New Ticket Created" email by returning false.
	 */
	public function scputm_disable_default_new_ticket_email( $data ) {
		return false;
	}

	/**
	 * Register the macro tag so it appears in the UI.
	 */
	public function register_macro( $macros ) {
		$macros[] = array(
			'tag'   => '{{scp_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'supportcandy-plus' ),
		);
		return $macros;
	}

	/**
	 * Private helper to build the HTML for the macro.
	 */
	private function _scputm_build_live_utm_html( $ticket ) {
		$options = get_option( 'scp_settings', [] );
		$selected_fields = isset( $options['scputm_selected_fields'] ) && is_array( $options['scputm_selected_fields'] ) ? $options['scputm_selected_fields'] : [];
		$field_map = isset( $options['scputm_field_map'] ) && is_array( $options['scputm_field_map'] ) ? $options['scputm_field_map'] : [];

		if ( empty( $selected_fields ) ) return '<table></table>';

		$html_output = '<table>';
		foreach ( $selected_fields as $field_slug ) {
			$field_value = $ticket->{$field_slug};
			if ( ! empty( $field_value ) ) {
				$field_name = isset( $field_map[ $field_slug ] ) ? $field_map[ $field_slug ] : $field_slug;
				if ( is_array( $field_value ) ) $field_value = implode( ', ', $field_value );
				$html_output .= '<tr><td>' . esc_html( $field_name ) . ':</td><td>' . esc_html( $field_value ) . '</td></tr>';
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}

	/**
	 * Builds and SAVES the macro HTML to the ticket's cache.
	 */
	public function scputm_update_utm_cache( $ticket_or_thread_or_id ) {
		$ticket = null;
		if ( is_a( $ticket_or_thread_or_id, 'WPSC_Ticket' ) ) $ticket = $ticket_or_thread_or_id;
		elseif ( is_a( $ticket_or_thread_or_id, 'WPSC_Thread' ) ) $ticket = $ticket_or_thread_or_id->ticket;
		elseif ( is_numeric( $ticket_or_thread_or_id ) ) $ticket = new WPSC_Ticket( intval( $ticket_or_thread_or_id ) );

		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) return;

		$html_to_cache = $this->_scputm_build_live_utm_html( $ticket );

		$misc_data = $ticket->misc;
		$misc_data['scputm_utm_html'] = $html_to_cache;
		$ticket->misc = $misc_data;
		$ticket->save();
	}

	/**
	 * Replaces the macro in the email body with the cached content.
	 */
	public function scputm_replace_utm_macro( $data, $thread ) {
		// If our disabling hook has already returned false, respect that.
		if ( $data === false ) return false;

		if ( strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) return $data;

		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) return $data;

		$misc_data   = $ticket->misc;
		$cached_html = isset( $misc_data['scputm_utm_html'] ) ? $misc_data['scputm_utm_html'] : '';

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $cached_html, $data['body'] );

		return $data;
	}
}

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

	public static function get_instance() {
		error_log('[UTM] SCPUTM_Core::get_instance() - Enter');
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		error_log('[UTM] SCPUTM_Core::get_instance() - Exit');
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

		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_schedule_delayed_email' ), 10, 1 );
		add_action( 'scputm_send_delayed_email_hook', array( $this, 'scputm_send_delayed_email_action' ), 10, 1 );

		// New interception logic
		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_flag_new_ticket_email' ), 10, 1 );
		add_filter( 'pre_wp_mail', array( $this, 'scputm_intercept_wp_mail' ), 10, 2 );

		add_action( 'wpsc_after_reply_ticket', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_status', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_priority', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_assign_agent', array( $this, 'scputm_update_utm_cache' ), 10, 1 );

		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
		error_log('[UTM] SCPUTM_Core::__construct() - Exit');
	}

	public function scputm_schedule_delayed_email( $ticket_id ) {
		error_log('[UTM] scputm_schedule_delayed_email() - Enter');
		wp_schedule_single_event( time() + 15, 'scputm_send_delayed_email_hook', array( $ticket_id ) );
		error_log('[UTM] scputm_schedule_delayed_email() - Exit');
	}

	public function scputm_send_delayed_email_action( $ticket_id ) {
		error_log('[UTM] scputm_send_delayed_email_action() - Enter');
		if ( ! $ticket_id ) {
			error_log('[UTM] scputm_send_delayed_email_action() - Exit (No Ticket ID)');
			return;
		}

		$this->scputm_update_utm_cache( $ticket_id );

		if ( class_exists('WPSC_Email') ) {
			$wpsc_email = new WPSC_Email();
			if ( method_exists( $wpsc_email, 'create_ticket' ) ) {
				$wpsc_email->create_ticket( $ticket_id );
			}
		}
		error_log('[UTM] scputm_send_delayed_email_action() - Exit');
	}

	public function scputm_flag_new_ticket_email( $data ) {
		error_log('[UTM] scputm_flag_new_ticket_email() - Enter');
		$this->is_intercepting = true;
		error_log('[UTM] scputm_flag_new_ticket_email() - Exit');
		return $data;
	}

	public function scputm_intercept_wp_mail( $null, $atts ) {
		error_log('[UTM] scputm_intercept_wp_mail() - Enter');
		if ( $this->is_intercepting ) {
			$this->is_intercepting = false;
			error_log('[UTM] scputm_intercept_wp_mail() - Exit (Intercepting)');
			return true;
		}
		error_log('[UTM] scputm_intercept_wp_mail() - Exit (Not Intercepting)');
		return null;
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
		$selected_fields = isset( $options['scputm_selected_fields'] ) && is_array( $options['scputm_selected_fields'] ) ? $options['scputm_selected_fields'] : [];
		$field_map = isset( $options['scputm_field_map'] ) && is_array( $options['scputm_field_map'] ) ? $options['scputm_field_map'] : [];

		if ( empty( $selected_fields ) ) {
			error_log('[UTM] _scputm_build_live_utm_html() - Exit (No Fields)');
			return '<table></table>';
		}

		$html_output = '<table>';
		foreach ( $selected_fields as $field_slug ) {
			$field_value = $ticket->{$field_slug};
			if ( ! empty( $field_value ) ) {
				$field_name = isset( $field_map[ $field_slug ] ) ? $field_map[ $field_slug ] : $field_slug;
				if ( is_a( $field_value, 'WPSC_Option' ) || is_a( $field_value, 'WPSC_Category' ) || is_a( $field_value, 'WPSC_Priority' ) || is_a( $field_value, 'WPSC_Status' ) ) {
					$field_value = $field_value->name;
				}
				if ( is_a( $field_value, 'WPSC_Customer' ) ) {
					$field_value = isset( $field_value->display_name ) ? $field_value->display_name : $field_value->name;
				}
				if ( $field_value instanceof DateTime ) {
					$field_value = $field_value->format('m/d/Y');
				}
				if ( is_array( $field_value ) ) {
					$display_values = array();
					foreach ( $field_value as $value ) {
						if ( is_a( $value, 'WPSC_Agent' ) ) {
							$display_values[] = $value->name;
						} else {
							$display_values[] = $value;
						}
					}
					$field_value = implode( ', ', $display_values );
				}
				$html_output .= '<tr><td>' . esc_html( $field_name ) . ':</td><td>' . esc_html( $field_value ) . '</td></tr>';
			}
		}
		$html_output .= '</table>';
		error_log('[UTM] _scputm_build_live_utm_html() - Exit');
		return $html_output;
	}

	public function scputm_update_utm_cache( $ticket_or_thread_or_id ) {
		error_log('[UTM] scputm_update_utm_cache() - Enter');
		$ticket = null;
		if ( is_a( $ticket_or_thread_or_id, 'WPSC_Ticket' ) ) $ticket = $ticket_or_thread_or_id;
		elseif ( is_a( $ticket_or_thread_or_id, 'WPSC_Thread' ) ) $ticket = $ticket_or_thread_or_id->ticket;
		elseif ( is_numeric( $ticket_or_thread_or_id ) ) $ticket = new WPSC_Ticket( intval( $ticket_or_thread_or_id ) );

		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			error_log('[UTM] scputm_update_utm_cache() - Exit (Invalid Ticket)');
			return;
		}

		$html_to_cache = $this->_scputm_build_live_utm_html( $ticket );

		$misc_data = $ticket->misc;
		$misc_data['scputm_utm_html'] = $html_to_cache;
		$ticket->misc = $misc_data;
		$ticket->save();
		error_log('[UTM] scputm_update_utm_cache() - Exit');
	}

	public function scputm_replace_utm_macro( $data, $thread ) {
		error_log('[UTM] scputm_replace_utm_macro() - Enter');
		if ( ! is_array($data) || strpos( $data['body'], '{{scp_unified_ticket}}' ) === false ) {
			error_log('[UTM] scputm_replace_utm_macro() - Exit (Macro not found or invalid data)');
			return $data;
		}
		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			error_log('[UTM] scputm_replace_utm_macro() - Exit (Invalid Ticket)');
			return $data;
		}
		$misc_data   = $ticket->misc;
		$cached_html = isset( $misc_data['scputm_utm_html'] ) ? $misc_data['scputm_utm_html'] : '';
		$data['body'] = str_replace( '{{scp_unified_ticket}}', $cached_html, $data['body'] );
		error_log('[UTM] scputm_replace_utm_macro() - Exit');
		return $data;
	}
}

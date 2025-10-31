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

		add_action( 'wpsc_create_new_ticket', array( $this, 'scputm_prime_cache_on_creation' ), 5, 1 );

		add_action( 'wpsc_after_reply_ticket', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_status', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_change_ticket_priority', array( $this, 'scputm_update_utm_cache' ), 10, 1 );
		add_action( 'wpsc_after_assign_agent', array( $this, 'scputm_update_utm_cache' ), 10, 1 );

		add_filter( 'wpsc_create_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', array( $this, 'scputm_replace_utm_macro' ), 10, 2 );

		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );
		error_log('[UTM] SCPUTM_Core::__construct() - Exit');
	}

	public function scputm_prime_cache_on_creation( $ticket_id ) {
		error_log('[UTM] scputm_prime_cache_on_creation() - Enter');
		$ticket = new WPSC_Ticket( intval( $ticket_id ) );
		if ( ! $ticket->id ) {
			error_log('[UTM] scputm_prime_cache_on_creation() - Exit (Invalid Ticket)');
			return;
		}

		$html_to_cache = $this->_scputm_build_live_utm_html( $ticket );

		// Use a transient for instant availability. Expires in 1 minute.
		set_transient( 'scputm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
		error_log('[UTM] scputm_prime_cache_on_creation() - Transient set.');

		// Defer the permanent save to avoid recursion.
		add_action( 'shutdown', array( $this, 'scputm_deferred_save' ), 10, 1 );

		// Pass the ticket object to the shutdown action.
		$this->deferred_ticket_to_save = $ticket;

		error_log('[UTM] scputm_prime_cache_on_creation() - Exit');
	}

	public function scputm_deferred_save() {
		error_log('[UTM] scputm_deferred_save() - Enter');
		if ( isset( $this->deferred_ticket_to_save ) && is_a( $this->deferred_ticket_to_save, 'WPSC_Ticket' ) ) {
			$ticket = $this->deferred_ticket_to_save;
			$html_to_cache = get_transient( 'scputm_temp_cache_' . $ticket->id );

			if ( $html_to_cache !== false ) {
				$misc_data = $ticket->misc;
				$misc_data['scputm_utm_html'] = $html_to_cache;
				$ticket->misc = $misc_data;

				// This is now safe to call.
				$ticket->save();
				error_log('[UTM] scputm_deferred_save() - Permanent cache saved.');

				// Clean up the transient.
				delete_transient( 'scputm_temp_cache_' . $ticket->id );
			}
			unset( $this->deferred_ticket_to_save );
		}
		error_log('[UTM] scputm_deferred_save() - Exit');
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

				error_log('[UTM] Processing field: ' . $field_slug . ' of type ' . gettype($field_value));

				if ( is_a( $field_value, 'WPSC_Option' ) || is_a( $field_value, 'WPSC_Category' ) || is_a( $field_value, 'WPSC_Priority' ) || is_a( $field_value, 'WPSC_Status' ) ) {
					error_log('[UTM] Field is an object with a name property.');
					$field_value = $field_value->name;
				}
				if ( is_a( $field_value, 'WPSC_Customer' ) ) {
					error_log('[UTM] Field is a WPSC_Customer object.');
					$field_value = isset( $field_value->display_name ) ? $field_value->display_name : $field_value->name;
				}
				if ( $field_value instanceof DateTime ) {
					error_log('[UTM] Field is a DateTime object.');
					$field_value = $field_value->format('m/d/Y');
				}
				if ( is_array( $field_value ) ) {
					error_log('[UTM] Field is an array.');
					$display_values = array();
					foreach ( $field_value as $value ) {
						if ( is_a( $value, 'WPSC_Agent' ) ) {
							error_log('[UTM] Array item is a WPSC_Agent object.');
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
		error_log('[UTM] scputm_replace_utm_macro() - Initial body: ' . print_r($data, true));

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
			$cached_html = $transient_html;
			error_log('[UTM] scputm_replace_utm_macro() - Using transient cache.');
		} else {
			$misc_data   = $ticket->misc;
			$cached_html = isset( $misc_data['scputm_utm_html'] ) ? $misc_data['scputm_utm_html'] : '';
			error_log('[UTM] scputm_replace_utm_macro() - Using permanent cache.');
		}

		error_log('[UTM] scputm_replace_utm_macro() - Cached HTML: ' . $cached_html);

		$data['body'] = str_replace( '{{scp_unified_ticket}}', $cached_html, $data['body'] );
		error_log('[UTM] scputm_replace_utm_macro() - Final body: ' . $data['body']);

		return $data;
	}
}

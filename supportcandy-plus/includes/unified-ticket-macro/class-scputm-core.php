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

		// Get all available columns to map slugs to friendly names.
		$all_columns = supportcandy_plus()->get_supportcandy_columns();

		if ( empty( $selected_fields ) ) {
			error_log('[UTM] _scputm_build_live_utm_html() - Exit (No Fields)');
			return '<table></table>';
		}

		global $wpdb;
		$custom_fields_table    = $wpdb->prefix . 'psmsc_custom_fields';
		$custom_fields_from_db  = $wpdb->get_results( "SELECT slug, type FROM {$custom_fields_table}", ARRAY_A );
		$custom_field_types     = wp_list_pluck( $custom_fields_from_db, 'type', 'slug' );

		// Create a definitive map of all known standard field types.
		$standard_field_types = array(
			'id'                       => 'df_id',
			'subject'                  => 'df_subject',
			'description'              => 'df_description',
			'ip_address'               => 'df_ip_address',
			'browser'                  => 'df_browser',
			'os'                       => 'df_os',
			'source'                   => 'df_source',
			'last_reply_source'        => 'df_last_reply_source',
			'user_type'                => 'df_user_type',
			'customer_name'            => 'df_customer_name',
			'customer_email'           => 'df_customer_email',
			'date_created'             => 'df_date_created',
			'date_updated'             => 'df_date_updated',
			'date_closed'              => 'df_date_closed',
			'last_reply_on'            => 'df_last_reply_on',
			'status'                   => 'df_status',
			'priority'                 => 'df_priority',
			'category'                 => 'df_category',
			'customer'                 => 'df_customer',
			'agent_created'            => 'df_agent_created',
			'last_reply_by'            => 'df_last_reply_by',
			'assigned_agent'           => 'df_assigned_agent',
			'prev_assignee'            => 'df_prev_assignee',
			'tags'                     => 'df_tags',
			'add_recipients'           => 'df_additional_recipients',
		);

		// Merge the standard and custom field maps. Custom fields will override standard ones in case of a slug conflict.
		$field_types_map = array_merge( $standard_field_types, $custom_field_types );

		$html_output  = '<table>';
		$ticket_array = $ticket->to_array();

		foreach ( $selected_fields as $field_slug ) {

			// Defensive check to prevent warnings for non-existent properties.
			if ( ! array_key_exists( $field_slug, $ticket_array ) ) {
				continue;
			}

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

			$field_name    = isset( $all_columns[ $field_slug ] ) ? $all_columns[ $field_slug ] : $field_slug;
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
					if ( is_object( $field_value ) && ! empty( $field_value->name ) ) {
						$display_value = $field_value->name;
					}
					break;

				// Array of Object References
				case 'cf_multi_select':
				case 'cf_checkbox':
				case 'cf_file-attachment-multiple':
				case 'df_assigned_agent':
				case 'df_prev_assignee':
				case 'df_tags':
				case 'df_additional_recipients':
					if ( is_array( $field_value ) ) {
						$names = array();
						foreach ( $field_value as $item ) {
							if ( is_object( $item ) && ! empty( $item->name ) ) {
								$names[] = $item->name;
							}
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

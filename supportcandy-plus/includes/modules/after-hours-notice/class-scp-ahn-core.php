<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SCP_AHN_Core {

	private static $instance = null;
	private $options = [];

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->options = get_option( 'scp_settings', [] );
		add_filter( 'wpsc_create_ticket_email_data', [ $this, 'add_after_hours_notice' ] );
		add_filter( 'wpsc_agent_reply_email_data', [ $this, 'add_after_hours_notice' ] );
		add_filter( 'wpsc_cust_reply_email_data', [ $this, 'add_after_hours_notice' ] );
	}

	/**
	 * Check if it is currently after hours.
	 *
	 * @return boolean
	 */
	private function is_after_hours() {
		$start_hour       = ! empty( $this->options['after_hours_start'] ) ? (int) $this->options['after_hours_start'] : 17;
		$end_hour         = ! empty( $this->options['before_hours_end'] ) ? (int) $this->options['before_hours_end'] : 8;
		$include_weekends = ! empty( $this->options['include_all_weekends'] );
		$holidays         = ! empty( $this->options['holidays'] ) ? array_map( 'trim', explode( "\n", $this->options['holidays'] ) ) : [];

		$current_timestamp = current_time( 'timestamp' );
		$current_hour      = (int) date( 'H', $current_timestamp );
		$day_of_week       = (int) date( 'w', $current_timestamp ); // 0 (for Sunday) through 6 (for Saturday)
		$current_date      = date( 'm-d-Y', $current_timestamp );

		// Check for holidays.
		if ( in_array( $current_date, $holidays, true ) ) {
			return true;
		}

		// Check for weekends.
		if ( $include_weekends && ( $day_of_week === 0 || $day_of_week === 6 ) ) {
			return true;
		}

		// Check for time.
		if ( $current_hour >= $start_hour || $current_hour < $end_hour ) {
			return true;
		}

		return false;
	}

	/**
	 * Add the after hours notice to the email body.
	 *
	 * @param array $email_data The email data.
	 * @return array
	 */
	public function add_after_hours_notice( $email_data ) {
		// Check if the feature is enabled.
		if ( empty( $this->options['enable_after_hours_notice'] ) || empty( $this->options['after_hours_in_email'] ) ) {
			return $email_data;
		}

		// Check if it's after hours.
		if ( ! $this->is_after_hours() ) {
			return $email_data;
		}

		$message = ! empty( $this->options['after_hours_message'] ) ? wpautop( wp_kses_post( $this->options['after_hours_message'] ) ) : '';
		if ( ! $message ) {
			return $email_data;
		}

		// Prepend the message to the email body.
		if ( isset( $email_data['body'] ) ) {
			$email_data['body'] = $message . '<hr>' . $email_data['body'];
		}

		return $email_data;
	}
}

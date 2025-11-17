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

		// Check if it's after hours using the centralized function.
		if ( ! supportcandy_plus()->is_after_hours() ) {
			return $email_data;
		}

		$message = ! empty( $this->options['after_hours_message'] ) ? wpautop( wp_kses_post( $this->options['after_hours_message'] ) ) : '';
		if ( ! $message ) {
			return $email_data;
		}

		// Prepend the message to the email body.
		if ( isset( $email_data['body'] ) ) {
			$styled_message = '<div style="background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px; margin: 15px 0; border-radius: 4px; font-size: 16px;">' . $message . '</div>';
			$email_data['body'] = $styled_message . $email_data['body'];
		}

		return $email_data;
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class SCP_After_Ticket_Survey {

	private static $instance = null;

	private $db_version = '2.16';
	private $questions_table_name;
	private $dropdown_options_table_name;
	private $survey_submissions_table_name;
	private $survey_answers_table_name;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;

		$this->questions_table_name        = $wpdb->prefix . 'scp_ats_questions';
		$this->dropdown_options_table_name = $wpdb->prefix . 'scp_ats_dropdown_options';
		$this->survey_submissions_table_name = $wpdb->prefix . 'scp_ats_survey_submissions';
		$this->survey_answers_table_name   = $wpdb->prefix . 'scp_ats_survey_answers';

		$this->init_hooks();
	}

	private function init_hooks() {
		register_activation_hook( SCP_PLUGIN_FILE, array( $this, 'install' ) );
		add_action( 'plugins_loaded', array( $this, 'check_db_version' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_shortcode( 'scp_after_ticket_survey', array( $this, 'survey_shortcode' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action( 'admin_post_scp_ats_manage_questions', array( $this, 'handle_manage_questions' ) );
		add_action( 'admin_post_scp_ats_manage_submissions', array( $this, 'handle_manage_submissions' ) );
		add_action( 'admin_post_scp_ats_import_settings', array( $this, 'handle_import_settings' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function check_db_version() {
		if ( get_option( 'scp_ats_db_version' ) !== $this->db_version ) {
			$this->install();
		}
	}

	public function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_questions = "CREATE TABLE {$this->questions_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			question_text text NOT NULL,
			question_type varchar(50) NOT NULL,
			sort_order int(11) DEFAULT 0 NOT NULL,
			is_required tinyint(1) DEFAULT 1 NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql_questions );

		$sql_dropdown_options = "CREATE TABLE {$this->dropdown_options_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			question_id bigint(20) NOT NULL,
			option_value varchar(255) NOT NULL,
			sort_order int(11) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY question_id (question_id)
		) $charset_collate;";
		dbDelta( $sql_dropdown_options );

		$sql_submissions = "CREATE TABLE {$this->survey_submissions_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) DEFAULT 0 NOT NULL,
			submission_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql_submissions );

		$sql_answers = "CREATE TABLE {$this->survey_answers_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) NOT NULL,
			question_id bigint(20) NOT NULL,
			answer_value text,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY question_id (question_id)
		) $charset_collate;";
		dbDelta( $sql_answers );

		if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questions_table_name}" ) == 0 ) {
			$default_questions = array(
				array( 'text' => 'What is your ticket number?', 'type' => 'short_text', 'required' => 1, 'initial_sort_order' => 0 ),
				array( 'text' => 'Who was your technician for this ticket?', 'type' => 'dropdown', 'options' => array('Technician A', 'Technician B', 'Technician C', 'Technician D'), 'required' => 1, 'initial_sort_order' => 1 ),
				array( 'text' => 'Overall, how would you rate the handling of your issue by the IT department?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 2 ),
				array( 'text' => 'Were you helped in a timely manner?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 3 ),
				array( 'text' => 'Was your technician helpful?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 4 ),
				array( 'text' => 'Was your technician courteous?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 5 ),
				array( 'text' => 'Did your technician demonstrate a reasonable understanding of your issue?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 6 ),
				array( 'text' => 'Do you feel we could make an improvement, or have concerns about how your ticket was handled?', 'type' => 'long_text', 'required' => 0, 'initial_sort_order' => 7 ),
			);
			foreach ( $default_questions as $q_data ) {
				$wpdb->insert( $this->questions_table_name, array( 'question_text' => $q_data['text'], 'question_type' => $q_data['type'], 'sort_order' => $q_data['initial_sort_order'], 'is_required' => $q_data['required'] ) );
				$question_id = $wpdb->insert_id;
				if ( $q_data['type'] === 'dropdown' && ! empty( $q_data['options'] ) ) {
					$option_sort_order = 0;
					foreach ( $q_data['options'] as $option_value ) {
						$wpdb->insert( $this->dropdown_options_table_name, array( 'question_id' => $question_id, 'option_value' => $option_value, 'sort_order' => $option_sort_order++ ) );
					}
				}
			}
		}

		update_option( 'scp_ats_db_version', $this->db_version );
	}

	public function enqueue_frontend_styles() {
		if ( is_singular() && has_shortcode( get_post()->post_content, 'scp_after_ticket_survey' ) ) {
			wp_enqueue_style( 'scp-ats-frontend-styles', SCP_PLUGIN_URL . 'assets/css/scp-ats-frontend-styles.css', array(), $this->db_version );
			$options = get_option( 'scp_settings' );
			$bg_color = ! empty( $options['ats_background_color'] ) ? $options['ats_background_color'] : '#f0f0f0';
			wp_add_inline_style( 'scp-ats-frontend-styles', 'body { background-color: ' . esc_attr( $bg_color ) . ' !important; }' );
		}
	}

	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'supportcandy-plus_page_scp-ats-survey' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'scp-ats-admin-styles', SCP_PLUGIN_URL . 'assets/admin/css/scp-ats-admin-styles.css', array(), $this->db_version );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
		if ( 'settings' === $current_tab ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'scp-ats-color-picker', SCP_PLUGIN_URL . 'assets/admin/js/scp-ats-color-picker.js', array( 'wp-color-picker' ), $this->db_version, true );
		}
	}

	public function survey_shortcode() {
		ob_start();
		if ( ! is_admin() && isset( $_POST['scp_ats_submit_survey'] ) && isset( $_POST['scp_ats_survey_nonce'] ) && wp_verify_nonce( $_POST['scp_ats_survey_nonce'], 'scp_ats_survey_form_nonce' ) ) {
			$this->handle_survey_submission();
		} else {
			$this->display_survey_form();
		}
		return ob_get_clean();
	}

	private function handle_survey_submission() {
		global $wpdb;
		$questions = $wpdb->get_results( "SELECT id, question_type, is_required FROM {$this->questions_table_name}", ARRAY_A );
		$user_id = get_current_user_id();
		$wpdb->insert( $this->survey_submissions_table_name, array( 'user_id' => $user_id, 'submission_date' => current_time( 'mysql' ) ) );
		$submission_id = $wpdb->insert_id;
		if ( $submission_id ) {
			foreach ( $questions as $question ) {
				$input_name = 'scp_ats_q_' . $question['id'];
				if ( isset( $_POST[ $input_name ] ) ) {
					$wpdb->insert( $this->survey_answers_table_name, array( 'submission_id' => $submission_id, 'question_id' => $question['id'], 'answer_value' => sanitize_textarea_field( $_POST[ $input_name ] ) ) );
				}
			}
			echo '<div class="ats-success-message">Thank you for completing our survey! Your feedback is invaluable and helps us improve our services.</div>';
		} else {
			echo '<div class="ats-error-message">There was an error submitting your survey. Please try again.</div>';
		}
	}

	private function display_survey_form() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$ticket_question_id = ! empty( $options['ats_ticket_question_id'] ) ? (int) $options['ats_ticket_question_id'] : 0;
		$technician_question_id = ! empty( $options['ats_technician_question_id'] ) ? (int) $options['ats_technician_question_id'] : 0;
		$prefill_ticket_id = isset( $_GET['ticket_id'] ) ? sanitize_text_field( $_GET['ticket_id'] ) : '';
		$prefill_tech_name = isset( $_GET['tech'] ) ? sanitize_text_field( $_GET['tech'] ) : '';
		$questions = $wpdb->get_results( "SELECT id, question_text, question_type, is_required FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		if ( empty( $questions ) ) {
			echo '<p class="ats-no-questions-message">No survey questions configured yet. Please contact the administrator.</p>';
			return;
		}
		?>
		<div class="ats-survey-container">
			<p class="ats-intro-text">We are committed to providing excellent IT support. Your feedback helps us assess our performance and identify areas for improvement.</p>
			<form method="post" class="ats-form">
				<?php wp_nonce_field( 'scp_ats_survey_form_nonce', 'scp_ats_survey_nonce' ); ?>
				<?php foreach ( $questions as $q_num => $q ) : ?>
					<div class="ats-form-group">
						<label for="scp_ats_q_<?php echo $q['id']; ?>" class="ats-label"><?php echo ( $q_num + 1 ) . '. ' . esc_html( $q['question_text'] ); ?><?php if ( $q['is_required'] ) echo ' <span class="ats-required-label">*</span>'; ?></label>
						<?php $this->render_question_field( $q, $options, $prefill_ticket_id, $prefill_tech_name ); ?>
					</div>
				<?php endforeach; ?>
				<button type="submit" name="scp_ats_submit_survey" class="ats-submit-button">Submit Survey</button>
			</form>
		</div>
		<?php
	}

	private function render_question_field( $question, $options, $prefill_ticket_id, $prefill_tech_name ) {
		global $wpdb;
		$input_name = 'scp_ats_q_' . $question['id'];
		$required_attr = $question['is_required'] ? 'required' : '';
		$input_value = ( $question['id'] == ( $options['ats_ticket_question_id'] ?? 0 ) && $prefill_ticket_id ) ? esc_attr( $prefill_ticket_id ) : '';
		switch ( $question['question_type'] ) {
			case 'short_text':
				echo "<input type=\"text\" name=\"{$input_name}\" value=\"{$input_value}\" class=\"ats-input ats-short-text\" {$required_attr}>";
				break;
			case 'long_text':
				echo "<textarea name=\"{$input_name}\" rows=\"4\" class=\"ats-input ats-long-text\" {$required_attr}></textarea>";
				break;
			case 'rating':
				echo '<div class="ats-rating-options">';
				for ( $i = 1; $i <= 5; $i++ ) {
					echo "<label class=\"ats-radio-label\"><input type=\"radio\" name=\"{$input_name}\" value=\"{$i}\" class=\"ats-radio-input\" {$required_attr}><span class=\"ats-radio-text\">{$i}</span></label>";
				}
				echo '<span class="ats-rating-guide">(1 = Poor, 5 = Excellent)</span></div>';
				break;
			case 'dropdown':
				$dd_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question['id'] ) );
				echo "<select name=\"{$input_name}\" class=\"ats-input ats-dropdown\" {$required_attr}>";
				echo '<option value="">-- Select --</option>';
				foreach ( $dd_options as $opt ) {
					$selected = '';
					if ( $question['id'] == ( $options['ats_technician_question_id'] ?? 0 ) && ! empty( $prefill_tech_name ) && strtolower( $opt->option_value ) === strtolower( $prefill_tech_name ) ) {
						$selected = 'selected';
					}
					echo '<option value="' . esc_attr( $opt->option_value ) . '" ' . $selected . '>' . esc_html( $opt->option_value ) . '</option>';
				}
				echo '</select>';
				break;
		}
	}

	public function admin_menu() {
		add_submenu_page( 'supportcandy-plus', 'After Ticket Survey', 'After Ticket Survey', 'manage_options', 'scp-ats-survey', array( $this, 'display_main_survey_page' ) );
	}

	public function admin_notices() {
		if ( isset( $_GET['page'] ) && 'scp-ats-survey' === $_GET['page'] && isset( $_GET['message'] ) ) {
			$type = 'success';
			if ( strpos( $_GET['message'], 'fail' ) !== false || $_GET['message'] === 'error' ) {
				$type = 'error';
			}
			$messages = array(
				'added' => 'Question added successfully!',
				'updated' => 'Question updated successfully!',
				'deleted' => 'Question deleted successfully!',
				'submissions_deleted' => 'Selected submissions deleted!',
				'import_success' => 'All data and settings imported successfully!',
				'import_fail' => 'Import failed: Could not find data from the old plugin.',
				'import_success_settings_only' => 'Settings imported successfully, but no table data was found for the old plugin.',
				'import_success_data_only' => 'Data from tables imported successfully, but no settings were found for the old plugin.',
				'error' => 'An error occurred.',
			);
			$message_text = $messages[ $_GET['message'] ] ?? 'Action completed.';
			echo "<div class=\"notice notice-{$type} is-dismissible\"><p>{$message_text}</p></div>";
		}
	}

	public function display_main_survey_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
		?>
		<div class="wrap">
			<h1>After Ticket Survey</h1>
			<nav class="nav-tab-wrapper">
				<a href="?page=scp-ats-survey&tab=main" class="nav-tab <?php if($current_tab === 'main') echo 'nav-tab-active'; ?>">How to Use</a>
				<a href="?page=scp-ats-survey&tab=questions" class="nav-tab <?php if($current_tab === 'questions') echo 'nav-tab-active'; ?>">Manage Questions</a>
				<a href="?page=scp-ats-survey&tab=submissions" class="nav-tab <?php if($current_tab === 'submissions') echo 'nav-tab-active'; ?>">Manage Submissions</a>
				<a href="?page=scp-ats-survey&tab=results" class="nav-tab <?php if($current_tab === 'results') echo 'nav-tab-active'; ?>">View Results</a>
				<a href="?page=scp-ats-survey&tab=settings" class="nav-tab <?php if($current_tab === 'settings') echo 'nav-tab-active'; ?>">Settings</a>
			</nav>
			<div class="tab-content" style="margin-top: 20px;">
			<?php
				switch ( $current_tab ) {
					case 'questions': $this->render_questions_tab(); break;
					case 'submissions': $this->render_submissions_tab(); break;
					case 'results': $this->render_results_tab(); break;
					case 'settings': $this->render_settings_tab(); break;
					default: $this->render_main_tab(); break;
				}
			?>
			</div>
		</div>
		<?php
	}

	private function render_main_tab() {
		?>
		<div class="ats-how-to-use-container">
			<p>This plugin allows you to easily create, customize, and manage after-ticket surveys to gather valuable feedback from your users.</p>

			<h2>How to Use This Plugin:</h2>

			<div class="ats-admin-section">
				<h3>1. Display the Survey on a Page</h3>
				<p>To show the survey form on any page or post on your website, simply add the following shortcode to the content editor:</p>
				<pre><code>[after_ticket_survey]</code></pre>
				<p>Once you add this, the survey form will appear on that page for your users to fill out.</p>
			</div>

			<div class="ats-admin-section">
				<h3>2. Manage Your Survey Questions</h3>
				<p>You have full control over the questions in your survey. To add new questions, edit existing ones, or remove questions:</p>
				<ul>
					<li>Go to <strong>After Ticket Survey → Manage Questions</strong> in your WordPress admin sidebar.</li>
					<li>Here, you'll see a list of all your current survey questions.</li>
					<li>Use the "Add New Question" form to create new questions. You can choose from different types:
						<ul>
							<li><strong>Short Text:</strong> For brief answers like a ticket number or a single word.</li>
							<li><strong>Long Text:</strong> For detailed feedback or comments.</li>
							<li><strong>Rating (1-5):</strong> For questions requiring a numerical rating (e.g., satisfaction level).</li>
							<li><strong>Dropdown:</strong> For questions with predefined options, like a list of technicians.</li>
						</ul>
					</li>
					<li>For "Dropdown" questions, remember to enter your options separated by commas (e.g., "Option 1, Option 2").</li>
					<li>You can also <strong>Edit</strong> or <strong>Delete</strong> existing questions using the buttons next to each question in the table.</li>
				</ul>
			</div>

			<div class="ats-admin-section">
				<h3>3. View Survey Results</h3>
				<p>Once users start submitting surveys, you can view all the collected feedback:</p>
				<ul>
					<li>Go to <strong>After Ticket Survey → View Results</strong> in your WordPress admin sidebar.</li>
					<li>This page will display a table with all survey submissions, showing each user's answers to your questions.</li>
				</ul>
				<p><em>(Note: The "View Results" page is currently a basic display. Future updates may include advanced filtering and export options.)</em></p>
			</div>

			<div class="ats-admin-section">
				<h3>4. Configure Your Settings</h3>
				<p>The settings page allows you to customize how the plugin works to better fit your needs:</p>
				<ul>
					<li>Go to <strong>After Ticket Survey → Settings</strong> in your WordPress admin sidebar.</li>
					<li>Here, you can configure the following options:
						<ul>
							<li><strong>Survey Page Background Color:</strong> Change the background color of the survey page using an interactive color picker to match your site's theme.</li>
							<li><strong>Ticket Number Question:</strong> Tell the plugin exactly which question asks for the ticket number. This makes the link from the results page to your ticketing system reliable, even if you change the question's text.</li>
							<li><strong>Technician Question:</strong> Specify which "Dropdown" type question is used for technicians. This allows you to pre-fill the technician's name in the survey by adding it to the survey URL.</li>
							<li><strong>Ticket System Base URL:</strong> Set the base URL for your ticketing system. The plugin will append the ticket ID to this URL to create a direct link to the ticket from the "View Results" page.</li>
						</ul>
					</li>
				</ul>
			</div>

			<div class="ats-admin-section">
				<h3>5. Pre-filling Survey Data via URL</h3>
				<p>You can pre-fill the ticket number and technician fields by adding parameters to your survey URL. This is ideal for including in email notifications from your helpdesk system.</p>
				<p>The following parameters are supported:</p>
				<ul>
					<li><code>ticket_id</code>: This will populate the field you designated as the "Ticket Number Question".</li>
					<li><code>tech</code>: This will pre-select the value in the "Technician Question" dropdown. The value must match one of the options exactly (e.g., "Philip Edwards").</li>
				</ul>
				<h4>Constructing the URL:</h4>
				<p>If your survey URL does not contain a question mark (?), add the first parameter with a <code>?</code> and subsequent parameters with an ampersand (<code>&</code>).</p>
				<pre><code>https://yourwebsite.com/survey-page/?ticket_id=12345&tech=John%20Doe</code></pre>
				<h4>Example for Email Templates:</h4>
				<p>You can use placeholders from your helpdesk system to build the link dynamically. Here is an example of a call-to-action button for an email template:</p>
				<pre><code>&lt;a style="padding: 10px 20px;background-color: #820024;color: #ffffff;text-decoration: none;border-radius: 4px;font-weight: bold;font-family: Arial, sans-serif" href="[your_survey_url]?ticket_id=[ticket_id_placeholder]&amp;amp;tech=[agent_name_placeholder]"&gt; Satisfaction Survey &lt;/a&gt;</code></pre>
				<p><strong>Note:</strong> Replace the <code>[placeholder]</code> values with the actual placeholders provided by your helpdesk system. Be sure to use <code>&amp;amp;</code> for the second parameter in HTML links. For this functionality to work, you must first configure the "Ticket Number Question" and "Technician Question" on the <strong>After Ticket Survey → Settings</strong> page.</p>
			</div>

			<p><strong>Thank you for using the After Ticket Survey plugin!</strong></p>
		</div>
		<?php
	}

	private function render_questions_tab() {
		global $wpdb;
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['question_id'] ) ) {
			check_admin_referer( 'scp_ats_delete_q' );
			$wpdb->delete( $this->questions_table_name, array( 'id' => intval( $_GET['question_id'] ) ) );
			wp_redirect( admin_url( 'admin.php?page=scp-ats-survey&tab=questions&message=deleted' ) );
			exit;
		}
		$editing_question = null;
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['question_id'] ) ) {
			$editing_question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questions_table_name} WHERE id = %d", intval( $_GET['question_id'] ) ), ARRAY_A );
			if ( $editing_question && $editing_question['question_type'] === 'dropdown' ) {
				$options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d", $editing_question['id'] ) );
				$editing_question['options_str'] = implode( ', ', array_column( $options, 'option_value' ) );
			}
		}
		$questions = $wpdb->get_results( "SELECT * FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		?>
		<div class="ats-questions-container">

			<!-- Existing Questions Table -->
			<div class="ats-questions-list">
				<h2>Existing Questions</h2>
				<table class="wp-list-table widefat fixed striped ats-admin-table">
					<thead><tr><th class="manage-column">Order</th><th class="manage-column">Question Text</th><th class="manage-column">Type</th><th class="manage-column">Required</th><th class="manage-column">Options (for Dropdown)</th><th class="manage-column">Actions</th></tr></thead>
					<tbody>
					<?php if ( empty( $questions ) ) : ?>
						<tr><td colspan="6">No questions found.</td></tr>
					<?php else : ?>
						<?php foreach ( $questions as $q ) : ?>
						<tr>
							<td><?php echo esc_html( $q['sort_order'] ); ?></td><td><?php echo esc_html( $q['question_text'] ); ?></td><td><?php echo esc_html( str_replace('_', ' ', ucfirst( $q['question_type'] )) ); ?></td><td><?php echo $q['is_required'] ? 'Yes' : 'No'; ?></td>
							<td><?php if ( $q['question_type'] === 'dropdown' ) { $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $q['id'] ), ARRAY_A ); echo esc_html( implode(', ', array_column($options, 'option_value')) ); } else { echo 'N/A'; } ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=scp-ats-survey&tab=questions&action=edit&question_id=' . $q['id'] ) ); ?>" class="button button-secondary">Edit</a> <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scp-ats-survey&tab=questions&action=delete&question_id=' . $q['id'] ), 'scp_ats_delete_q' ) ); ?>" class="button button-secondary" onclick="return confirm('Are you sure?');">Delete</a></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Add/Edit Question Form -->
			<div class="ats-question-form">
				<h2><?php echo $editing_question ? 'Edit Question' : 'Add New Question'; ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ats-admin-form">
					<input type="hidden" name="action" value="scp_ats_manage_questions">
					<input type="hidden" name="ats_action" value="<?php echo $editing_question ? 'update' : 'add'; ?>">
					<?php if ( $editing_question ) : ?><input type="hidden" name="question_id" value="<?php echo esc_attr( $editing_question['id'] ); ?>"><?php endif; ?>
					<?php wp_nonce_field( 'scp_ats_manage_questions_nonce' ); ?>

					<div class="ats-form-group">
						<label for="question_text" class="ats-label">Question Text:</label>
						<input type="text" id="question_text" name="question_text" class="ats-input" value="<?php echo esc_attr( $editing_question['question_text'] ?? '' ); ?>" required>
					</div>

					<div class="ats-form-row">
						<div class="ats-form-group">
							<label for="question_type" class="ats-label">Question Type:</label>
							<select id="question_type" name="question_type" class="ats-input" required onchange="toggleDropdownOptions(this)">
								<option value="short_text" <?php selected( $editing_question['question_type'] ?? '', 'short_text' ); ?>>Short Text</option>
								<option value="long_text" <?php selected( $editing_question['question_type'] ?? '', 'long_text' ); ?>>Long Text</option>
								<option value="rating" <?php selected( $editing_question['question_type'] ?? '', 'rating' ); ?>>Rating (1-5)</option>
								<option value="dropdown" <?php selected( $editing_question['question_type'] ?? '', 'dropdown' ); ?>>Dropdown</option>
							</select>
						</div>
						<div class="ats-form-group">
							<label for="ats_sort_order" class="ats-label">Sort Order:</label>
							<input type="number" id="ats_sort_order" name="ats_sort_order" class="ats-input ats-sort-order-input" value="<?php echo esc_attr( $editing_question['sort_order'] ?? count($questions) ); ?>" min="0">
						</div>
						<div class="ats-form-group ats-form-group-checkbox">
							<label for="ats_is_required" class="ats-label-inline">Required?</label>
							<input type="checkbox" id="ats_is_required" name="ats_is_required" value="1" <?php checked( $editing_question['is_required'] ?? 1 ); ?>>
						</div>
					</div>

					<div class="ats-form-group" id="ats_dropdown_options_group" style="display: none;">
						<label for="ats_dropdown_options" class="ats-label">Dropdown Options (comma-separated):</label>
						<textarea id="ats_dropdown_options" name="ats_dropdown_options" rows="3" class="ats-input" placeholder="e.g., Option 1, Option 2"><?php echo esc_textarea( $editing_question['options_str'] ?? '' ); ?></textarea>
					</div>

					<div class="ats-form-actions">
						<button type="submit" class="button button-primary button-large ats-submit-button-admin"><?php echo $editing_question ? 'Update Question' : 'Add Question'; ?></button>
						<?php if ( $editing_question ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=scp-ats-survey&tab=questions' ) ); ?>" class="button button-secondary ats-cancel-button-admin">Cancel Edit</a><?php endif; ?>
					</div>
				</form>
			</div>
		</div>
		<script>function toggleDropdownOptions(selectElement) { document.getElementById('ats_dropdown_options_group').style.display = selectElement.value === 'dropdown' ? 'block' : 'none'; } document.addEventListener('DOMContentLoaded', function() { toggleDropdownOptions(document.getElementById('ats_question_type')); });</script>
		<?php
	}

	private function render_submissions_tab() {
		global $wpdb;
		$submissions = $wpdb->get_results( "SELECT id, submission_date FROM {$this->survey_submissions_table_name} ORDER BY submission_date DESC", ARRAY_A );
		?>
		<h2>Manage Survey Submissions</h2>
		<p>Select one or more submissions below and click "Delete" to permanently remove them.</p>
		<?php if ( $submissions ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="scp_ats_manage_submissions">
				<?php wp_nonce_field( 'scp_ats_manage_submissions_nonce' ); ?>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th class="check-column"><input type="checkbox" id="scp-ats-select-all"></th><th>Submission ID</th><th>Date Submitted</th></tr></thead>
					<tbody>
					<?php foreach ( $submissions as $sub ) : ?>
						<tr><th scope="row" class="check-column"><input type="checkbox" name="selected_submissions[]" value="<?php echo esc_attr( $sub['id'] ); ?>"></th><td><?php echo esc_html( $sub['id'] ); ?></td><td><?php echo esc_html( $sub['submission_date'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table><br>
				<button type="submit" class="button button-primary">Delete Selected Submissions</button>
			</form>
			<script>jQuery(document).ready(function($){ $('#scp-ats-select-all').on('change', function(){ $('input[name="selected_submissions[]"]').prop('checked', $(this).prop('checked')); }); });</script>
		<?php else : ?>
			<p>No survey submissions to manage yet.</p>
		<?php endif; ?>
		<?php
	}

	private function render_results_tab() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$ticket_question_id = ! empty( $options['ats_ticket_question_id'] ) ? (int) $options['ats_ticket_question_id'] : 0;
		$ticket_url_base = ! empty( $options['ats_ticket_url_base'] ) ? $options['ats_ticket_url_base'] : '';
		$questions = $wpdb->get_results( "SELECT id, question_text, question_type FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		$submissions = $wpdb->get_results( "SELECT s.*, u.display_name FROM {$this->survey_submissions_table_name} s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID ORDER BY submission_date DESC", ARRAY_A );
		?>
		<h2>View Survey Results</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>ID</th><th>Date</th><th>User</th><?php foreach ( $questions as $q ) echo '<th>' . esc_html( $q['question_text'] ) . '</th>'; ?></tr></thead>
			<tbody>
			<?php foreach ( $submissions as $sub ) : ?>
				<tr>
					<td><?php echo $sub['id']; ?></td><td><?php echo $sub['submission_date']; ?></td><td><?php echo esc_html( $sub['display_name'] ?? 'Guest' ); ?></td>
					<?php
					$answers = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_value FROM {$this->survey_answers_table_name} WHERE submission_id = %d", $sub['id'] ), OBJECT_K );
					foreach ( $questions as $q ) {
						$answer = $answers[ $q['id'] ]->answer_value ?? '';
						if ( $q['id'] == $ticket_question_id && $ticket_url_base && is_numeric( $answer ) ) { echo '<td><a href="' . esc_url( $ticket_url_base . $answer ) . '" target="_blank">' . esc_html( $answer ) . '</a></td>';
						} else { echo '<td>' . esc_html( $answer ) . '</td>'; }
					}
					?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_settings_tab() {
		?>
		<h2>After Ticket Survey Settings</h2>
		<form method="post" action="options.php">
			<?php
				settings_fields( 'scp_settings' );
				echo '<input type="hidden" name="scp_settings[page_slug]" value="scp-ats-survey">';
				do_settings_sections( 'scp-ats-survey' );
				submit_button();
			?>
		</form>
		<hr style="margin: 20px 0;">
		<h2>Import from Old Plugin</h2>
		<p>If you were using the standalone "WP - After Ticket Survey" plugin, you can import your questions, submissions, and settings here.</p>
		<div style="border: 2px solid #ffb900; padding: 10px; margin-bottom: 15px;">
			<p><strong>Warning:</strong> This is a one-way process and will overwrite any existing survey data in this plugin. It cannot be undone. Please be sure you want to proceed.</p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Are you sure you want to import data from the old plugin? This will overwrite existing survey data and cannot be undone.');">
			<input type="hidden" name="action" value="scp_ats_import_settings">
			<?php wp_nonce_field( 'scp_ats_import_nonce', '_scp_ats_import_nonce' ); ?>
			<?php submit_button( 'Import Data from Old Plugin', 'secondary' ); ?>
		</form>
		<?php
	}

	public function handle_manage_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'scp_ats_manage_submissions_nonce' );
		if ( ! empty( $_POST['selected_submissions'] ) ) {
			global $wpdb;
			$ids = implode( ',', array_map( 'absint', $_POST['selected_submissions'] ) );
			$wpdb->query( "DELETE FROM {$this->survey_submissions_table_name} WHERE id IN ($ids)" );
			$wpdb->query( "DELETE FROM {$this->survey_answers_table_name} WHERE submission_id IN ($ids)" );
		}
		wp_redirect( admin_url( 'admin.php?page=scp-ats-survey&tab=submissions&message=submissions_deleted' ) );
		exit;
	}

	public function handle_manage_questions() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'scp_ats_manage_questions_nonce' );
		global $wpdb;
		$action = $_POST['ats_action'] ?? '';
		$question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
		$data = array( 'question_text' => sanitize_text_field( $_POST['question_text'] ), 'question_type' => sanitize_text_field( $_POST['question_type'] ), 'is_required' => isset( $_POST['is_required'] ) ? 1 : 0, 'sort_order' => intval( $_POST['sort_order'] ) );
		if ( $action === 'add' ) {
			$wpdb->insert( $this->questions_table_name, $data );
			$question_id = $wpdb->insert_id;
			$message = 'added';
		} elseif ( $action === 'update' && $question_id ) {
			$wpdb->update( $this->questions_table_name, $data, array( 'id' => $question_id ) );
			$message = 'updated';
		}
		if ( $question_id && $data['question_type'] === 'dropdown' ) {
			$wpdb->delete( $this->dropdown_options_table_name, array( 'question_id' => $question_id ) );
			$options = array_map( 'trim', explode( ',', $_POST['dropdown_options'] ) );
			foreach ( $options as $opt ) { if ( ! empty( $opt ) ) { $wpdb->insert( $this->dropdown_options_table_name, array( 'question_id' => $question_id, 'option_value' => $opt ) ); } }
		}
		wp_redirect( admin_url( 'admin.php?page=scp-ats-survey&tab=questions&message=' . ( $message ?? 'error' ) ) );
		exit;
	}

	public function register_settings() {
		add_settings_section( 'scp_ats_settings_section', '', null, 'scp-ats-survey' );
		add_settings_field( 'ats_background_color', 'Survey Page Background Color', array( $this, 'render_color_picker' ), 'scp-ats-survey', 'scp_ats_settings_section' );
		add_settings_field( 'ats_ticket_question_id', 'Ticket Number Question', array( $this, 'render_question_dropdown' ), 'scp-ats-survey', 'scp_ats_settings_section' );
		add_settings_field( 'ats_technician_question_id', 'Technician Question', array( $this, 'render_technician_question_dropdown' ), 'scp-ats-survey', 'scp_ats_settings_section' );
		add_settings_field( 'ats_ticket_url_base', 'Ticket System Base URL', array( $this, 'render_text_field' ), 'scp-ats-survey', 'scp_ats_settings_section' );
	}

	public function render_color_picker() {
		$options = get_option( 'scp_settings' );
		echo '<input type="text" name="scp_settings[ats_background_color]" value="' . esc_attr( $options['ats_background_color'] ?? '' ) . '" class="ats-color-picker" />';
	}

	public function render_question_dropdown() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$selected = $options['ats_ticket_question_id'] ?? '';
		$questions = $wpdb->get_results( "SELECT id, question_text FROM {$this->questions_table_name} ORDER BY sort_order ASC" );
		echo '<select name="scp_settings[ats_ticket_question_id]"><option value="">-- Select --</option>';
		foreach ( $questions as $q ) { echo '<option value="' . $q->id . '"' . selected( $selected, $q->id, false ) . '>' . esc_html( $q->question_text ) . '</option>'; }
		echo '</select>';
	}

	public function render_technician_question_dropdown() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$selected = $options['ats_technician_question_id'] ?? '';
		$questions = $wpdb->get_results( "SELECT id, question_text FROM {$this->questions_table_name} WHERE question_type = 'dropdown' ORDER BY sort_order ASC" );
		echo '<select name="scp_settings[ats_technician_question_id]"><option value="">-- Select --</option>';
		foreach ( $questions as $q ) { echo '<option value="' . $q->id . '"' . selected( $selected, $q->id, false ) . '>' . esc_html( $q->question_text ) . '</option>'; }
		echo '</select>';
	}

	public function render_text_field() {
		$options = get_option( 'scp_settings' );
		echo '<input type="text" name="scp_settings[ats_ticket_url_base]" value="' . esc_attr( $options['ats_ticket_url_base'] ?? '' ) . '" class="regular-text">';
	}

	public function handle_import_settings() {
		if ( ! isset( $_POST['_scp_ats_import_nonce'] ) || ! wp_verify_nonce( $_POST['_scp_ats_import_nonce'], 'scp_ats_import_nonce' ) ) {
			wp_die( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		global $wpdb;
		$old_table_prefix              = $wpdb->prefix . 'ats_';
		$old_questions_table           = $old_table_prefix . 'questions';
		$old_dropdown_options_table    = $old_table_prefix . 'dropdown_options';
		$old_survey_submissions_table  = $old_table_prefix . 'survey_submissions';
		$old_survey_answers_table      = $old_table_prefix . 'survey_answers';

		$old_tables_exist  = $wpdb->get_var( "SHOW TABLES LIKE '{$old_questions_table}'" ) === $old_questions_table;
		$old_options_exist = get_option( 'ats_survey_options' ) !== false;

		if ( ! $old_tables_exist && ! $old_options_exist ) {
			wp_redirect( admin_url( 'admin.php?page=scp-ats-survey&tab=settings&message=import_fail' ) );
			exit;
		}

		if ( $old_tables_exist ) {
			$wpdb->query( "TRUNCATE TABLE {$this->questions_table_name}" );
			$wpdb->query( "TRUNCATE TABLE {$this->dropdown_options_table_name}" );
			$wpdb->query( "TRUNCATE TABLE {$this->survey_submissions_table_name}" );
			$wpdb->query( "TRUNCATE TABLE {$this->survey_answers_table_name}" );

			$wpdb->query( "INSERT INTO {$this->questions_table_name} SELECT * FROM {$old_questions_table}" );
			$wpdb->query( "INSERT INTO {$this->dropdown_options_table_name} SELECT * FROM {$old_dropdown_options_table}" );
			$wpdb->query( "INSERT INTO {$this->survey_submissions_table_name} (id, user_id, submission_date) SELECT id, user_id, submission_date FROM {$old_survey_submissions_table}" );
			$wpdb->query( "INSERT INTO {$this->survey_answers_table_name} SELECT * FROM {$old_survey_answers_table}" );
		}

		if ( $old_options_exist ) {
			$old_options   = get_option( 'ats_survey_options' );
			$new_options   = get_option( 'scp_settings', array() );
			$option_mapping = array(
				'background_color'     => 'ats_background_color',
				'ticket_question_id'     => 'ats_ticket_question_id',
				'technician_question_id' => 'ats_technician_question_id',
				'ticket_url'             => 'ats_ticket_url_base',
			);
			foreach ( $option_mapping as $old_key => $new_key ) {
				if ( isset( $old_options[ $old_key ] ) ) {
					$new_options[ $new_key ] = $old_options[ $old_key ];
				}
			}
			$new_options['page_slug'] = 'scp-ats-survey';
			update_option( 'scp_settings', $new_options );
		}

		$message = 'import_success';
		if ( $old_tables_exist && ! $old_options_exist ) {
			$message = 'import_success_data_only';
		} elseif ( ! $old_tables_exist && $old_options_exist ) {
			$message = 'import_success_settings_only';
		}

		wp_redirect( admin_url( 'admin.php?page=scp-ats-survey&tab=settings&message=' . $message ) );
		exit;
	}
}
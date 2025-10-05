<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class SCP_After_Ticket_Survey {

	private static $instance = null;

	private $db_version = '1.0.0';
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
			ticket_id bigint(20) DEFAULT 0 NOT NULL,
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

		$default_questions = array(
			array(
				'text' => 'What is your ticket number?', 'type' => 'short_text', 'required' => 1, 'initial_sort_order' => 0,
			),
			array(
				'text' => 'Who was your technician for this ticket?', 'type' => 'dropdown', 'options' => array('Technician A', 'Technician B'), 'required' => 1, 'initial_sort_order' => 1,
			),
			array(
				'text' => 'Overall, how would you rate the handling of your issue?', 'type' => 'rating', 'required' => 1, 'initial_sort_order' => 2,
			),
			array(
				'text' => 'Any other comments?', 'type' => 'long_text', 'required' => 0, 'initial_sort_order' => 3,
			),
		);

		if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questions_table_name}" ) == 0 ) {
			foreach ( $default_questions as $q_data ) {
				$wpdb->insert(
					$this->questions_table_name,
					array(
						'question_text' => $q_data['text'],
						'question_type' => $q_data['type'],
						'sort_order'    => $q_data['initial_sort_order'],
						'is_required'   => $q_data['required'],
					)
				);
				$question_id = $wpdb->insert_id;

				if ( $q_data['type'] === 'dropdown' && ! empty( $q_data['options'] ) ) {
					foreach ( $q_data['options'] as $option_value ) {
						$wpdb->insert(
							$this->dropdown_options_table_name,
							array(
								'question_id'  => $question_id,
								'option_value' => $option_value,
							)
						);
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
		$allowed_hooks = array(
			'supportcandy-plus_page_scp-ats-questions',
			'supportcandy-plus_page_scp-ats-submissions',
			'supportcandy-plus_page_scp-ats-results',
			'supportcandy-plus_page_scp-ats-settings',
		);
		if ( in_array( $hook_suffix, $allowed_hooks, true ) ) {
			wp_enqueue_style( 'scp-ats-admin-styles', SCP_PLUGIN_URL . 'assets/admin/css/scp-ats-admin-styles.css', array(), $this->db_version );
			if ( 'supportcandy-plus_page_scp-ats-settings' === $hook_suffix ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'scp-ats-color-picker', SCP_PLUGIN_URL . 'assets/admin/js/scp-ats-color-picker.js', array( 'wp-color-picker' ), $this->db_version, true );
			}
		}
	}

	public function survey_shortcode() {
		ob_start();
		global $wpdb;

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

		$wpdb->insert(
			$this->survey_submissions_table_name,
			array(
				'user_id'         => $user_id,
				'ticket_id'       => isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0,
				'submission_date' => current_time( 'mysql' ),
			)
		);
		$submission_id = $wpdb->insert_id;

		if ( $submission_id ) {
			foreach ( $questions as $question ) {
				$input_name = 'scp_ats_q_' . $question['id'];
				if ( isset( $_POST[ $input_name ] ) ) {
					$wpdb->insert(
						$this->survey_answers_table_name,
						array(
							'submission_id' => $submission_id,
							'question_id'   => $question['id'],
							'answer_value'  => sanitize_textarea_field( $_POST[ $input_name ] ),
						)
					);
				}
			}
			echo '<div class="scp-ats-success-message">Thank you for your feedback!</div>';
		} else {
			echo '<div class="scp-ats-error-message">An error occurred. Please try again.</div>';
		}
	}

	private function display_survey_form() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$ticket_question_id = ! empty( $options['ats_ticket_question_id'] ) ? (int) $options['ats_ticket_question_id'] : 0;
		$technician_question_id = ! empty( $options['ats_technician_question_id'] ) ? (int) $options['ats_technician_question_id'] : 0;
		$prefill_ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : '';
		$prefill_tech_name = isset( $_GET['tech'] ) ? sanitize_text_field( $_GET['tech'] ) : '';

		$questions = $wpdb->get_results( "SELECT * FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		if ( empty( $questions ) ) {
			echo 'No survey questions defined.';
			return;
		}
		?>
		<div class="scp-ats-survey-container">
			<form method="post" class="scp-ats-form">
				<?php wp_nonce_field( 'scp_ats_survey_form_nonce', 'scp_ats_survey_nonce' ); ?>
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $prefill_ticket_id ); ?>">
				<?php foreach ( $questions as $q_num => $question ) : ?>
					<div class="scp-ats-form-group">
						<label for="scp_ats_q_<?php echo $question['id']; ?>">
							<?php echo ( $q_num + 1 ) . '. ' . esc_html( $question['question_text'] ); ?>
							<?php if ( $question['is_required'] ) : ?>
								<span class="scp-ats-required-label">*</span>
							<?php endif; ?>
						</label>
						<?php $this->render_question_field( $question, $options, $prefill_ticket_id, $prefill_tech_name ); ?>
					</div>
				<?php endforeach; ?>
				<button type="submit" name="scp_ats_submit_survey" class="scp-ats-submit-button">Submit Survey</button>
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
				echo "<input type=\"text\" name=\"{$input_name}\" value=\"{$input_value}\" {$required_attr}>";
				break;
			case 'long_text':
				echo "<textarea name=\"{$input_name}\" rows=\"4\" {$required_attr}></textarea>";
				break;
			case 'rating':
				echo '<div class="scp-ats-rating-options">';
				for ( $i = 1; $i <= 5; $i++ ) {
					echo "<label><input type=\"radio\" name=\"{$input_name}\" value=\"{$i}\" {$required_attr}> {$i}</label>";
				}
				echo '</div>';
				break;
			case 'dropdown':
				$dd_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question['id'] ) );
				echo "<select name=\"{$input_name}\" {$required_attr}>";
				echo '<option value="">-- Select --</option>';
				foreach ( $dd_options as $opt ) {
					$selected = '';
					if ( $question['id'] == ( $options['ats_technician_question_id'] ?? 0 ) && ! empty( $prefill_tech_name ) ) {
						if ( strtolower( $opt->option_value ) === strtolower( $prefill_tech_name ) ) {
							$selected = 'selected';
						}
					}
					echo '<option value="' . esc_attr( $opt->option_value ) . '" ' . $selected . '>' . esc_html( $opt->option_value ) . '</option>';
				}
				echo '</select>';
				break;
		}
	}

	public function admin_menu() {
		add_submenu_page(
			'supportcandy-plus',
			'After Ticket Survey',
			'After Ticket Survey',
			'manage_options',
			'scp-ats-questions',
			array( $this, 'display_questions_page' )
		);
		add_submenu_page(
			'scp-ats-questions',
			'Submissions',
			'Submissions',
			'manage_options',
			'scp-ats-submissions',
			array( $this, 'display_submissions_page' )
		);
		add_submenu_page(
			'scp-ats-questions',
			'Results',
			'Results',
			'manage_options',
			'scp-ats-results',
			array( $this, 'display_results_page' )
		);
		add_submenu_page(
			'scp-ats-questions',
			'Settings',
			'Settings',
			'manage_options',
			'scp-ats-settings',
			array( $this, 'display_settings_page' )
		);
	}

	public function admin_notices() {
		$page = $_GET['page'] ?? '';
		if ( strpos( $page, 'scp-ats-' ) !== false && isset( $_GET['message'] ) ) {
			$type = $_GET['message'] === 'error' ? 'error' : 'success';
			$messages = array(
				'added'               => 'Question added successfully!',
				'updated'             => 'Question updated successfully!',
				'deleted'             => 'Question deleted successfully!',
				'submissions_deleted' => 'Selected submissions deleted!',
				'error'               => 'An error occurred.',
			);
			$message_text = $messages[ $_GET['message'] ] ?? 'Action completed.';
			echo "<div class=\"notice notice-{$type} is-dismissible\"><p>{$message_text}</p></div>";
		}
	}

	public function display_questions_page() {
		global $wpdb;

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['question_id'] ) ) {
			check_admin_referer( 'scp_ats_delete_q' );
			$question_id = intval( $_GET['question_id'] );
			$wpdb->delete( $this->questions_table_name, array( 'id' => $question_id ) );
			$wpdb->delete( $this->dropdown_options_table_name, array( 'question_id' => $question_id ) );
			$wpdb->delete( $this->survey_answers_table_name, array( 'question_id' => $question_id ) );
			wp_redirect( admin_url( 'admin.php?page=scp-ats-questions&message=deleted' ) );
			exit;
		}

		$editing_question = null;
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['question_id'] ) ) {
			$question_id = intval( $_GET['question_id'] );
			$editing_question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questions_table_name} WHERE id = %d", $question_id ), ARRAY_A );
			if ( $editing_question && $editing_question['question_type'] === 'dropdown' ) {
				$options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d", $question_id ) );
				$editing_question['options_str'] = implode( ', ', array_column( $options, 'option_value' ) );
			}
		}
		$questions = $wpdb->get_results( "SELECT * FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		?>
		<div class="wrap">
			<h1>Manage Survey Questions</h1>
			<div id="col-container">
				<div id="col-left">
					<div class="col-wrap">
						<h2><?php echo $editing_question ? 'Edit Question' : 'Add New Question'; ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="scp_ats_manage_questions">
							<input type="hidden" name="ats_action" value="<?php echo $editing_question ? 'update' : 'add'; ?>">
							<?php if ( $editing_question ) : ?>
								<input type="hidden" name="question_id" value="<?php echo esc_attr( $editing_question['id'] ); ?>">
							<?php endif; ?>
							<?php wp_nonce_field( 'scp_ats_manage_questions_nonce' ); ?>

							<div class="form-field">
								<label for="question_text">Question Text</label>
								<input name="question_text" id="question_text" type="text" value="<?php echo esc_attr( $editing_question['question_text'] ?? '' ); ?>" required>
							</div>
							<div class="form-field">
								<label for="question_type">Question Type</label>
								<select name="question_type" id="question_type" required>
									<option value="short_text" <?php selected( $editing_question['question_type'] ?? '', 'short_text' ); ?>>Short Text</option>
									<option value="long_text" <?php selected( $editing_question['question_type'] ?? '', 'long_text' ); ?>>Long Text</option>
									<option value="rating" <?php selected( $editing_question['question_type'] ?? '', 'rating' ); ?>>Rating (1-5)</option>
									<option value="dropdown" <?php selected( $editing_question['question_type'] ?? '', 'dropdown' ); ?>>Dropdown</option>
								</select>
							</div>
							<div class="form-field" id="dropdown-options-wrapper" style="<?php echo ( $editing_question['question_type'] ?? '' ) === 'dropdown' ? '' : 'display:none;'; ?>">
								<label for="dropdown_options">Dropdown Options (comma-separated)</label>
								<textarea name="dropdown_options" id="dropdown_options" rows="2"><?php echo esc_textarea( $editing_question['options_str'] ?? '' ); ?></textarea>
							</div>
							<div class="form-field">
								<label><input name="is_required" type="checkbox" value="1" <?php checked( $editing_question['is_required'] ?? 1 ); ?>> Required</label>
							</div>
							<div class="form-field">
								<label for="sort_order">Sort Order</label>
								<input name="sort_order" id="sort_order" type="number" value="<?php echo esc_attr( $editing_question['sort_order'] ?? count( $questions ) ); ?>" min="0">
							</div>

							<?php submit_button( $editing_question ? 'Update Question' : 'Add Question' ); ?>
							<?php if ( $editing_question ) : ?>
								<a href="?page=scp-ats-questions" class="button">Cancel Edit</a>
							<?php endif; ?>
						</form>
					</div>
				</div>
				<div id="col-right">
					<div class="col-wrap">
						<h2>Existing Questions</h2>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr><th>Order</th><th>Question</th><th>Type</th><th>Actions</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $questions as $q ) : ?>
									<tr>
										<td><?php echo esc_html( $q['sort_order'] ); ?></td>
										<td><?php echo esc_html( $q['question_text'] ); ?></td>
										<td><?php echo esc_html( $q['question_type'] ); ?></td>
										<td>
											<a href="?page=scp-ats-questions&action=edit&question_id=<?php echo $q['id']; ?>">Edit</a> |
											<a href="<?php echo wp_nonce_url( '?page=scp-ats-questions&action=delete&question_id=' . $q['id'], 'scp_ats_delete_q' ); ?>" onclick="return confirm('Are you sure?')">Delete</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<script>
				jQuery(document).ready(function($){
					$('#question_type').on('change', function(){
						if ( $(this).val() === 'dropdown' ) {
							$('#dropdown-options-wrapper').show();
						} else {
							$('#dropdown-options-wrapper').hide();
						}
					}).trigger('change');
				});
			</script>
		</div>
		<?php
	}

	public function display_submissions_page() {
		global $wpdb;
		$submissions = $wpdb->get_results( "SELECT id, submission_date FROM {$this->survey_submissions_table_name} ORDER BY submission_date DESC", ARRAY_A );
		?>
		<div class="wrap">
			<h1>Manage Survey Submissions</h1>
			<p>Select one or more submissions below and click "Delete" to permanently remove them.</p>

			<?php if ( $submissions ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'scp_ats_delete_submissions_nonce' ); ?>
					<input type="hidden" name="action" value="scp_ats_manage_submissions">
					<input type="hidden" name="ats_action" value="delete_selected">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox" id="scp-ats-select-all"></th>
								<th>Submission ID</th>
								<th>Date Submitted</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $submissions as $submission ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="selected_submissions[]" value="<?php echo esc_attr( $submission['id'] ); ?>">
									</th>
									<td><?php echo esc_html( $submission['id'] ); ?></td>
									<td><?php echo esc_html( $submission['submission_date'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<br>
					<button type="submit" class="button button-primary" onclick="return confirm('Are you sure?');">
						Delete Selected Submissions
					</button>
				</form>
				<script>
					jQuery(document).ready(function($){
						$('#scp-ats-select-all').on('change', function(){
							$('input[name="selected_submissions[]"]').prop('checked', $(this).prop('checked'));
						});
					});
				</script>
			<?php else : ?>
				<p>No survey submissions to manage yet.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function display_results_page() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$ticket_question_id = ! empty( $options['ats_ticket_question_id'] ) ? (int) $options['ats_ticket_question_id'] : 0;
		$ticket_url_base = ! empty( $options['ats_ticket_url_base'] ) ? $options['ats_ticket_url_base'] : '';

		$questions = $wpdb->get_results( "SELECT id, question_text, question_type FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		$submissions = $wpdb->get_results( "SELECT s.*, u.display_name FROM {$this->survey_submissions_table_name} s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID ORDER BY submission_date DESC", ARRAY_A );
		?>
		<div class="wrap">
			<h1>Survey Results</h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th><th>Date</th><th>User</th>
						<?php foreach ( $questions as $q ) echo '<th>' . esc_html( $q['question_text'] ) . '</th>'; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $submissions as $sub ) : ?>
						<tr>
							<td><?php echo $sub['id']; ?></td>
							<td><?php echo $sub['submission_date']; ?></td>
							<td><?php echo esc_html( $sub['display_name'] ?? 'Guest' ); ?></td>
							<?php
							$answers = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_value FROM {$this->survey_answers_table_name} WHERE submission_id = %d", $sub['id'] ), OBJECT_K );
							foreach ( $questions as $q ) {
								$answer = $answers[ $q['id'] ]->answer_value ?? '';
								if ( $q['id'] == $ticket_question_id && $ticket_url_base && is_numeric( $answer ) ) {
									echo '<td><a href="' . esc_url( $ticket_url_base . $answer ) . '" target="_blank">' . esc_html( $answer ) . '</a></td>';
								} else {
									echo '<td>' . esc_html( $answer ) . '</td>';
								}
							}
							?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function display_settings_page() {
		?>
		<div class="wrap">
			<h1>After Ticket Survey Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'scp_settings' );
				do_settings_sections( 'scp-ats-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function handle_manage_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'scp_ats_delete_submissions_nonce' );

		global $wpdb;
		$action = $_POST['ats_action'] ?? '';
		$message = 'error';

		if ( $action === 'delete_selected' && ! empty( $_POST['selected_submissions'] ) ) {
			$submission_ids = array_map( 'intval', $_POST['selected_submissions'] );
			$ids_placeholder = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->survey_answers_table_name} WHERE submission_id IN ( $ids_placeholder )", $submission_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->survey_submissions_table_name} WHERE id IN ( $ids_placeholder )", $submission_ids ) );

			$message = 'submissions_deleted';
		}

		wp_redirect( admin_url( 'admin.php?page=scp-ats-submissions&message=' . $message ) );
		exit;
	}

	public function handle_manage_questions() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'scp_ats_manage_questions_nonce' );

		global $wpdb;
		$action = $_POST['ats_action'] ?? '';
		$question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
		$message = 'error';

		$data = array(
			'question_text' => sanitize_text_field( $_POST['question_text'] ),
			'question_type' => sanitize_text_field( $_POST['question_type'] ),
			'is_required'   => isset( $_POST['is_required'] ) ? 1 : 0,
			'sort_order'    => intval( $_POST['sort_order'] ),
		);

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
			foreach ( $options as $opt ) {
				if ( ! empty( $opt ) ) {
					$wpdb->insert( $this->dropdown_options_table_name, array( 'question_id' => $question_id, 'option_value' => $opt ) );
				}
			}
		}

		wp_redirect( admin_url( 'admin.php?page=scp-ats-questions&message=' . $message ) );
		exit;
	}

	public function register_settings() {
		register_setting( 'scp_settings', 'scp_settings' );

		add_settings_section( 'scp_ats_settings_section', 'After Ticket Survey Settings', null, 'scp-ats-settings' );

		add_settings_field( 'ats_background_color', 'Survey Page Background Color', array( $this, 'render_color_picker' ), 'scp-ats-settings', 'scp_ats_settings_section' );
		add_settings_field( 'ats_ticket_question_id', 'Ticket Number Question', array( $this, 'render_question_dropdown' ), 'scp-ats-settings', 'scp_ats_settings_section' );
		add_settings_field( 'ats_technician_question_id', 'Technician Question', array( $this, 'render_technician_question_dropdown' ), 'scp-ats-settings', 'scp_ats_settings_section' );
		add_settings_field( 'ats_ticket_url_base', 'Ticket System Base URL', array( $this, 'render_text_field' ), 'scp-ats-settings', 'scp_ats_settings_section' );
	}

	public function render_color_picker() {
		$options = get_option( 'scp_settings' );
		$color = $options['ats_background_color'] ?? '#f0f0f0';
		echo '<input type="text" name="scp_settings[ats_background_color]" value="' . esc_attr( $color ) . '" class="scp-ats-color-picker" />';
	}

	public function render_question_dropdown() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$selected = $options['ats_ticket_question_id'] ?? '';
		$questions = $wpdb->get_results( "SELECT id, question_text FROM {$this->questions_table_name} ORDER BY sort_order ASC" );
		echo '<select name="scp_settings[ats_ticket_question_id]"><option value="">-- Select --</option>';
		foreach ( $questions as $q ) {
			echo '<option value="' . $q->id . '"' . selected( $selected, $q->id, false ) . '>' . esc_html( $q->question_text ) . '</option>';
		}
		echo '</select>';
	}

	public function render_technician_question_dropdown() {
		global $wpdb;
		$options = get_option( 'scp_settings' );
		$selected = $options['ats_technician_question_id'] ?? '';
		$questions = $wpdb->get_results( "SELECT id, question_text FROM {$this->questions_table_name} WHERE question_type = 'dropdown' ORDER BY sort_order ASC" );
		echo '<select name="scp_settings[ats_technician_question_id]"><option value="">-- Select --</option>';
		foreach ( $questions as $q ) {
			echo '<option value="' . $q->id . '"' . selected( $selected, $q->id, false ) . '>' . esc_html( $q->question_text ) . '</option>';
		}
		echo '</select>';
	}

	public function render_text_field() {
		$options = get_option( 'scp_settings' );
		$value = $options['ats_ticket_url_base'] ?? '';
		echo '<input type="text" name="scp_settings[ats_ticket_url_base]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="e.g., https://support.example.com/tickets/">';
		echo '<p class="description">The ticket ID will be appended to this URL.</p>';
	}
}
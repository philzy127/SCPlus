/**
 * SupportCandy Plus Frontend Script (v2.0.0 - Advanced)
 *
 * This script is fully configurable via the admin settings page.
 * It uses a single MutationObserver for efficiency and is driven by the
 * `scp_settings` object localized from PHP.
 *
 * @package SupportCandy_Plus
 */

(function ($) {
	'use strict';

	// `scp_settings` is localized from PHP in the main plugin file.
	const settings = window.scp_settings || {};
	const features = settings.features || {};

	/**
	 * Main initializer. Sets up a MutationObserver to watch for DOM changes,
	 * ensuring features are applied even on AJAX-loaded content.
	 */
	function init() {
		const observer = new MutationObserver(function (mutations) {
			// A simple check is enough, as run_features() is idempotent.
			if (mutations.length) {
				run_features();
			}
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		// Initial run on page load.
		run_features();
	}

	/**
	 * Central dispatcher. Checks if features are enabled in the settings
	 * and calls the corresponding function.
	 */
	function run_features() {
		if (features.hover_card?.enabled) {
			feature_ticket_hover_card();
		}

		// All column visibility logic is now consolidated.
		const emptyColsConfig = features.hide_empty_columns || {};
		const conditionalConfig = features.conditional_hiding || {};
		if (emptyColsConfig.enabled || emptyColsConfig.hide_priority || conditionalConfig.enabled) {
			feature_manage_column_visibility();
		}

		if (features.ticket_type_hiding?.enabled) {
			feature_hide_ticket_types_for_non_agents();
		}

		if (features.after_hours_notice?.enabled) {
			feature_after_hours_notice();
		}

		if (features.hide_reply_close?.enabled) {
			feature_hide_reply_close_button();
		}

		if (features.date_formatting?.enabled) {
			feature_apply_custom_date_formats();
		}
	}

	/**
	 * Feature: Unified Column Visibility Manager.
	 * Consolidates all column hiding features to prevent conflicts. It processes rules for
	 * empty columns, the priority column, and conditional view-based rules in a specific order.
	 */
	function feature_manage_column_visibility() {
		// 1. Get configs for all relevant features
		const emptyColsConfig = features.hide_empty_columns || {};
		const conditionalConfig = features.conditional_hiding || {};

		// 2. Get table elements
		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		const tbody = table?.querySelector('tbody');
		if (!table || !tbody || !tbody.rows.length) return;

		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const rows = Array.from(tbody.querySelectorAll('tr'));
		if (!headers.length || !rows.length) return;

		// 3. Initialize visibility plan and data matrix
		const visibilityPlan = {}; // index -> 'show' or 'hide'
		headers.forEach((_, i) => {
			visibilityPlan[i] = 'show'; // Default to show
		});
		const matrix = rows.map(row => Array.from(row.children).map(td => td.textContent.trim()));

		// 4. Process "Hide Empty" and "Hide Priority" logic
		if (emptyColsConfig.enabled || emptyColsConfig.hide_priority) {
			headers.forEach((th, i) => {
				const headerText = th.textContent.trim().toLowerCase();

				// Priority column logic
				if (emptyColsConfig.hide_priority && headerText === 'priority') {
					const hasNonLow = matrix.some(row => row[i] && row[i].toLowerCase() !== 'low');
					if (!hasNonLow) {
						visibilityPlan[i] = 'hide';
					}
				}
				// Empty column logic (run for other columns)
				else if (emptyColsConfig.enabled) {
					const allEmpty = matrix.every(row => !row[i] || row[i] === '');
					if (allEmpty) {
						visibilityPlan[i] = 'hide';
					}
				}
			});
		}

		// 5. Process "Conditional Hiding" rules, which can OVERRIDE the previous plan
		if (conditionalConfig.enabled && conditionalConfig.rules && conditionalConfig.rules.length) {
			const filter = document.querySelector('#wpsc-input-filter');
			const currentViewId = filter ? filter.value || '0' : '0';
			const pageView = currentViewId.replace('default-', '');
			const columnKeyMap = conditionalConfig.columns || {}; // slug -> Name

			const headerIndexMap = {};
			headers.forEach((th, index) => {
				headerIndexMap[th.textContent.trim().toLowerCase()] = index;
			});

			// Helper to check if a rule is active for a given view ID.
			const isRuleActiveForView = (rule, viewId) => {
				const ruleView = String(rule.view);
				return (rule.condition === 'in_view' && viewId === ruleView) ||
					   (rule.condition === 'not_in_view' && viewId !== ruleView);
			};

			// First Pass: Apply the implications of "Show Only" rules from other views.
			// If a column is "Show Only" in another view, it should be hidden here by default.
			conditionalConfig.rules.forEach(rule => {
				if (rule.action === 'show_only' && !isRuleActiveForView(rule, pageView)) {
					const columnSlug = rule.columns;
					const columnLabel = columnKeyMap[columnSlug];
					if (columnLabel) {
						const columnIndex = headerIndexMap[columnLabel.toLowerCase()];
						if (columnIndex !== undefined) {
							visibilityPlan[columnIndex] = 'hide';
						}
					}
				}
			});

			// Second Pass: Apply all rules that are explicitly for the current view.
			// This will override the defaults set above.
			conditionalConfig.rules.forEach(rule => {
				if (isRuleActiveForView(rule, pageView)) {
					const columnSlug = rule.columns;
					const columnLabel = columnKeyMap[columnSlug];
					if (columnLabel) {
						const columnIndex = headerIndexMap[columnLabel.toLowerCase()];
						if (columnIndex !== undefined) {
							if (rule.action === 'show' || rule.action === 'show_only') {
								visibilityPlan[columnIndex] = 'show';
							} else if (rule.action === 'hide') {
								visibilityPlan[columnIndex] = 'hide';
							}
						}
					}
				}
			});
		}

		// 6. Apply the final visibility plan
		headers.forEach((th, i) => {
			const action = visibilityPlan[i] || 'show';
			th.style.display = action === 'hide' ? 'none' : '';
		});
		rows.forEach(row => {
			Array.from(row.children).forEach((td, i) => {
				const action = visibilityPlan[i] || 'show';
				td.style.display = action === 'hide' ? 'none' : '';
			});
		});
	}

	/**
	 * Feature: Ticket Hover Card.
	 * Configurable delay and uses a dynamic nonce.
	 */
	function feature_ticket_hover_card() {
		let floatingCard = document.getElementById('floatingTicketCard');
		if (!floatingCard) {
			floatingCard = document.createElement('div');
			floatingCard.id = 'floatingTicketCard';
			Object.assign(floatingCard.style, {
				position: 'absolute', zIndex: '9999', background: '#fff',
				border: '1px solid #ccc', padding: '10px',
				boxShadow: '0 4px 12px rgba(0, 0, 0, 0.2)', maxWidth: '400px',
				display: 'none'
			});

			// Create a dedicated content container
			const contentContainer = document.createElement('div');
			contentContainer.className = 'scp-card-content';

			// Create, style, and append the close button
			const closeButton = document.createElement('span');
			closeButton.innerHTML = '&times;'; // "X" character
			Object.assign(closeButton.style, {
				position: 'absolute',
				top: '3px',
				right: '5px',
				cursor: 'pointer',
				fontSize: '20px',
				color: '#333',
				background: '#f1f1f1',
				borderRadius: '50%',
				width: '24px',
				height: '24px',
				lineHeight: '24px',
				textAlign: 'center',
				fontWeight: 'bold',
				clear: 'both'
			});
			closeButton.addEventListener('click', () => {
				floatingCard.style.display = 'none';
			});

			floatingCard.appendChild(closeButton);
			floatingCard.appendChild(contentContainer);
			document.body.appendChild(floatingCard);
		}

		// Get the content container for later use.
		const contentContainer = floatingCard.querySelector('.scp-card-content');

		const cache = {};
		const delay = features.hover_card.delay || 1000;

		async function fetchTicketDetails(ticketId) {
			if (cache[ticketId]) return cache[ticketId];
			try {
				const response = await fetch(settings.ajax_url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: new URLSearchParams({
						action: 'wpsc_get_individual_ticket',
						nonce: settings.nonce,
						ticket_id: ticketId
					})
				});
				if (!response.ok) return '<div>Error fetching ticket info.</div>';
				const html = await response.text();
				const doc = new DOMParser().parseFromString(html, 'text/html');
				const content = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields')?.outerHTML || '<div>No details found.</div>';
				return (cache[ticketId] = content);
			} catch (error) {
				return '<div>Error fetching ticket info.</div>';
			}
		}

		// Hide card when clicking anywhere on the page, unless inside the card.
		document.addEventListener('click', (e) => {
			if (floatingCard && !floatingCard.contains(e.target)) {
				floatingCard.style.display = 'none';
			}
		});

		document.querySelectorAll('tr.wpsc_tl_tr:not(._contextAttached)').forEach(row => {
			row.classList.add('_contextAttached');
			// Clean up old class if present, for seamless update.
			if (row.classList.contains('_hoverAttached')) {
				row.classList.remove('_hoverAttached');
			}

			// We are replacing the hover/click logic with a right-click.
			// The original click listener on the row is for navigation, so we leave it.
			// The new logic is to show the card on right-click.

			row.addEventListener('contextmenu', async (e) => {
				e.preventDefault(); // Prevent browser's context menu.

				const ticketId = row.getAttribute('onclick')?.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/)?.[1];
				if (ticketId) {
					// Position and show loading indicator immediately.
					contentContainer.innerHTML = 'Loading...';
					floatingCard.style.top = `${e.pageY + 15}px`;
					floatingCard.style.left = `${e.pageX + 15}px`;
					floatingCard.style.display = 'block';

					// Fetch and display the actual content.
					contentContainer.innerHTML = await fetchTicketDetails(ticketId);
				}
			});
		});
	}

	/**
	 * Feature: Hide Ticket Types from Non-Agents.
	 * Uses a dynamic field ID and a configurable list of types to hide.
	 */
	function feature_hide_ticket_types_for_non_agents() {
		const isAgent = document.querySelector('.wpsc-menu-list.agent-profile, #menu-item-8128');
		const fieldId = features.ticket_type_hiding.field_id;
		const typesToHide = features.ticket_type_hiding.types_to_hide;

		if (!isAgent && fieldId && typesToHide.length) {
			const select = document.querySelector(`select[name="cust_${fieldId}"]`);
			if (select && $.fn.select2) {
				const $select = $(select);
				let changesMade = false;
				$select.find('option').each(function () {
					const optionText = $(this).text().trim();
					if (typesToHide.includes(optionText)) {
						$(this).remove();
						changesMade = true;
					}
				});
				if (changesMade) {
					$select.trigger('change.select2');
				}
			}
		}
	}


	/**
	 * Feature: After Hours Notice.
	 * Displays a configurable message on the ticket creation form if it's outside business hours.
	 */
	function feature_after_hours_notice() {
		const config = features.after_hours_notice;
		if (!config?.enabled || !config.message) {
			return;
		}

		const now = new Date();
		const currentHour = now.getHours();
		const currentDay = now.getDay(); // Sunday = 0, Saturday = 6
		const isWeekend = currentDay === 0 || currentDay === 6;

		// Format current date as MM-DD-YYYY for comparison
		const year = now.getFullYear();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');
		const currentDate = `${month}-${day}-${year}`;
		const isHoliday = config.holidays && config.holidays.includes(currentDate);

		let isAfterHours = false;

		// Check for holiday, weekend, or time-based conditions.
		if (isHoliday) {
			isAfterHours = true;
		} else if (config.include_weekends && isWeekend) {
			isAfterHours = true;
		} else {
			isAfterHours = currentHour >= config.start_hour || currentHour < config.end_hour;
		}

		if (isAfterHours) {
			const ticketForm = document.querySelector('.wpsc-create-ticket');

			// Check if the form exists and if we haven't already added the message.
			if (ticketForm && !ticketForm.dataset.afterHoursNoticeAdded) {
				const message = document.createElement('div');
				message.innerHTML = config.message;

				// Apply styles for the notice.
				Object.assign(message.style, {
					backgroundColor: '#fff3cd',
					border: '1px solid #ffeeba',
					color: '#856404',
					padding: '10px',
					margin: '15px 0',
					borderRadius: '4px',
					fontSize: '16px',
				});

				ticketForm.prepend(message);
				ticketForm.dataset.afterHoursNoticeAdded = 'true'; // Mark as added.
			}
		}
	}


	/**
	 * Feature: Hide "Reply & Close" button for non-agents.
	 *
	 * Selects the button based on its unique `onclick` attribute content
	 * to avoid relying on classes that might be used elsewhere.
	 */
	function feature_hide_reply_close_button() {
		const isAgent = document.querySelector('.wpsc-menu-list.agent-profile, #menu-item-8128');

		// This feature should only apply to non-agents.
		if (!isAgent) {
			// Find the button specifically containing the 'wpsc_it_reply_and_close' function call.
			const replyCloseButton = document.querySelector('button.wpsc-it-editor-submit[onclick*="wpsc_it_reply_and_close"]');

			if (replyCloseButton) {
				replyCloseButton.style.display = 'none';
			}
		}
	}


	// Wait for the DOM to be ready before initializing.
	$(document).ready(init);

	/**
	 * Feature: Apply Custom Date Formats.
	 * Replaces the "time ago" text with the formatted date from the title attribute.
	 */
	function feature_apply_custom_date_formats() {
		const config = features.date_formatting;
		if (!config?.enabled || !config.rules?.length) {
			return;
		}

		console.log('SupportCandy Plus Debug: Applying custom date formats. Rules:', config.rules);

		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		if (!table) return;

		// Get header indexes to identify date columns
		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const dateColumnIndexes = [];
		const ruleColumns = config.rules.map(rule => rule.column);

		headers.forEach((th, index) => {
			const headerText = th.textContent.trim();
			// A bit of a weak check, but should cover 'Date Created', 'Last Reply', etc.
			if (headerText.toLowerCase().includes('date') || headerText.toLowerCase().includes('reply')) {
				dateColumnIndexes.push(index);
			}
		});

		if (!dateColumnIndexes.length) return;

		const rows = Array.from(table.querySelectorAll('tbody tr.wpsc_tl_tr'));
		rows.forEach(row => {
			dateColumnIndexes.forEach(index => {
				const cell = row.children[index];
				const span = cell?.querySelector('span[title]');
				if (span && span.title && !span.dataset.scpDateFormatApplied) {
					span.textContent = span.title;
					span.dataset.scpDateFormatApplied = 'true';
				}
			});
		});
	}

})(jQuery);
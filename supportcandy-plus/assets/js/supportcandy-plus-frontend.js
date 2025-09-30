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
		// Run general cleanup first.
		if (features.hide_empty_columns?.enabled) {
			feature_hide_empty_columns();
		}
		if (features.ticket_type_hiding?.enabled) {
			feature_hide_ticket_types_for_non_agents();
		}
		// Run the advanced conditional hiding last so it can override.
		if (features.conditional_hiding?.enabled) {
			feature_conditional_column_hiding();
		}
	}

	/**
	 * Feature: Hide Empty Columns.
	 * Hides any column that is completely empty.
	 */
	function feature_hide_empty_columns() {
		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		if (!table?.querySelector('tbody')?.rows.length) return;

		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const rows = Array.from(table.querySelectorAll('tbody tr'));
		const matrix = rows.map(row => Array.from(row.children).map(td => td.textContent.trim()));
		const columnsToHide = new Set();

		headers.forEach((th, i) => {
			if (matrix.every(row => !row[i] || row[i] === '')) {
				columnsToHide.add(i);
			}
		});

		columnsToHide.forEach(i => {
			if (headers[i]) headers[i].style.display = 'none';
			rows.forEach(row => {
				if (row.children[i]) row.children[i].style.display = 'none';
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
	 * Feature: Advanced Conditional Column Hiding Rule Engine.
	 * Processes a set of rules to show or hide columns based on the current view.
	 * This version uses case-insensitive text matching for robustness.
	 */
	function feature_conditional_column_hiding() {
		console.log('[SCP] Running Conditional Column Hiding...');

		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		const filter = document.querySelector('#wpsc-input-filter');
		if (!table || !filter) {
			console.log('[SCP] Exiting: Table or filter not found.');
			return;
		}

		const config = features.conditional_hiding;
		const rules = config.rules;
		const columnMap = config.columns;

		if (!rules || !rules.length || !columnMap) {
			console.log('[SCP] Exiting: No rules or column map defined.');
			return;
		}
		console.log('[SCP] Settings:', { rules, columnMap });

		const currentViewId = filter.value || '0';
		console.log(`[SCP] Current View ID: ${currentViewId}`);

		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const columnVisibility = {};

		// 1. Initialize.
		for (const key in columnMap) {
			columnVisibility[key] = 'show';
		}

		// 2. Process rules.
		rules.forEach(rule => {
			console.log(`[SCP] Evaluating Rule:`, rule);

			// Normalize view IDs to handle cases where the page uses 'default-3' and the rule uses '3'.
			const pageView = currentViewId.replace('default-', '');
			const ruleView = String(rule.view);

			const ruleIsActive = (rule.condition === 'in_view' && pageView === ruleView) ||
								 (rule.condition === 'not_in_view' && pageView !== ruleView);

			if (ruleIsActive) {
				const columnKey = rule.columns;
				if (columnKey && columnVisibility.hasOwnProperty(columnKey)) {
					columnVisibility[columnKey] = rule.action;
					console.log(`[SCP] Rule ACTIVE: Action=${rule.action}, Column=${columnKey}, View=${rule.view}`);
				}
			}
		});
		console.log('[SCP] Final Column Visibility Plan:', columnVisibility);

		// 3. Create header map.
		const headerIndexMap = {};
		headers.forEach((th, index) => {
			const headerText = th.textContent.trim().toLowerCase();
			headerIndexMap[headerText] = index;
		});
		console.log('[SCP] Header Index Map:', headerIndexMap);

		// 4. Apply visibility.
		console.log('[SCP] Applying visibility states...');
		for (const columnKey in columnVisibility) {
			const columnLabel = columnMap[columnKey];
			if (!columnLabel) continue;

			const columnIndex = headerIndexMap[columnLabel.toLowerCase()];
			const shouldHide = columnVisibility[columnKey] === 'hide';

			console.log(`[SCP] Processing Column: Key='${columnKey}', Label='${columnLabel}', Index=${columnIndex}, Hide=${shouldHide}`);

			if (columnIndex !== undefined) {
				if (headers[columnIndex]) {
					headers[columnIndex].style.display = shouldHide ? 'none' : '';
				}
				table.querySelectorAll('tbody tr').forEach(row => {
					if (row.cells[columnIndex]) {
						row.cells[columnIndex].style.display = shouldHide ? 'none' : '';
					}
				});
				console.log(`[SCP]   -> ACTION: Set display to '${shouldHide ? 'none' : ''}' for column index ${columnIndex}`);
			} else {
				console.log(`[SCP]   -> SKIPPED: Column label '${columnLabel}' not found in table headers.`);
			}
		}
		console.log('[SCP] Finished Conditional Column Hiding.');
	}

	// Wait for the DOM to be ready before initializing.
	$(document).ready(init);

})(jQuery);
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
			document.body.appendChild(floatingCard);
		}

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

		document.querySelectorAll('tr.wpsc_tl_tr:not(._hoverAttached)').forEach(row => {
			row.classList.add('_hoverAttached');
			let hoverTimeout;
			row.addEventListener('mouseenter', () => {
				clearTimeout(hoverTimeout);
				hoverTimeout = setTimeout(async () => {
					const ticketId = row.getAttribute('onclick')?.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/)?.[1];
					if (ticketId) {
						floatingCard.innerHTML = 'Loading...';
						floatingCard.style.display = 'block';
						floatingCard.innerHTML = await fetchTicketDetails(ticketId);
						const rect = row.getBoundingClientRect();
						floatingCard.style.top = `${rect.bottom + window.scrollY + 5}px`;
						floatingCard.style.left = `${rect.left + window.scrollX}px`;
					}
				}, delay);
			});
			row.addEventListener('mouseleave', () => {
				clearTimeout(hoverTimeout);
				floatingCard.style.display = 'none';
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
		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		const filter = document.querySelector('#wpsc-input-filter');
		if (!table || !filter) return;

		const config = features.conditional_hiding;
		const rules = config.rules;
		const columnMap = config.columns; // Map of 'key' => 'Label'

		if (!rules || !rules.length || !columnMap) return;

		const currentViewId = filter.value || '0';
		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const columnVisibility = {}; // Final state for each column *key*

		// 1. Initialize visibility state for all known columns to 'show'.
		for (const key in columnMap) {
			columnVisibility[key] = 'show';
		}

		// 2. Process rules to determine the final visibility state for each column key.
		rules.forEach(rule => {
			const ruleIsActive = (rule.condition === 'in_view' && rule.view === currentViewId) ||
								 (rule.condition === 'not_in_view' && rule.view !== currentViewId);

			if (ruleIsActive) {
				const columnKey = rule.columns;
				if (columnKey && columnVisibility.hasOwnProperty(columnKey)) {
					columnVisibility[columnKey] = rule.action; // 'show' or 'hide'
				}
			}
		});

		// 3. Create a map of lowercase header text to header index for robust matching.
		const headerIndexMap = {};
		headers.forEach((th, index) => {
			headerIndexMap[th.textContent.trim().toLowerCase()] = index;
		});

		// 4. Apply the final state to the table columns by matching labels case-insensitively.
		for (const columnKey in columnVisibility) {
			const columnLabel = columnMap[columnKey];
			const columnIndex = headerIndexMap[columnLabel.toLowerCase()];

			if (columnIndex !== undefined) {
				const shouldHide = columnVisibility[columnKey] === 'hide';

				if (headers[columnIndex]) {
					headers[columnIndex].style.display = shouldHide ? 'none' : '';
				}

				table.querySelectorAll('tbody tr').forEach(row => {
					if (row.cells[columnIndex]) {
						row.cells[columnIndex].style.display = shouldHide ? 'none' : '';
					}
				});
			}
		}
	}

	// Wait for the DOM to be ready before initializing.
	$(document).ready(init);

})(jQuery);
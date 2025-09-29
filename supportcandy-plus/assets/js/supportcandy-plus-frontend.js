/**
 * SupportCandy Plus Frontend Script
 *
 * This script contains all the frontend functionality for the SupportCandy Plus plugin.
 * It is loaded on pages where SupportCandy tickets are displayed.
 *
 * @package SupportCandy_Plus
 */

(function ($) {
	'use strict';

	// Will be populated by wp_localize_script
	const scp_settings = window.scp_settings || {};

	/**
	 * Main function to initialize all features.
	 */
	function init() {
		// A single observer for all DOM mutations.
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.addedNodes.length) {
					run_features();
				}
			});
		});

		// Start observing the document body.
		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		// Initial run.
		run_features();
	}

	/**
	 * Run all enabled features.
	 */
	function run_features() {
		if (scp_settings.enable_column_hider) {
			feature_dynamic_column_hider();
		}
		if (scp_settings.enable_ticket_type_hiding) {
			feature_hide_ticket_types_for_non_agents();
		}
		if (scp_settings.enable_hover_card) {
			feature_ticket_hover_card();
		}
        feature_conditional_column_hiding(); // This one has its own internal checks
	}

	/**
	 * Feature: Dynamically hides columns.
	 */
	function feature_dynamic_column_hider() {
		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		const tbody = table?.querySelector('tbody');
		if (!table || !tbody || !tbody.rows.length) return;

		const headers = table.querySelectorAll('thead tr th');
		const rows = Array.from(tbody.querySelectorAll('tr'));

		if (!headers.length || !rows.length) return;

		const matrix = rows.map(row => Array.from(row.children).map(td => td.textContent.trim()));
		const columnsToHide = new Set();

		headers.forEach((th, i) => {
			const headerText = th.textContent.trim().toLowerCase();

			if (headerText === 'priority') {
				const hasNonLow = matrix.some(row => row[i] && row[i].toLowerCase() !== 'low');
				if (!hasNonLow) {
					columnsToHide.add(i);
				}
				return;
			}

			const allEmpty = matrix.every(row => !row[i]);
			if (allEmpty) {
				columnsToHide.add(i);
			}
		});

		// Reset: Show all first
		headers.forEach((th, i) => th.style.display = '');
		rows.forEach(row => {
			Array.from(row.children).forEach(td => td.style.display = '');
		});

		// Apply hiding
		columnsToHide.forEach(i => {
			if (headers[i]) headers[i].style.display = 'none';
			rows.forEach(row => {
				if (row.children[i]) row.children[i].style.display = 'none';
			});
		});
	}

	/**
	 * Feature: Hide ticket types from non-agents.
	 */
	function feature_hide_ticket_types_for_non_agents() {
		const isAgent = !!document.querySelector('.wpsc-menu-list.agent-profile') || !!document.querySelector('#menu-item-8128');

		if (!isAgent) {
			const select = document.querySelector('select[name="cust_39"]');
			if (select && window.jQuery && window.jQuery.fn.select2) {
				const $select = window.jQuery(select);
				$select.find('option').each(function () {
					const optionText = $(this).text().trim();
					if (optionText === 'Network Access Request' || optionText === 'Video Archive Request') {
						$(this).remove();
					}
				});
				$select.trigger('change.select2');
			}
		}
	}

	/**
	 * Feature: Ticket Hover Card.
	 */
	function feature_ticket_hover_card() {
		let floatingCard = document.getElementById('floatingTicketCard');
		if (!floatingCard) {
			floatingCard = document.createElement('div');
			floatingCard.id = 'floatingTicketCard';
			floatingCard.style.position = 'absolute';
			floatingCard.style.zIndex = '9999';
			floatingCard.style.background = '#fff';
			floatingCard.style.border = '1px solid #ccc';
			floatingCard.style.padding = '10px';
			floatingCard.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
			floatingCard.style.maxWidth = '400px';
			floatingCard.style.display = 'none';
			document.body.appendChild(floatingCard);
		}

		const cache = {};

		async function fetchTicketDetails(ticketId) {
			if (cache[ticketId]) {
				return cache[ticketId];
			}

			try {
				const response = await fetch(scp_settings.ajax_url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: new URLSearchParams({
						action: 'wpsc_get_individual_ticket',
						nonce: scp_settings.nonce,
						ticket_id: ticketId
					})
				});

				if (!response.ok) {
					return '<div>Error fetching ticket info.</div>';
				}

				const html = await response.text();
				const parser = new DOMParser();
				const doc = parser.parseFromString(html, 'text/html');
				const widget = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields');
				const content = widget ? widget.outerHTML : '<div>No details found.</div>';
				cache[ticketId] = content;
				return content;

			} catch (error) {
				return '<div>Error fetching ticket info.</div>';
			}
		}

		document.querySelectorAll('tr.wpsc_tl_tr').forEach(row => {
			if (row._hoverAttached) return;
			row._hoverAttached = true;

			let hoverTimeout;
			row.addEventListener('mouseenter', () => {
				clearTimeout(hoverTimeout);
				hoverTimeout = setTimeout(async () => {
					const onclickText = row.getAttribute('onclick');
					const match = onclickText && onclickText.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/);
					const ticketId = match && match[1];
					if (ticketId) {
						floatingCard.innerHTML = 'Loading...';
						floatingCard.style.display = 'block';
						const content = await fetchTicketDetails(ticketId);
						floatingCard.innerHTML = content;
						const rect = row.getBoundingClientRect();
						floatingCard.style.top = `${rect.bottom + window.scrollY + 5}px`;
						floatingCard.style.left = `${rect.left + window.scrollX}px`;
					}
				}, 1000);
			});

			row.addEventListener('mouseleave', () => {
				clearTimeout(hoverTimeout);
				floatingCard.style.display = 'none';
			});
		});
	}

    /**
     * Feature: Conditionally hide/show columns based on filter.
     */
    function feature_conditional_column_hiding() {
        const table = document.querySelector('.wpsc-ticket-list-tbl');
        const filter = document.querySelector('#wpsc-input-filter');

        if (!table || !filter) {
            return;
        }

        const selectedText = filter.options[filter.selectedIndex].text.trim();
        const viewFilterName = scp_settings.view_filter_name || '';
        const columnsToHideStr = scp_settings.columns_to_hide_in_view || '';
        const columnsToShowStr = scp_settings.columns_to_show_in_view || '';

        const columnsToHide = columnsToHideStr.split(',').map(s => s.trim()).filter(Boolean);
        const columnsToShow = columnsToShowStr.split(',').map(s => s.trim()).filter(Boolean);

        const allRows = Array.from(table.querySelectorAll('tr'));
        const headerRow = allRows[0];
        if (!headerRow) {
            return;
        }

        const headers = Array.from(headerRow.querySelectorAll('th'));

        // First, reset all columns that this feature might touch
        const allManagedColumns = [...columnsToHide, ...columnsToShow];
        const allManagedIndices = allManagedColumns.map(headerName =>
            headers.findIndex(h => h.innerText.trim() === headerName)
        ).filter(index => index !== -1);

        allRows.forEach(row => {
            allManagedIndices.forEach(index => {
                if (row.cells[index]) {
                    row.cells[index].style.display = '';
                }
            });
        });

        // Now, apply the specific logic
        if (selectedText === viewFilterName) {
            // HIDE columns that should NOT be in this view
            const hideIndices = columnsToHide.map(headerName =>
                headers.findIndex(h => h.innerText.trim() === headerName)
            ).filter(index => index !== -1);

            allRows.forEach(row => {
                hideIndices.forEach(index => {
                    if (row.cells[index]) {
                        row.cells[index].style.display = 'none';
                    }
                });
            });
        } else {
            // HIDE columns that should ONLY be in the special view
            const showIndices = columnsToShow.map(headerName =>
                headers.findIndex(h => h.innerText.trim() === headerName)
            ).filter(index => index !== -1);

            allRows.forEach(row => {
                showIndices.forEach(index => {
                    if (row.cells[index]) {
                        row.cells[index].style.display = 'none';
                    }
                });
            });
        }
    }

	// Wait for the DOM to be ready before initializing.
	$(document).ready(function () {
		init();
	});

})(jQuery);
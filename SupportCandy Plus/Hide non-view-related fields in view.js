/**
 * @fileoverview This script dynamically shows or hides specific columns
 * in a table based on a filter's selected value. It handles initial page load,
 * AJAX refreshes, and manual filter changes.
 *
 * Version: 6.2
 */

// --- USER VARIABLES ---
// Modify these variables to customize the script's behavior.

// The name of the filter option that should display the target columns.
const hideTargetFilterValue = 'Network Access Requests';

// The exact names of the table columns you want to hide or show.
const hideColumnsToToggle = ['Name'];
// --- END OF USER VARIABLES ---



// --- INTERNAL SCRIPT CONFIGURATION ---
// These variables are for the script's internal logic. Avoid changing them.
const hideTableClass = 'wpsc-ticket-list-tbl';
const hideFilterId = 'wpsc-input-filter';
const hidePollingInterval = 50; // Milliseconds between checks
let hideIsFilterListenerAttached = false;
let hideLastTableElement = null;
let hideLastFilterElement = null;
// --- END OF INTERNAL SCRIPT CONFIGURATION ---

/**
 * Toggles the visibility of the target columns based on the selected filter.
 */
function hideToggleColumns() {
    const table = document.querySelector(`.${hideTableClass}`);
    const filter = document.querySelector(`#${hideFilterId}`);

    if (!table || !filter) {
        // console.log('TOGGLE COLUMNS: Table or filter not found.');
        return;
    }

    const selectedText = filter.options[filter.selectedIndex].text.trim();
    // Check if the selected filter value matches the target.
    const shouldHide = selectedText === hideTargetFilterValue;

    if (shouldHide) {
        // If the target filter is selected, we hide the columns.
        const allRows = Array.from(table.querySelectorAll('tr'));
        const headerRow = allRows[0];
        if (!headerRow) {
            // console.log('TOGGLE COLUMNS: Header row not found.');
            return;
        }

        const headers = Array.from(headerRow.querySelectorAll('th'));
        const columnIndices = hideColumnsToToggle.map(headerName =>
            headers.findIndex(h => h.innerText.trim() === headerName)
        ).filter(index => index !== -1);

        allRows.forEach(row => {
            columnIndices.forEach(index => {
                if (row.cells[index]) {
                    row.cells[index].style.display = 'none';
                }
            });
        });

        // console.log('TOGGLE COLUMNS: Columns visibility updated. Hiding: true');
    } else {
        // If the filter is anything else, we ensure the columns are visible.
        const allRows = Array.from(table.querySelectorAll('tr'));
        const headerRow = allRows[0];
        if (!headerRow) {
            return;
        }

        const headers = Array.from(headerRow.querySelectorAll('th'));
        const columnIndices = hideColumnsToToggle.map(headerName =>
            headers.findIndex(h => h.innerText.trim() === headerName)
        ).filter(index => index !== -1);

        allRows.forEach(row => {
            columnIndices.forEach(index => {
                if (row.cells[index]) {
                    row.cells[index].style.display = '';
                }
            });
        });
        // console.log('TOGGLE COLUMNS: Columns visibility updated. Hiding: false');
    }
}

/**
 * A robust polling function to detect elements on initial load and after
 * AJAX refreshes.
 */
function hideCheckForElements() {
    const filter = document.querySelector(`#${hideFilterId}`);
    const table = document.querySelector(`.${hideTableClass}`);

    // If filter is found and listener is not attached, attach it
    if (filter) {
        if (!hideIsFilterListenerAttached) {
            // console.log('CHECK: Filter found. Attaching event listener.');
            filter.addEventListener('change', () => {
                // console.log('EVENT: Filter change detected.');
                hideToggleColumns();
            });
            hideIsFilterListenerAttached = true;
        }
    } else {
        // If the filter is not found, it might have been replaced. Reset the listener flag.
        hideIsFilterListenerAttached = false;
    }

    // If a new table is detected, apply the initial toggle
    if (table && table !== hideLastTableElement) {
        // console.log('CHECK: New table detected. Applying initial toggle.');
        hideLastTableElement = table;
        // Make sure we have a filter to check against before toggling
        if (filter) {
             hideToggleColumns();
        }
    }

    // If the filter element is replaced, reset the flag to re-attach the listener
    if (filter && filter !== hideLastFilterElement) {
        hideLastFilterElement = filter;
        hideIsFilterListenerAttached = false;
    }

    setTimeout(hideCheckForElements, hidePollingInterval);
}

hideCheckForElements();

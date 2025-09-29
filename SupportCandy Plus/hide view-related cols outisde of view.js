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
const targetFilterValue = 'Network Access Requests';

// The exact names of the table columns you want to hide or show.
const columnsToToggle = ['Anticipated Start Date', 'Onboarding Date', "Date to be Completed By / Effective Date"];
// --- END OF USER VARIABLES ---



// --- INTERNAL SCRIPT CONFIGURATION ---
// These variables are for the script's internal logic. Avoid changing them.
const tableClass = 'wpsc-ticket-list-tbl';
const filterId = 'wpsc-input-filter';
const pollingInterval = 50; // Milliseconds between checks
let isFilterListenerAttached = false;
let lastTableElement = null;
let lastFilterElement = null;
// --- END OF INTERNAL SCRIPT CONFIGURATION ---

/**
 * Toggles the visibility of the target columns based on the selected filter.
 */
function toggleColumns() {
    const table = document.querySelector(`.${tableClass}`);
    const filter = document.querySelector(`#${filterId}`);
    
    if (!table || !filter) {
        // console.log('TOGGLE COLUMNS: Table or filter not found.');
        return;
    }

    const selectedText = filter.options[filter.selectedIndex].text.trim();
    const shouldShow = selectedText === targetFilterValue;

    if (!shouldShow) {
        const allRows = Array.from(table.querySelectorAll('tr'));
        const headerRow = allRows[0];
        if (!headerRow) {
            // console.log('TOGGLE COLUMNS: Header row not found.');
            return;
        }
        
        const headers = Array.from(headerRow.querySelectorAll('th'));
        const columnIndices = columnsToToggle.map(headerName =>
            headers.findIndex(h => h.innerText.trim() === headerName)
        ).filter(index => index !== -1);

        allRows.forEach(row => {
            columnIndices.forEach(index => {
                if (row.cells[index]) {
                    row.cells[index].style.display = 'none';
                }
            });
        });

        // console.log('TOGGLE COLUMNS: Columns visibility updated. Showing: false');
    } else {
        // console.log('TOGGLE COLUMNS: Selected filter is the target. No action taken.');
    }
}

/**
 * A robust polling function to detect elements on initial load and after
 * AJAX refreshes.
 */
function checkForElements() {
    const filter = document.querySelector(`#${filterId}`);
    const table = document.querySelector(`.${tableClass}`);

    // If filter is found and listener is not attached, attach it
    if (filter) {
        if (!isFilterListenerAttached) {
            // console.log('CHECK: Filter found. Attaching event listener.');
            filter.addEventListener('change', () => {
                // console.log('EVENT: Filter change detected.');
                toggleColumns();
            });
            isFilterListenerAttached = true;
        }
    } else {
        // If the filter is not found, it might have been replaced. Reset the listener flag.
        isFilterListenerAttached = false;
    }

    // If a new table is detected, apply the initial toggle
    if (table && table !== lastTableElement) {
        // console.log('CHECK: New table detected. Applying initial toggle.');
        lastTableElement = table;
        // Make sure we have a filter to check against before toggling
        if (filter) {
             toggleColumns();
        }
    }

    // If the filter element is replaced, reset the flag to re-attach the listener
    if (filter && filter !== lastFilterElement) {
        lastFilterElement = filter;
        isFilterListenerAttached = false;
    }
    
    setTimeout(checkForElements, pollingInterval);
}

checkForElements();

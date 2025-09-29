<?php
/*
Plugin Name: CHP - SupportCandy Column Hider
Description: Dynamically hides the "Priority" column if all ticket priorities are 'Low', and hides any columns that have no content in any row.
Version: 1.1
Author: Philip (CHP)
*/

add_action('wp_footer', function () {
    ?>
    <script>
    (function () {
        const observer = new MutationObserver(() => {
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
        });

        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
});

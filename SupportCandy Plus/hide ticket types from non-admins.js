/*
if (document.querySelector('.wpsc-menu-list.agent-profile')) {
    console.log('This user is an agent!');
    // Run agent-only logic here
} else {
    console.log('This user is a customer.');
    // Run customer-only logic here
}
*/

document.addEventListener('DOMContentLoaded', function () {
    const isAgent = !!document.querySelector('.wpsc-menu-list.agent-profile') || !!document.querySelector('#menu-item-8128');

    if (!isAgent) {
        const interval = setInterval(function () {
            const select = document.querySelector('select[name="cust_39"]');

            if (select && window.jQuery && window.jQuery.fn.select2) {
                clearInterval(interval);

                const $ = window.jQuery;

                // Remove the "Network Access Request" and "Video Access Request" options
                $(select).find('option').each(function () {
                    const optionText = $(this).text().trim();
                    if (optionText === 'Network Access Request' || optionText === 'Video Archive Request') {
                        $(this).remove();
                    }
                });

                $(select).trigger('change.select2');
            }
        }, 200);
    }
});


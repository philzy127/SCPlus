jQuery(document).ready(function ($) {
    'use strict';

    function initializeSelect2(element) {
        // Initializes Select2 on a jQuery element.
        element.select2({
            width: '100%' // Ensure it fits the container
        });
    }

    // Initialize Select2 on existing dropdowns on page load
    $('.scp-rule-columns').each(function() {
        initializeSelect2($(this));
    });

    // Handle adding new rules
    $('#scp-add-rule').on('click', function () {
        const rulesContainer = $('#scp-rules-container');
        const template = $('#scp-rule-template').html();

        // Use a timestamp to ensure a unique index for the new rule
        const newIndex = new Date().getTime();

        // Replace the placeholder index with the new unique index
        const newRuleHtml = template.replace(/__INDEX__/g, newIndex);

        // Hide the 'no rules' message if it exists
        $('#scp-no-rules-message').hide();

        // Append the new rule and get the new element
        const newRule = $(newRuleHtml).appendTo(rulesContainer);

        // Initialize Select2 on the new dropdown
        initializeSelect2(newRule.find('.scp-rule-columns'));
    });

    // Handle removing rules using event delegation
    $('#scp-rules-container').on('click', '.scp-remove-rule', function () {
        $(this).closest('.scp-rule').remove();

        // If no rules are left, show the 'no rules' message
        if ($('#scp-rules-container').find('.scp-rule').length === 0) {
            $('#scp-no-rules-message').show();
        }
    });
});
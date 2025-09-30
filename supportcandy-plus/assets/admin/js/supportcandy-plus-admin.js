jQuery(document).ready(function ($) {
    'use strict';

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

        // Append the new rule to the container
        rulesContainer.append(newRuleHtml);
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
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

    // Dual list for Queue Macro statuses
    $('#scp_add_status').on('click', function () {
        $('#scp_available_statuses option:selected').each(function () {
            $(this).remove().appendTo('#scp_selected_statuses');
        });
    });

    $('#scp_remove_status').on('click', function () {
        $('#scp_selected_statuses option:selected').each(function () {
            $(this).remove().appendTo('#scp_available_statuses');
        });
    });

    // Before submitting the form, select all items in the 'selected' lists.
    $('form[action="options.php"]').on('submit', function () {
        $('#scp_selected_statuses option').prop('selected', true);
        $('#scp_selected_utm_columns option').prop('selected', true);
    });

    // Dual list for UTM Columns
    $('#scp_add_utm_column').on('click', function () {
        $('#scp_available_utm_columns option:selected').each(function () {
            $(this).remove().appendTo('#scp_selected_utm_columns');
        });
    });

    $('#scp_remove_utm_column').on('click', function () {
        $('#scp_selected_utm_columns option:selected').each(function () {
            $(this).remove().appendTo('#scp_available_utm_columns');
        });
    });

    $('#scp_add_utm_column_all').on('click', function () {
        $('#scp_available_utm_columns option').each(function () {
            $(this).remove().appendTo('#scp_selected_utm_columns');
        });
    });

    $('#scp_remove_utm_column_all').on('click', function () {
        $('#scp_selected_utm_columns option').each(function () {
            $(this).remove().appendTo('#scp_available_utm_columns');
        });
    });

    // Test button for Queue Macro
    $('#scp_test_queue_macro_button').on('click', function () {
        const resultsContent = $('#scp_test_results_content');
        const resultsContainer = $('#scp_test_results');
        resultsContent.html('<p>Loading...</p>');
        resultsContainer.show();

        $.post(scp_admin_ajax.ajax_url, {
            action: 'scp_test_queue_macro',
            nonce: scp_admin_ajax.nonce
        }, function (response) {
            if (response.success) {
                let html = '<ul>';
                if (Object.keys(response.data).length === 0) {
                    html = '<p>No tickets found for the specified criteria.</p>';
                } else {
                    $.each(response.data, function (key, value) {
                        html += '<li><strong>' + key + ':</strong> ' + value + '</li>';
                    });
                }
                html += '</ul>';
                resultsContent.html(html);
            } else {
                resultsContent.html('<p>Error: ' + response.data + '</p>');
            }
        });
    });
});
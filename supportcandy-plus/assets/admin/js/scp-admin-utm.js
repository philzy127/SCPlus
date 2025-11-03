jQuery(document).ready(function($) {

    // Move Up
    $('#scp_utm_move_up').on('click', function() {
        var $selected = $('#scp_utm_selected_fields option:selected');
        if ($selected.length) {
            var $first = $selected.first();
            var $before = $first.prev();
            if ($before.length) {
                $selected.insertBefore($before);
            }
        }
    });

    // Move Down
    $('#scp_utm_move_down').on('click', function() {
        var $selected = $('#scp_utm_selected_fields option:selected');
        if ($selected.length) {
            var $last = $selected.last();
            var $after = $last.next();
            if ($after.length) {
                $selected.insertAfter($after);
            }
        }
    });

    // Move to Top
    $('#scp_utm_move_top').on('click', function() {
        var $selected = $('#scp_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#scp_utm_selected_fields').prepend($selected);
        }
    });

    // Move to Bottom
    $('#scp_utm_move_bottom').on('click', function() {
        var $selected = $('#scp_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#scp_utm_selected_fields').append($selected);
        }
    });

    // Add selected
    $('#scp_utm_add').click(function() {
        $('#scp_utm_available_fields option:selected').appendTo('#scp_utm_selected_fields');
    });

    // Remove selected
    $('#scp_utm_remove').click(function() {
        $('#scp_utm_selected_fields option:selected').appendTo('#scp_utm_available_fields');
    });

    // Add all
    $('#scp_utm_add_all').click(function() {
        $('#scp_utm_available_fields option').appendTo('#scp_utm_selected_fields');
    });

    // Remove all
    $('#scp_utm_remove_all').click(function() {
        $('#scp_utm_selected_fields option').appendTo('#scp_utm_available_fields');
    });


    // Save settings via AJAX
    $('#scp-utm-save-settings').on('click', function() {
        var selectedFields = [];
        $('#scp_utm_selected_fields option').each(function() {
            selectedFields.push($(this).val());
        });

        // Collect rename rules
        var renameRules = [];
        var validationError = false;
        $('#scp-utm-rules-container .scp-utm-rule-row').each(function() {
            var $row = $(this);
            var field = $row.find('.scp-utm-rule-field').val();
            var name = $row.find('.scp-utm-rule-name').val().trim();

            if (name === '') {
                alert('Rule name cannot be blank. Please provide a name or remove the rule.');
                validationError = true;
                return false; // Exit the .each() loop
            }

            renameRules.push({
                'field': field,
                'name': name
            });
        });

        if (validationError) {
            return; // Stop the save process
        }

        var data = {
            'action': 'scputm_save_settings',
            'nonce': scp_utm_admin_params.nonce,
            'selected_fields': selectedFields,
            'rename_rules': renameRules
        };

        $('.spinner').addClass('is-active');

        $.post(ajaxurl, data, function(response) {
            $('.spinner').removeClass('is-active');
            if (response.success) {
                // Display a success message (e.g., using a toast notification)
                alert(scp_utm_admin_params.save_success_message);
            } else {
                // Display an error message
                alert(scp_utm_admin_params.save_error_message);
            }
        });
    });

    // Add Rule
    $('#scp-utm-add-rule').on('click', function() {
        var template = $('#scp-utm-rule-template').html();
        $('#scp-utm-rules-container').append(template);
    });

    // Remove Rule (using event delegation)
    $('#scp-utm-rules-container').on('click', '.scp-utm-remove-rule', function() {
        $(this).closest('.scp-utm-rule-row').remove();
    });
});

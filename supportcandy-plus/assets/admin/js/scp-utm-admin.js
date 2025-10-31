jQuery( document ).ready( function( $ ) {

    // Move selected items from available to selected
    $('#scp_utm_add').click(function() {
        $('#scp_utm_available_fields option:selected').each(function() {
            $(this).appendTo('#scp_utm_selected_fields');
        });
    });

    // Move all items from available to selected
    $('#scp_utm_add_all').click(function() {
        $('#scp_utm_available_fields option').each(function() {
            $(this).appendTo('#scp_utm_selected_fields');
        });
    });

    // Move selected items from selected to available
    $('#scp_utm_remove').click(function() {
        $('#scp_utm_selected_fields option:selected').each(function() {
            $(this).appendTo('#scp_utm_available_fields');
        });
		sortSelect('#scp_utm_available_fields');
    });

    // Move all items from selected to available
    $('#scp_utm_remove_all').click(function() {
        $('#scp_utm_selected_fields option').each(function() {
            $(this).appendTo('#scp_utm_available_fields');
        });
		sortSelect('#scp_utm_available_fields');
    });

	// Helper function to sort a select dropdown alphabetically.
	function sortSelect(selector) {
		var options = $(selector + ' option');
		var arr = options.map(function(_, o) {
			return { t: $(o).text(), v: o.value };
		}).get();
		arr.sort(function(o1, o2) {
			return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
		});
		options.each(function(i, o) {
			o.value = arr[i].v;
			$(o).text(arr[i].t);
		});
	}

    // On form submission, select all options in the 'selected' box
    // so they are all included in the POST data.
    $('form[action="options.php"]').submit(function() {
        $('#scp_utm_selected_fields option').prop('selected', true);
    });

});

jQuery( document ).ready(
	function ($) {
		// Rule builder for Conditional Hiding.
		$( '#scp-add-date-rule' ).on(
			'click',
			function () {
				let ruleIndex = $('#scp-rules-container .scp-rule').length ? Math.max( ... $.map( $('#scp-rules-container .scp-rule'), el => $( el ).index() ) ) + 1 : 0;
				let template = $( '#scp-rule-template' ).html().replace( /__INDEX__/g, ruleIndex );
				$( '#scp-rules-container' ).append( template );
				$( '#scp-no-rules-message' ).hide();
			}
		);

		$( '#scp-rules-container' ).on(
			'click',
			'.scp-remove-rule',
			function () {
				$( this ).closest( '.scp-rule' ).remove();
				if ($('#scp-rules-container .scp-rule').length === 0) {
					$( '#scp-no-rules-message' ).show();
				}
			}
		);

		// Logic for the status dual list.
		$( '#scp_add_status' ).on(
			'click',
			function () {
				$( '#scp_available_statuses option:selected' ).appendTo( '#scp_selected_statuses' );
				sortSelect( '#scp_selected_statuses' );
				$( '#scp_selected_statuses' ).prop( 'selectedIndex', -1 ); // Clear selection.
			}
		);

		$( '#scp_remove_status' ).on(
			'click',
			function () {
				$( '#scp_selected_statuses option:selected' ).appendTo( '#scp_available_statuses' );
				sortSelect( '#scp_available_statuses' );
				$( '#scp_available_statuses' ).prop( 'selectedIndex', -1 ); // Clear selection.
			}
		);

		// AJAX call for testing queue macro.
		$( '#scp_test_queue_macro_button' ).on(
			'click',
			function () {
				$( '#scp_test_results' ).show();
				$( '#scp_test_results_content' ).html( 'Loading...' );
				$.post(
					ajaxurl,
					{
						action: 'scp_test_queue_macro'
					},
					function (response) {
						$( '#scp_test_results_content' ).html( response );
					}
				);
			}
		);

		// UTM dual list logic.
		$( '#scp_utm_add_column' ).on(
			'click',
			function () {
				$( '#scp_utm_available_columns option:selected' ).appendTo( '#scp_utm_selected_columns' );
			}
		);

		$( '#scp_utm_remove_column' ).on(
			'click',
			function () {
				$( '#scp_utm_selected_columns option:selected' ).appendTo( '#scp_utm_available_columns' );
				sortSelect( '#scp_utm_available_columns' );
			}
		);

		$( '#scp_utm_add_all_columns' ).on(
			'click',
			function () {
				$( '#scp_utm_available_columns option' ).appendTo( '#scp_utm_selected_columns' );
			}
		);

		$( '#scp_utm_remove_all_columns' ).on(
			'click',
			function () {
				$( '#scp_utm_selected_columns option' ).appendTo( '#scp_utm_available_columns' );
				sortSelect( '#scp_utm_available_columns' );
			}
		);

		// Before the form is submitted, select all options in the "selected" list
		// so they are all included in the POST data.
		$( 'form' ).on(
			'submit',
			function () {
				$( '#scp_selected_statuses option' ).prop( 'selected', true );
				$( '#scp_utm_selected_columns option' ).prop( 'selected', true );
			}
		);

		// Helper function to sort a select element's options.
		function sortSelect(selectId) {
			let options = $( selectId + ' option' );
			let arr     = options.map(
				function (_, o) {
					return { t: $( o ).text(), v: o.value };
				}
			).get();
			arr.sort(
				function (o1, o2) {
					return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
				}
			);
			options.each(
				function (i, o) {
					o.value = arr[i].v;
					$( o ).text( arr[i].t );
				}
			);
		}
	}
);

jQuery( document ).ready( function( $ ) {
	'use strict';

	// Helper to move options between selects
	function moveOptions( from, to ) {
		$( from + ' option:selected' ).each( function() {
			$( to ).append( $( this ).clone() );
			$( this ).remove();
		} );
	}

	function moveAllOptions( from, to ) {
		$( from + ' option' ).each( function() {
			$( to ).append( $( this ).clone() );
			$( this ).remove();
		} );
	}

	// Move selected fields from Available to Selected
	$( '#scp_utm_add' ).on( 'click', function() {
		moveOptions( '#scp_utm_available_fields', '#scp_utm_selected_fields' );
	} );

	// Move all fields from Available to Selected
	$( '#scp_utm_add_all' ).on( 'click', function() {
		moveAllOptions( '#scp_utm_available_fields', '#scp_utm_selected_fields' );
	} );

	// Move selected fields from Selected to Available
	$( '#scp_utm_remove' ).on( 'click', function() {
		moveOptions( '#scp_utm_selected_fields', '#scp_utm_available_fields' );
	} );

	// Move all fields from Selected to Available
	$( '#scp_utm_remove_all' ).on( 'click', function() {
		moveAllOptions( '#scp_utm_selected_fields', '#scp_utm_available_fields' );
	} );

	// Sorting handlers
	$( '#scp_utm_move_up' ).on( 'click', function() {
		var $selected = $( '#scp_utm_selected_fields option:selected' );
		if ( $selected.length ) {
			$selected.first().prev().before( $selected );
		}
	} );

	$( '#scp_utm_move_down' ).on( 'click', function() {
		var $selected = $( '#scp_utm_selected_fields option:selected' );
		if ( $selected.length ) {
			$selected.last().next().after( $selected );
		}
	} );

	$( '#scp_utm_move_top' ).on( 'click', function() {
		var $selected = $( '#scp_utm_selected_fields option:selected' );
		$( '#scp_utm_selected_fields' ).prepend( $selected );
	} );

	$( '#scp_utm_move_bottom' ).on( 'click', function() {
		var $selected = $( '#scp_utm_selected_fields option:selected' );
		$( '#scp_utm_selected_fields' ).append( $selected );
	} );

	// AJAX Save Handler
	$( '#scp-utm-save-settings' ).on( 'click', function(e) {
		e.preventDefault();

		var $button = $( this );
		var $spinner = $button.siblings( '.spinner' );

		// Select all options to be included in the data
		$( '#scp_utm_selected_fields option' ).prop( 'selected', true );
		var selectedFields = $( '#scp_utm_selected_fields' ).val();

		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scputm_save_settings',
				nonce: scp_utm_admin_params.nonce,
				selected_fields: selectedFields
			},
			success: function( response ) {
				if ( response.success ) {
					// Show success toast
					$( '.wrap h1' ).after( '<div class="notice notice-success is-dismissible"><p>' + scp_utm_admin_params.save_success_message + '</p></div>' );
				} else {
					// Show error toast
					$( '.wrap h1' ).after( '<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>' );
				}
				// Remove toast after a few seconds
				setTimeout(function() {
					$('.notice.is-dismissible').fadeOut('slow', function() {
						$(this).remove();
					});
				}, 5000);
			},
			error: function( xhr, status, error ) {
				// Show a generic error toast
				$( '.wrap h1' ).after( '<div class="notice notice-error is-dismissible"><p>' + scp_utm_admin_params.save_error_message + '</p></div>' );
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
				// De-select options after sending
				$( '#scp_utm_selected_fields option' ).prop( 'selected', false );
			}
		});
	});

} );

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
		selectAllInSelectedBox();
	} );

	// Move all fields from Available to Selected
	$( '#scp_utm_add_all' ).on( 'click', function() {
		moveAllOptions( '#scp_utm_available_fields', '#scp_utm_selected_fields' );
		selectAllInSelectedBox();
	} );

	// Move selected fields from Selected to Available
	$( '#scp_utm_remove' ).on( 'click', function() {
		moveOptions( '#scp_utm_selected_fields', '#scp_utm_available_fields' );
		selectAllInSelectedBox();
	} );

	// Move all fields from Selected to Available
	$( '#scp_utm_remove_all' ).on( 'click', function() {
		moveAllOptions( '#scp_utm_selected_fields', '#scp_utm_available_fields' );
		selectAllInSelectedBox();
	} );

	/**
	 * Ensures all options in the 'selected' box are marked as selected
	 * before the form submits, so they are included in the POST data.
	 */
	function selectAllInSelectedBox() {
		$( '#scp_utm_selected_fields option' ).prop( 'selected', true );
	}

	// Before the form submits, select all items in the right-hand box.
	$( 'form' ).on( 'submit', function() {
		selectAllInSelectedBox();
	} );

} );

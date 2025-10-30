jQuery( document ).ready( function( $ ) {
    'use strict';

    // Move item from available to selected
    $( '#scputm_add_field' ).on( 'click', function() {
        $( '#scputm_available_fields option:selected' ).each( function() {
            $( this ).appendTo( '#scputm_selected_fields' );
        });
    });

    // Move all items from available to selected
    $( '#scputm_add_all_fields' ).on( 'click', function() {
        $( '#scputm_available_fields option' ).each( function() {
            $( this ).appendTo( '#scputm_selected_fields' );
        });
    });

    // Move item from selected to available
    $( '#scputm_remove_field' ).on( 'click', function() {
        $( '#scputm_selected_fields option:selected' ).each( function() {
            $( this ).appendTo( '#scputm_available_fields' );
        });
    });

    // Move all items from selected to available
    $( '#scputm_remove_all_fields' ).on( 'click', function() {
        $( '#scputm_selected_fields option' ).each( function() {
            $( this ).appendTo( '#scputm_available_fields' );
        });
    });

    // Ensure all items in the selected list are selected before form submission
    $( 'form' ).on( 'submit', function() {
        $( '#scputm_selected_fields option' ).prop( 'selected', true );
    });

});

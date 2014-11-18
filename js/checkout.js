jQuery( document ).ready( function( $ ) {
    if ( $( '.make_purchase' ).is( '*' ) ) {
        // Get containing element for the gateway radio buttons
        var container = $( '.wpsc_gateway_container' );

        // Determine whether or not to hide the testmode gateway
        var price = $( '#checkout_total span.pricedisplay' ).html();
        if ( price == '$0.00'){
            var testmode = 'show-only';
        }

        else { var testmode = 'hide '}

        // Loop through the gateways hiding or showing each based on the value of testmode

        $( 'div.custom_gateway', container ).each( function( index, value ) {
            if ( testmode == 'show-only' ) {
                if ( ! $( 'input[value="wpsc_merchant_testmode"]', this ).is( '*' ) )
                    $( this ).remove();
            } else if ( 'hide' ) {
                if ( $( 'input[value="wpsc_merchant_testmode"]', this ).is( '*' ) )
                    $( this ).remove();
            }
        } );

        // Mark the first one as checked
        $( 'div.custom_gateway .custom_gateway:first', container ).attr( 'checked', 'checked' );
    }
} );
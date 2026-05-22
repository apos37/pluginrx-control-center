jQuery( function ( $ ) {

    var tableBody = $( '#prxctrl-sites-body' );

    // Keep a hidden template row
    var templateRow = tableBody.find( '.prxctrl-site-template' ).first().clone();
    templateRow.addClass( 'prxctrl-site-template-clone' ).hide();
    tableBody.append( templateRow );

    $( '#prxctrl-add-site' ).on( 'click', function ( e ) {
        e.preventDefault();

        var row = templateRow.clone();
        row.removeClass( 'prxctrl-site-template-clone' ).show();
        row.find( 'input' ).val( '' );

        tableBody.append( row );
    } );

    tableBody.on( 'click', '.prxctrl-remove-site', function ( e ) {
        e.preventDefault();
        $( this ).closest( 'tr' ).remove();
    } );

    // Make sites sortable
    var sitesBody = $( '#prxctrl-sites-body' );

    sitesBody.sortable( {
        handle: '.prxctrl-sort-handle',
        items: 'tr:not( .prxctrl-site-template )',
        axis: 'y',
        update: function () {

            var siteOrder = [];

            sitesBody.find( 'tr[data-site-id]' ).each( function () {
                siteOrder.push( $( this ).data( 'site-id' ) );
            } );

            $.post( ajaxurl, {
                action: 'prxctrl_save_site_order',
                nonce: prxctrl_settings.nonce,
                site_order: siteOrder
            } );

        }
    } );

} );

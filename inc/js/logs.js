/**
 * PLUGINRX CONTROL CENTER: Logs
 *
 * Clear All and Clear All Success, and auto-submitting the filters.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlLogs || {};
    var prx = window.prxctrl;


    $( function () {
        $( '.prxctrl-clear-logs' ).on( 'click', function () {
            var button = $( this );
            var status = String( button.data( 'status' ) || '' );

            if ( ! window.confirm( 'success' === status ? config.i18n.confirmSuccess : config.i18n.confirmAll ) ) {
                return;
            }

            prx.busy( button, '...' );

            prx.post( 'prxctrl_clear_logs', { nonce: config.nonce, status: status } ).done( function ( response ) {
                if ( ! response.success ) {
                    prx.unbusy( button );
                    prx.toast( prx.message( response ), 'error' );
                    return;
                }

                prx.toast( prx.format( config.i18n.cleared, response.data.deleted ), 'success' );

                setTimeout( function () {
                    window.location.reload();
                }, 700 );
            } ).fail( function ( xhr ) {
                prx.unbusy( button );
                prx.toast( prx.message( xhr ), 'error' );
            } );
        } );

        $( '#prxctrl-filter-per_page' ).on( 'change', function () {
            $( '#prxctrl-logs-form' ).find( 'input[name="filter_action"]' ).trigger( 'click' );
        } );
    } );

} )( jQuery );

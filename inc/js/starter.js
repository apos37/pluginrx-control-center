/**
 * PLUGINRX CONTROL CENTER: Setup
 *
 * Shows the network questions only when they apply, and saves the answers.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlStarter || {};
    var prx = window.prxctrl;


    /**
     * Show the network steps that apply to the chosen mode and location.
     */
    function updateSteps() {
        var mode = $( 'input[name="mode"]:checked' ).val();
        var hasHost = 'host' === mode || 'both' === mode;
        var location = $( 'input[name="location"]:checked' ).val();

        $( '#prxctrl-step-location' ).prop( 'hidden', ! hasHost );
        $( '#prxctrl-step-subsites' ).prop( 'hidden', ! hasHost || 'network' !== location );
    } // End updateSteps()


    $( function () {
        var button = $( '#prxctrl-complete-setup' );
        var error = $( '.prxctrl-starter .prxctrl-modal-error' );

        updateSteps();

        $( document ).on( 'change', 'input[name="mode"], input[name="location"]', updateSteps );

        $( 'select[name="location_site"]' ).on( 'change', function () {
            $( 'input[name="location"][value="site"]' ).prop( 'checked', true );
            updateSteps();
        } );

        button.on( 'click', function () {
            var mode = $( 'input[name="mode"]:checked' ).val();
            var subsites = [];

            if ( ! mode ) {
                error.text( config.i18n.chooseMode ).prop( 'hidden', false );
                return;
            }

            $( 'input[name="subsites[]"]:checked' ).each( function () {
                subsites.push( $( this ).val() );
            } );

            error.prop( 'hidden', true );
            prx.busy( button, config.i18n.finishing + '...' );

            prx.post( 'prxctrl_complete_setup', {
                nonce: config.nonce,
                mode: mode,
                location: $( 'input[name="location"]:checked' ).val() || 'network',
                location_site: $( 'select[name="location_site"]' ).val() || '',
                subsites: subsites,
                auto_include: $( 'input[name="auto_include"]' ).is( ':checked' ) ? 1 : 0
            } ).done( function ( response ) {
                if ( response.success ) {
                    window.location.href = response.data.redirect;
                    return;
                }

                prx.unbusy( button );
                error.text( prx.message( response ) ).prop( 'hidden', false );
            } ).fail( function ( xhr ) {
                prx.unbusy( button );
                error.text( prx.message( xhr ) ).prop( 'hidden', false );
            } );
        } );
    } );

} )( jQuery );

/**
 * PLUGINRX CONTROL CENTER: Common
 *
 * Shared helpers for every page: AJAX, toasts, the unsaved-changes reminder, busy buttons and modals.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlCommon || {};
    var prx = window.prxctrl = window.prxctrl || {};
    var isDirty = false;
    var titleTimer = null;
    var originalTitle = document.title;
    var lastFocus = null;


    /**
     * Escape text for use in HTML.
     *
     * @param {string} text
     * @return {string}
     */
    prx.escape = function ( text ) {
        return $( '<div>' ).text( null === text || undefined === text ? '' : String( text ) ).html();
    }; // End escape()


    /**
     * Fill %s, %d and %1$s style placeholders.
     *
     * @param {string} template
     * @return {string}
     */
    prx.format = function ( template ) {
        var args = Array.prototype.slice.call( arguments, 1 );
        var index = 0;

        return String( template ).replace( /%(\d+\$)?[sd]/g, function ( match, position ) {
            var value = position ? args[ parseInt( position, 10 ) - 1 ] : args[ index++ ];

            return undefined === value ? '' : value;
        } );
    }; // End format()


    /**
     * Send an AJAX request.
     *
     * @param {string} action
     * @param {Object} data
     * @return {jqXHR}
     */
    prx.post = function ( action, data ) {
        var payload = $.extend( { action: action }, data || {} );

        if ( config.network ) {
            payload.prxctrl_network = 1;
        }

        return $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: payload
        } ).done( function ( response ) {
            if ( config.consoleLog && window.console ) {
                window.console.log( '[PluginRx Control Center]', action, response );
            }
        } );
    }; // End post()


    /**
     * Get the message from an AJAX response or failure.
     *
     * @param {Object} response
     * @return {string}
     */
    prx.message = function ( response ) {
        if ( -1 === response || '-1' === response || ( response && '-1' === response.responseText ) ) {
            return config.i18n.expired;
        }

        if ( response && response.data && response.data.message ) {
            return response.data.message;
        }

        if ( response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ) {
            return response.responseJSON.data.message;
        }

        return config.i18n.error;
    }; // End message()


    /**
     * Show a toast.
     *
     * @param {string} message
     * @param {string} type success|error|info
     * @param {string} linkUrl
     * @param {string} linkText
     */
    prx.toast = function ( message, type, linkUrl, linkText ) {
        var container = $( '.prxctrl-toasts' );

        if ( ! container.length ) {
            container = $( '<div class="prxctrl-toasts" role="status" aria-live="polite"></div>' ).appendTo( 'body' );
        }

        var toast = $( '<div class="prxctrl-toast"></div>' ).addClass( 'is-' + ( type || 'info' ) );
        var icon = 'error' === type ? 'dashicons-dismiss' : ( 'success' === type ? 'dashicons-yes-alt' : 'dashicons-info' );

        toast.append( $( '<span class="dashicons" aria-hidden="true"></span>' ).addClass( icon ) );
        toast.append( $( '<span></span>' ).text( message ) );

        if ( linkUrl ) {
            toast.append( ' ' ).append( $( '<a></a>' ).attr( 'href', linkUrl ).text( linkText || linkUrl ) );
        }

        container.append( toast );

        setTimeout( function () {
            toast.fadeOut( 300, function () {
                toast.remove();
            } );
        }, 'error' === type ? 9000 : 4000 );
    }; // End toast()


    /**
     * Mark the page as having unsaved changes.
     */
    prx.markDirty = function () {
        if ( isDirty ) {
            return;
        }

        isDirty = true;
        $( '#prxctrl-save-reminder' ).fadeIn( 200 );
    }; // End markDirty()


    /**
     * Clear the unsaved-changes state.
     */
    prx.clearDirty = function () {
        isDirty = false;
        $( '#prxctrl-save-reminder' ).hide();
    }; // End clearDirty()


    /**
     * Check for unsaved changes.
     *
     * @return {boolean}
     */
    prx.isDirty = function () {
        return isDirty;
    }; // End isDirty()


    /**
     * Animate the browser tab title while saving.
     */
    prx.startSavingTitle = function () {
        var dots = 0;

        originalTitle = document.title;
        document.title = config.i18n.saving;

        clearInterval( titleTimer );
        titleTimer = setInterval( function () {
            dots = ( dots + 1 ) % 4;
            document.title = config.i18n.saving + '.'.repeat( dots );
        }, 500 );
    }; // End startSavingTitle()


    /**
     * Restore the browser tab title.
     */
    prx.stopSavingTitle = function () {
        clearInterval( titleTimer );
        document.title = originalTitle;
    }; // End stopSavingTitle()


    /**
     * Put a button in its busy state.
     *
     * @param {jQuery} button
     * @param {string} text
     */
    prx.busy = function ( button, text ) {
        if ( ! button.data( 'prxctrlHtml' ) ) {
            button.data( 'prxctrlHtml', button.html() );
        }

        button.addClass( 'is-busy' ).prop( 'disabled', true ).html( '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' + prx.escape( text ) );
    }; // End busy()


    /**
     * Restore a busy button.
     *
     * @param {jQuery} button
     */
    prx.unbusy = function ( button ) {
        if ( button.data( 'prxctrlHtml' ) ) {
            button.html( button.data( 'prxctrlHtml' ) );
            button.removeData( 'prxctrlHtml' );
        }

        button.removeClass( 'is-busy' ).prop( 'disabled', false );
    }; // End unbusy()


    /**
     * Show a saved or error note next to a button.
     *
     * @param {jQuery} button
     * @param {string} message
     * @param {boolean} success
     */
    prx.note = function ( button, message, success ) {
        $( '.prxctrl-save-note' ).remove();

        var note = $( '<span class="prxctrl-save-note" role="status"></span>' ).text( message ).addClass( success ? 'is-success' : 'is-error' );

        button.after( note );

        setTimeout( function () {
            note.fadeOut( 400, function () {
                note.remove();
            } );
        }, 4000 );
    }; // End note()


    /**
     * Get an animated check mark.
     *
     * @return {string}
     */
    prx.checkMark = function () {
        return '<svg class="prxctrl-check-mark" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M7 12.5l3.2 3.2L17 9"></path></svg>';
    }; // End checkMark()


    /**
     * Get an animated cross mark.
     *
     * @return {string}
     */
    prx.crossMark = function () {
        return '<svg class="prxctrl-cross-mark" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M8.5 8.5l7 7M15.5 8.5l-7 7"></path></svg>';
    }; // End crossMark()


    /**
     * Open a modal.
     *
     * @param {jQuery} modal
     */
    prx.openModal = function ( modal ) {
        lastFocus = document.activeElement;
        modal.prop( 'hidden', false );
        $( 'body' ).addClass( 'prxctrl-modal-open' );

        setTimeout( function () {
            modal.find( 'input:not([type="hidden"]):not([readonly]), select, textarea' ).filter( ':visible' ).first().trigger( 'focus' );
        }, 30 );
    }; // End openModal()


    /**
     * Close a modal.
     *
     * @param {jQuery} modal
     */
    prx.closeModal = function ( modal ) {
        modal.prop( 'hidden', true );
        $( 'body' ).removeClass( 'prxctrl-modal-open' );

        if ( lastFocus ) {
            $( lastFocus ).trigger( 'focus' );
        }
    }; // End closeModal()


    /**
     * Keep a "Select all" box in sync with the other checkboxes in its fieldset.
     *
     * @param {jQuery} group
     */
    prx.syncSelectAll = function ( group ) {
        var boxes = group.find( '.prxctrl-checkboxes input[type="checkbox"], .prxctrl-permission-list input[type="checkbox"]' );
        var checked = boxes.filter( ':checked' ).length;
        var toggle = group.find( '.prxctrl-select-all-toggle' );

        toggle.prop( 'checked', boxes.length > 0 && checked === boxes.length );
        toggle.prop( 'indeterminate', checked > 0 && checked < boxes.length );
    }; // End syncSelectAll()


    $( function () {
        $( '.prxctrl-select-all-toggle' ).each( function () {
            prx.syncSelectAll( $( this ).closest( 'fieldset' ) );
        } );
    } );

    $( document ).on( 'change', '.prxctrl-select-all-toggle', function () {
        $( this ).closest( 'fieldset' ).find( '.prxctrl-checkboxes input[type="checkbox"], .prxctrl-permission-list input[type="checkbox"]' ).prop( 'checked', $( this ).is( ':checked' ) ).trigger( 'change' );
    } );

    $( document ).on( 'change', 'fieldset .prxctrl-checkboxes input[type="checkbox"], fieldset .prxctrl-permission-list input[type="checkbox"]', function () {
        var group = $( this ).closest( 'fieldset' );

        if ( group.find( '.prxctrl-select-all-toggle' ).length ) {
            prx.syncSelectAll( group );
        }
    } );

    $( document ).on( 'click', '.prxctrl-modal [data-close]', function () {
        prx.closeModal( $( this ).closest( '.prxctrl-modal' ) );
    } );

    $( document ).on( 'keydown', function ( event ) {
        var open = $( '.prxctrl-modal:not([hidden])' );

        if ( ! open.length ) {
            return;
        }

        if ( 'Escape' === event.key ) {
            prx.closeModal( open );
            return;
        }

        if ( 'Tab' !== event.key ) {
            return;
        }

        var focusable = open.find( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])' ).filter( ':visible' );

        if ( ! focusable.length ) {
            return;
        }

        var first = focusable.get( 0 );
        var last = focusable.get( focusable.length - 1 );

        if ( event.shiftKey && document.activeElement === first ) {
            event.preventDefault();
            last.focus();
        } else if ( ! event.shiftKey && document.activeElement === last ) {
            event.preventDefault();
            first.focus();
        }
    } );

    $( window ).on( 'beforeunload', function ( event ) {
        if ( isDirty ) {
            event.preventDefault();
            event.returnValue = '';
            return '';
        }
    } );

    $( document ).on( 'click keydown', '.prxctrl-copy', function ( event ) {
        if ( 'keydown' === event.type && 'Enter' !== event.key ) {
            return;
        }

        var text = $( this ).text().trim();

        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( text ).then( function () {
                prx.toast( text, 'success' );
            } );
        }
    } );

} )( jQuery );

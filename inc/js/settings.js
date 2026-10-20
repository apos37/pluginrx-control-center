/**
 * PLUGINRX CONTROL CENTER: Settings
 *
 * Settings page: AJAX save (with Ctrl+S), the unsaved-changes reminder,
 * upload/reset, the widget manager, link fields, permission "Select all" boxes and the API key.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlSettings || {};
    var prx = window.prxctrl;
    var form;
    var saveButton;


    /**
     * Collect every setting on the page.
     *
     * @return {Object}
     */
    function gatherSettings() {
        var data = {};

        form.find( '[name]' ).each( function () {
            var field = $( this );
            var name = field.attr( 'name' );
            var type = field.attr( 'type' );

            if ( field.closest( 'form[action*="admin-post.php"]' ).length ) {
                return;
            }

            if ( field.data( 'json' ) ) {
                try {
                    data[ name ] = JSON.parse( field.val() || 'null' );
                } catch ( error ) {
                    data[ name ] = null;
                }

                return;
            }

            if ( 'radio' === type ) {
                if ( field.is( ':checked' ) ) {
                    data[ name ] = field.val();
                }

                return;
            }

            if ( 'checkbox' === type ) {
                if ( '[]' === name.slice( -2 ) ) {
                    var key = name.slice( 0, -2 );

                    data[ key ] = data[ key ] || [];

                    if ( field.is( ':checked' ) ) {
                        data[ key ].push( field.val() );
                    }
                } else {
                    data[ name ] = field.is( ':checked' ) ? 1 : 0;
                }

                return;
            }

            data[ name ] = field.val();
        } );

        return data;
    } // End gatherSettings()


    /**
     * Save the settings.
     */
    function saveSettings() {
        if ( saveButton.prop( 'disabled' ) || ! serializeLinkFields( true ) ) {
            return;
        }

        prx.startSavingTitle();
        prx.busy( saveButton, prx.format( '%s...', window.prxctrlCommon.i18n.saving ) );

        prx.post( 'prxctrl_save_settings', {
            nonce: config.nonce,
            page: config.page,
            settings: JSON.stringify( gatherSettings() )
        } ).done( function ( response ) {
            prx.stopSavingTitle();
            prx.unbusy( saveButton );

            if ( ! response.success ) {
                prx.note( saveButton, prx.message( response ), false );
                return;
            }

            prx.clearDirty();

            if ( response.data && response.data.redirect ) {
                prx.note( saveButton, config.i18n.savedReload, true );
                window.location.href = response.data.redirect;
                return;
            }

            form.find( '.prxctrl-link-field' ).attr( 'data-saved', '1' ).find( '.prxctrl-link-slug' ).prop( 'readonly', true );
            prx.note( saveButton, config.i18n.saved, true );
        } ).fail( function ( xhr ) {
            prx.stopSavingTitle();
            prx.unbusy( saveButton );
            prx.note( saveButton, prx.message( xhr ), false );
        } );
    } // End saveSettings()


    /**
     * Upload a settings file.
     *
     * @param {Event} event
     */
    function importSettings( event ) {
        var file = event.target.files[ 0 ];

        if ( ! file ) {
            return;
        }

        if ( ! window.confirm( config.i18n.confirmImport ) ) {
            $( event.target ).val( '' );
            return;
        }

        var reader = new FileReader();

        reader.onload = function ( loaded ) {
            $( '#prxctrl-upload-filename' ).text( file.name ).show();

            prx.post( 'prxctrl_import_settings', { nonce: config.nonce, payload: loaded.target.result } ).done( function ( response ) {
                if ( response.success ) {
                    prx.clearDirty();
                    prx.toast( config.i18n.importOk, 'success' );
                    setTimeout( function () {
                        window.location.reload();
                    }, 900 );
                } else {
                    prx.toast( prx.message( response ), 'error' );
                }
            } ).fail( function ( xhr ) {
                prx.toast( prx.message( xhr ), 'error' );
            } );
        };

        reader.readAsText( file );
    } // End importSettings()


    /**
     * Reset the settings on this page.
     */
    function resetSettings() {
        if ( ! window.confirm( config.i18n.confirmReset ) ) {
            return;
        }

        prx.post( 'prxctrl_reset_settings', { nonce: config.nonce, page: config.page } ).done( function ( response ) {
            if ( response.success ) {
                prx.clearDirty();
                prx.toast( config.i18n.resetOk, 'success' );
                setTimeout( function () {
                    window.location.reload();
                }, 900 );
            } else {
                prx.toast( prx.message( response ), 'error' );
            }
        } ).fail( function ( xhr ) {
            prx.toast( prx.message( xhr ), 'error' );
        } );
    } // End resetSettings()


    /**
     * Store the widget layout from the board and tray.
     */
    function storeWidgetLayout() {
        var layout = { enabled: [], disabled: [] };

        $( '.prxctrl-widget-board .prxctrl-widget' ).each( function () {
            layout.enabled.push( $( this ).data( 'widget' ) );
        } );

        $( '#prxctrl-widget-tray .prxctrl-widget' ).each( function () {
            layout.disabled.push( $( this ).data( 'widget' ) );
        } );

        $( '#prxctrl-widget-layout' ).val( JSON.stringify( layout ) );
        $( '.prxctrl-tray-empty' ).toggle( 0 === layout.disabled.length );
    } // End storeWidgetLayout()


    /**
     * Set up the widget manager.
     */
    function initWidgetManager() {
        var board = $( '.prxctrl-widget-board' );

        if ( ! board.length || ! $.fn.sortable ) {
            return;
        }

        board.add( '#prxctrl-widget-tray' ).sortable( {
            items: '> .prxctrl-widget',
            connectWith: '.prxctrl-widget-board, #prxctrl-widget-tray',
            placeholder: 'prxctrl-widget-placeholder',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            update: function () {
                storeWidgetLayout();
                prx.markDirty();
            }
        } );

        storeWidgetLayout();
    } // End initWidgetManager()


    /**
     * Turn a label into a slug.
     *
     * @param {string} text
     * @return {string}
     */
    function slugify( text ) {
        return String( text ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' );
    } // End slugify()


    /**
     * Store the link fields, optionally validating them.
     *
     * @param {boolean} validate
     * @return {boolean}
     */
    function serializeLinkFields( validate ) {
        var list = $( '#prxctrl-link-fields' );

        if ( ! list.length ) {
            return true;
        }

        var fields = [];
        var slugs = {};
        var valid = true;

        list.find( '.prxctrl-link-field' ).each( function () {
            var row = $( this );
            var label = row.find( '.prxctrl-link-label' ).val().trim();
            var slug = slugify( row.find( '.prxctrl-link-slug' ).val() || label );

            row.find( 'input' ).removeAttr( 'aria-invalid' );

            if ( '' === label && '' === slug ) {
                return;
            }

            if ( '' === label ) {
                row.find( '.prxctrl-link-label' ).attr( 'aria-invalid', 'true' );
                valid = false;
                return;
            }

            if ( slugs[ slug ] || 'login' === slug ) {
                row.find( '.prxctrl-link-slug' ).attr( 'aria-invalid', 'true' );
                valid = false;
                return;
            }

            slugs[ slug ] = true;
            fields.push( { label: label, slug: slug } );
        } );

        if ( validate && ! valid ) {
            prx.toast( Object.keys( slugs ).length && list.find( '.prxctrl-link-slug[aria-invalid]' ).length ? config.i18n.slugDuplicate : config.i18n.labelRequired, 'error' );
            return false;
        }

        $( '#prxctrl-link-fields-value' ).val( JSON.stringify( fields ) );

        return true;
    } // End serializeLinkFields()


    /**
     * Set up the link fields repeater.
     */
    function initLinkFields() {
        var list = $( '#prxctrl-link-fields' );

        if ( ! list.length ) {
            return;
        }

        if ( $.fn.sortable ) {
            list.sortable( {
                items: '> .prxctrl-link-field',
                handle: '.prxctrl-link-handle',
                axis: 'y',
                update: function () {
                    serializeLinkFields( false );
                    prx.markDirty();
                }
            } );
        }

        $( '#prxctrl-add-link-field' ).on( 'click', function () {
            var row = $( $( '#prxctrl-link-field-template' ).html() );

            list.append( row );
            row.find( '.prxctrl-link-label' ).trigger( 'focus' );
            prx.markDirty();
        } );

        list.on( 'input', '.prxctrl-link-label', function () {
            var row = $( this ).closest( '.prxctrl-link-field' );

            if ( '1' !== String( row.attr( 'data-saved' ) ) && ! row.data( 'slugEdited' ) ) {
                row.find( '.prxctrl-link-slug' ).val( slugify( $( this ).val() ) );
            }

            serializeLinkFields( false );
        } );

        list.on( 'input', '.prxctrl-link-slug', function () {
            $( this ).closest( '.prxctrl-link-field' ).data( 'slugEdited', true );
            serializeLinkFields( false );
        } );

        list.on( 'click', '.prxctrl-link-remove', function () {
            var row = $( this ).closest( '.prxctrl-link-field' );

            if ( '1' === String( row.attr( 'data-saved' ) ) && ! window.confirm( config.i18n.confirmRemoveLink ) ) {
                return;
            }

            row.remove();
            serializeLinkFields( false );
            prx.markDirty();
        } );
    } // End initLinkFields()


    /**
     * Set up the API key controls.
     */
    function initApiKey() {
        var agent = config.agent || {};
        var input = $( '#prxctrl-api-key' );
        var showButton = $( '#prxctrl-show-api-key' );

        if ( ! input.length ) {
            return;
        }

        showButton.on( 'click', function () {
            var hidden = 'password' === input.attr( 'type' );

            input.attr( 'type', hidden ? 'text' : 'password' );
            showButton.text( hidden ? agent.hide : agent.show );
        } );

        $( '#prxctrl-copy-api-key' ).on( 'click', function () {
            var button = $( this );

            if ( navigator.clipboard ) {
                navigator.clipboard.writeText( input.val() ).then( function () {
                    prx.note( button, agent.copied, true );
                } );
            }
        } );

        $( '#prxctrl-generate-api-key' ).on( 'click', function () {
            var button = $( this );

            if ( input.val() && ! window.confirm( agent.confirmRegenerate ) ) {
                return;
            }

            prx.busy( button, '...' );

            prx.post( 'prxctrl_generate_api_key', { nonce: config.nonce } ).done( function ( response ) {
                prx.unbusy( button );

                if ( ! response.success ) {
                    prx.toast( prx.message( response ), 'error' );
                    return;
                }

                input.val( response.data.key ).attr( 'type', 'text' );
                showButton.text( agent.hide ).prop( 'disabled', false );
                $( '#prxctrl-copy-api-key' ).prop( 'disabled', false );
                prx.toast( agent.generated, 'success' );
            } ).fail( function ( xhr ) {
                prx.unbusy( button );
                prx.toast( prx.message( xhr ), 'error' );
            } );
        } );
    } // End initApiKey()


    $( function () {
        form = $( '#prxctrl-settings-form' );
        saveButton = $( '#prxctrl-save-settings' );

        if ( ! form.length ) {
            return;
        }

        form.on( 'input change', '[name]', function () {
            if ( ! $( this ).closest( 'form[action*="admin-post.php"]' ).length && 'prxctrl-upload-settings' !== this.id ) {
                prx.markDirty();
            }
        } );

        form.on( 'input change', '.prxctrl-link-field input', function () {
            prx.markDirty();
        } );

        saveButton.on( 'click', function ( event ) {
            event.preventDefault();
            saveSettings();
        } );

        $( document ).on( 'keydown', function ( event ) {
            if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                saveSettings();
            }
        } );

        $( '#prxctrl-upload-settings' ).on( 'change', importSettings );
        $( '#prxctrl-reset-settings' ).on( 'click', resetSettings );

        $( 'label[for="prxctrl-upload-settings"]' ).on( 'keydown', function ( event ) {
            if ( 'Enter' === event.key || ' ' === event.key ) {
                event.preventDefault();
                $( '#prxctrl-upload-settings' ).trigger( 'click' );
            }
        } );

        initWidgetManager();
        initLinkFields();
        initApiKey();
    } );

} )( jQuery );

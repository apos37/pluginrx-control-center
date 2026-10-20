/**
 * PLUGINRX CONTROL CENTER: Dashboard
 *
 * Site checks (one at a time by default), actions, group actions, remote login, notes, ignored
 * warnings, display options, search, accordions and screenshot refreshes.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlDashboard || {};
    var prx = window.prxctrl;
    var i18n = config.i18n || {};
    var storageKey = 'prxctrl_collapsed';
    var groupStorageKey = 'prxctrl_collapsed_groups';
    var cancelled = false;
    var running = false;
    var displayTimer = null;


    /**
     * Read a list from localStorage.
     *
     * @param {string} key
     * @return {Array}
     */
    function readList( key ) {
        try {
            var value = JSON.parse( window.localStorage.getItem( key ) || '[]' );
            return Array.isArray( value ) ? value : [];
        } catch ( error ) {
            return [];
        }
    } // End readList()


    /**
     * Save a list to localStorage.
     *
     * @param {string} key
     * @param {Array} list
     */
    function writeList( key, list ) {
        try {
            window.localStorage.setItem( key, JSON.stringify( list ) );
        } catch ( error ) {
            return;
        }
    } // End writeList()


    /**
     * Collapse or expand a site card.
     *
     * @param {jQuery} card
     * @param {boolean} collapsed
     * @param {boolean} remember
     */
    function setCollapsed( card, collapsed, remember ) {
        var siteId = String( card.data( 'site-id' ) );
        var list = readList( storageKey );

        card.toggleClass( 'is-collapsed', collapsed );
        card.find( '.prxctrl-site-toggle' ).attr( 'aria-expanded', collapsed ? 'false' : 'true' );

        if ( ! remember ) {
            return;
        }

        list = list.filter( function ( id ) {
            return id !== siteId;
        } );

        if ( collapsed ) {
            list.push( siteId );
        }

        writeList( storageKey, list );
    } // End setCollapsed()


    /**
     * Restore saved accordion states.
     */
    function restoreCollapsed() {
        var collapsed = readList( storageKey );
        var groups = readList( groupStorageKey );

        $( '.prxctrl-site' ).each( function () {
            var card = $( this );

            if ( -1 !== collapsed.indexOf( String( card.data( 'site-id' ) ) ) ) {
                setCollapsed( card, true, false );
            }
        } );

        $( '.prxctrl-group' ).each( function () {
            var group = $( this );

            if ( -1 !== groups.indexOf( String( group.data( 'group-id' ) ) ) ) {
                setGroupCollapsed( group, true, false );
            }
        } );
    } // End restoreCollapsed()


    /**
     * Collapse or expand a group.
     *
     * @param {jQuery} group
     * @param {boolean} collapsed
     * @param {boolean} remember
     */
    function setGroupCollapsed( group, collapsed, remember ) {
        var groupId = String( group.data( 'group-id' ) );
        var list = readList( groupStorageKey );

        group.find( '.prxctrl-group-sites' ).prop( 'hidden', collapsed );
        group.find( '.prxctrl-group-toggle' ).attr( 'aria-expanded', collapsed ? 'false' : 'true' );

        if ( ! remember ) {
            return;
        }

        list = list.filter( function ( id ) {
            return id !== groupId;
        } );

        if ( collapsed ) {
            list.push( groupId );
        }

        writeList( groupStorageKey, list );
    } // End setGroupCollapsed()


    /**
     * Show a message inside a card.
     *
     * @param {jQuery} card
     * @param {string} message
     * @param {string} type success|error|info
     * @param {boolean} withLogs
     */
    function showMessage( card, message, type, withLogs ) {
        var box = card.find( '.prxctrl-site-message' );

        box.removeClass( 'is-error is-success' ).text( message || '' );

        if ( 'error' === type || 'success' === type ) {
            box.addClass( 'is-' + type );
        }

        if ( withLogs && config.logsUrl ) {
            box.append( ' ' ).append( $( '<a></a>' ).attr( 'href', config.logsUrl + '&site=' + encodeURIComponent( card.data( 'site-id' ) ) + '&status=error' ).text( i18n.viewLogs ) );
        }

        box.prop( 'hidden', ! message );
    } // End showMessage()


    /**
     * Swap a card for freshly rendered HTML, keeping its collapsed state.
     *
     * @param {jQuery} card
     * @param {string} html
     * @return {jQuery}
     */
    function replaceCard( card, html ) {
        var collapsed = card.hasClass( 'is-collapsed' );
        var fresh = $( $.parseHTML( html, document, false ) ).filter( '.prxctrl-site' );
        var oldThumb = card.find( '.prxctrl-site-thumbnail' );
        var oldImage = oldThumb.find( 'img' );

        if ( ! fresh.length ) {
            return card;
        }

        if ( oldImage.length && oldImage.attr( 'src' ) ) {
            fresh.find( '.prxctrl-site-thumbnail img' ).attr( 'src', oldImage.attr( 'src' ) );
            fresh.find( '.prxctrl-site-thumbnail' ).toggleClass( 'is-broken', oldThumb.hasClass( 'is-broken' ) );
        }

        card.replaceWith( fresh );
        setCollapsed( fresh, collapsed, false );
        applyDisplay( fresh );

        return fresh;
    } // End replaceCard()


    /**
     * Put a card into its checking state.
     *
     * @param {jQuery} card
     * @param {string} label
     */
    function startChecking( card, label ) {
        card.addClass( 'is-checking' ).attr( 'aria-busy', 'true' );
        card.find( '.prxctrl-site-buttons .prxctrl-button' ).prop( 'disabled', true );
        card.find( '.prxctrl-status-pill' ).removeClass( 'is-ok is-error is-never' ).addClass( 'is-checking' ).html( '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' + prx.escape( label || i18n.checkingSite ) );
        showMessage( card, '', '' );
    } // End startChecking()


    /**
     * Check one site.
     *
     * @param {jQuery} card
     * @param {string} successMessage Message to show after the check (used after actions).
     * @return {Promise}
     */
    function checkSite( card, successMessage ) {
        var deferred = $.Deferred();

        startChecking( card, successMessage ? i18n.rechecking : i18n.checkingSite );

        prx.post( 'prxctrl_check_site', { nonce: config.nonce, site_id: card.data( 'site-id' ) } ).done( function ( response ) {
            var data = response.data || {};
            var fresh = data.html ? replaceCard( card, data.html ) : card;

            if ( ! response.success ) {
                fresh.removeClass( 'is-checking' );
                showMessage( fresh, prx.message( response ), 'error', true );
                deferred.resolve( false );
                return;
            }

            if ( successMessage ) {
                showMessage( fresh, successMessage, 'success' );
            }

            refreshScreenshot( fresh );
            deferred.resolve( true );
        } ).fail( function ( xhr ) {
            card.removeClass( 'is-checking' ).removeAttr( 'aria-busy' );
            card.find( '.prxctrl-site-buttons .prxctrl-button' ).prop( 'disabled', false );
            showMessage( card, prx.message( xhr ), 'error', true );
            deferred.resolve( false );
        } );

        return deferred.promise();
    } // End checkSite()


    /**
     * Run a task over a list of cards, a few at a time.
     *
     * @param {Array} cards
     * @param {Function} task Receives a card and returns a promise of true/false.
     * @param {Function} onProgress Receives (done, total, card).
     * @return {Promise}
     */
    function runQueue( cards, task, onProgress ) {
        var deferred = $.Deferred();
        var limit = Math.max( 1, parseInt( config.concurrency, 10 ) || 1 );
        var next = 0;
        var done = 0;
        var errors = 0;
        var active = 0;

        function launch() {
            if ( cancelled && 0 === active ) {
                deferred.resolve( done, errors );
                return;
            }

            while ( ! cancelled && active < limit && next < cards.length ) {
                var card = cards[ next++ ];

                active++;
                onProgress( done, cards.length, card );

                task( card ).then( function ( ok ) {
                    active--;
                    done++;

                    if ( ! ok ) {
                        errors++;
                    }

                    if ( done === cards.length || ( cancelled && 0 === active ) ) {
                        deferred.resolve( done, errors );
                        return;
                    }

                    launch();
                } );
            }

            if ( 0 === cards.length ) {
                deferred.resolve( 0, 0 );
            }
        }

        launch();

        return deferred.promise();
    } // End runQueue()


    /**
     * Update the progress panel.
     *
     * @param {number} done
     * @param {number} total
     * @param {string} text
     */
    function setProgress( done, total, text ) {
        var panel = $( '#prxctrl-progress' );

        panel.prop( 'hidden', false ).removeClass( 'is-done' );
        panel.find( '.prxctrl-progress-text' ).text( text );
        panel.find( '.prxctrl-progress-fill' ).css( 'width', ( total ? Math.round( ( done / total ) * 100 ) : 0 ) + '%' );
    } // End setProgress()


    /**
     * Finish the progress panel.
     *
     * @param {string} text
     */
    function finishProgress( text ) {
        var panel = $( '#prxctrl-progress' );

        panel.addClass( 'is-done' );
        panel.find( '.dashicons' ).first().removeClass( 'dashicons-update spin' ).addClass( 'dashicons-yes-alt' );
        panel.find( '.prxctrl-progress-text' ).text( text );
        panel.find( '.prxctrl-progress-fill' ).css( 'width', '100%' );

        setTimeout( function () {
            if ( ! running ) {
                panel.prop( 'hidden', true );
                panel.find( '.dashicons' ).first().removeClass( 'dashicons-yes-alt' ).addClass( 'dashicons-update spin' );
            }
        }, 8000 );
    } // End finishProgress()


    /**
     * Check a list of cards with progress.
     *
     * @param {Array} cards
     * @return {Promise}
     */
    function checkMany( cards ) {
        running = true;
        cancelled = false;
        $( '#prxctrl-check-all' ).prop( 'disabled', true );
        $( '#prxctrl-check-all-cancel' ).prop( 'hidden', false );

        return runQueue( cards, function ( card ) {
            return checkSite( $( '#' + card.attr( 'id' ) ) );
        }, function ( done, total, card ) {
            setProgress( done, total, prx.format( i18n.progress, done + 1, total, card.data( 'name' ) ) );
        } ).then( function ( done, errors ) {
            running = false;
            $( '#prxctrl-check-all' ).prop( 'disabled', false );
            $( '#prxctrl-check-all-cancel' ).prop( 'hidden', true );
            finishProgress( cancelled ? i18n.cancelled + ' ' + prx.format( i18n.done, done, errors ) : prx.format( i18n.done, done, errors ) );
        } );
    } // End checkMany()


    /**
     * Run an action on a site, then check it again.
     *
     * @param {jQuery} card
     * @param {string} action
     * @param {string} waiting
     * @return {Promise}
     */
    function runAction( card, action, waiting ) {
        var deferred = $.Deferred();

        startChecking( card, waiting );

        prx.post( 'prxctrl_site_action', { nonce: config.nonce, site_id: card.data( 'site-id' ), type: action } ).done( function ( response ) {
            if ( ! response.success ) {
                card.removeClass( 'is-checking' ).removeAttr( 'aria-busy' );
                checkSite( card, '' ).then( function () {
                    showMessage( $( '#' + card.attr( 'id' ) ), prx.message( response ), 'error', true );
                    deferred.resolve( false );
                } );
                return;
            }

            checkSite( card, response.data.message ).then( function ( ok ) {
                deferred.resolve( ok );
            } );
        } ).fail( function ( xhr ) {
            card.removeClass( 'is-checking' ).removeAttr( 'aria-busy' );
            card.find( '.prxctrl-site-buttons .prxctrl-button' ).prop( 'disabled', false );
            showMessage( card, prx.message( xhr ), 'error', true );
            deferred.resolve( false );
        } );

        return deferred.promise();
    } // End runAction()


    /**
     * Poll until a fresh screenshot is ready, then show it.
     *
     * @param {jQuery} card
     */
    function refreshScreenshot( card ) {
        var url = card.data( 'screenshot' );
        var delays = [ 3000, 4000, 5000, 7000, 9000, 12000, 15000 ];
        var attempt = 0;

        if ( ! url || ! card.find( '.prxctrl-site-thumbnail' ).length || ! config.isHost ) {
            return;
        }

        function poll() {
            var current = $( '#' + card.attr( 'id' ) );

            if ( ! current.length || current.data( 'screenshot' ) !== url ) {
                return;
            }

            prx.post( 'prxctrl_screenshot_ready', { nonce: config.nonce, site_id: current.data( 'site-id' ) } ).done( function ( response ) {
                if ( response.success && response.data.ready ) {
                    var preload = new window.Image();

                    preload.onload = function () {
                        $( '#' + card.attr( 'id' ) ).find( '.prxctrl-site-thumbnail img' ).attr( 'src', response.data.url );
                    };
                    preload.referrerPolicy = 'no-referrer';
                    preload.src = response.data.url;
                    return;
                }

                if ( attempt < delays.length ) {
                    setTimeout( poll, delays[ attempt++ ] );
                }
            } );
        }

        setTimeout( poll, delays[ attempt++ ] );
    } // End refreshScreenshot()


    /**
     * Show or hide display keys in one card.
     *
     * @param {jQuery} scope
     */
    function applyDisplay( scope ) {
        var hidden = {};

        $( '.prxctrl-display-toggle' ).each( function () {
            hidden[ $( this ).val() ] = ! $( this ).is( ':checked' );
        } );

        scope.find( '[data-display]' ).each( function () {
            var element = $( this );
            var key = element.attr( 'data-display' );
            var group = element.attr( 'data-display-group' );

            if ( undefined === hidden[ key ] && ! group ) {
                return;
            }

            element.prop( 'hidden', !! hidden[ key ] || ( group ? !! hidden[ group ] : false ) );
        } );
    } // End applyDisplay()


    /**
     * Save the display options.
     */
    function saveDisplay() {
        var hidden = [];

        $( '.prxctrl-display-toggle' ).each( function () {
            if ( ! $( this ).is( ':checked' ) ) {
                hidden.push( $( this ).val() );
            }
        } );

        clearTimeout( displayTimer );
        displayTimer = setTimeout( function () {
            prx.post( 'prxctrl_display_options', { nonce: config.nonce, hidden: hidden.length ? hidden : [ '' ] } );
        }, 600 );
    } // End saveDisplay()


    /**
     * Filter plugins, themes or admins across every site.
     */
    function runSearch() {
        var term = $( '#prxctrl-search' ).val().trim().toLowerCase();
        var scope = $( '#prxctrl-search-scope' ).val();
        var results = $( '#prxctrl-search-results' );
        var lists = { on: [], off: [], unknown: [] };
        var total = 0;

        $( '.prxctrl-site' ).each( function () {
            var card = $( this );
            var rows = card.find( '.prxctrl-site-table[data-table="' + scope + '"] tbody tr' );
            var found = [];

            card.find( '.prxctrl-site-table tr' ).removeClass( 'is-match' );

            if ( '' === term ) {
                card.removeClass( 'is-search-miss' );

                if ( card.data( 'searchOpened' ) ) {
                    card.removeData( 'searchOpened' );
                    openTable( card, '' );
                }

                return;
            }

            rows.each( function () {
                if ( -1 !== $( this ).text().toLowerCase().indexOf( term ) ) {
                    $( this ).addClass( 'is-match' );
                    var cells = $( this ).children( 'td' );
                    var label = cells.first().text().trim();
                    var version = 'admin_users' === scope ? '' : cells.eq( 1 ).text().trim();

                    found.push( '' !== version ? label + ' v' + version.replace( /^v/i, '' ) : label );
                }
            } );

            total += found.length;
            card.toggleClass( 'is-search-miss', 0 === found.length );

            if ( ! rows.length ) {
                lists.unknown.push( { card: card, items: [] } );
            } else if ( found.length ) {
                lists.on.push( { card: card, items: found } );
                setCollapsed( card, false, false );
                openTable( card, scope );
                card.data( 'searchOpened', true );
            } else {
                lists.off.push( { card: card, items: [] } );
            }
        } );

        $( '#prxctrl-search-summary' ).text( '' === term ? '' : ( total ? prx.format( i18n.matches, total ) : i18n.noMatches ) );
        results.prop( 'hidden', '' === term );

        $.each( lists, function ( key, entries ) {
            var column = results.find( '[data-list="' + key + '"]' );
            var list = column.find( 'ul' ).empty();

            column.prop( 'hidden', 'unknown' === key && ! entries.length );
            column.find( '.count' ).text( '(' + entries.length + ')' );

            $.each( entries, function ( index, entry ) {
                var item = $( '<li></li>' );

                item.append( $( '<a href="#"></a>' ).attr( 'data-jump', entry.card.attr( 'id' ) ).text( entry.card.data( 'name' ) ) );

                if ( entry.items.length ) {
                    item.append( $( '<span class="prxctrl-muted"></span>' ).text( ' — ' + entry.items.join( ', ' ) ) );
                }

                list.append( item );
            } );
        } );
    } // End runSearch()


    /**
     * Scroll to a site card, opening it and its group.
     *
     * @param {string} id
     */
    function jumpTo( id ) {
        var card = id ? $( '#' + id ) : $();

        if ( ! card.length ) {
            return;
        }

        setGroupCollapsed( card.closest( '.prxctrl-group' ), false, true );
        setCollapsed( card, false, true );
        $( 'html, body' ).animate( { scrollTop: card.offset().top - 110 }, 300 );
        card.addClass( 'is-highlight' );

        setTimeout( function () {
            card.removeClass( 'is-highlight' );
        }, 2000 );
    } // End jumpTo()


    /**
     * Open one of a card's tables and close the others.
     *
     * @param {jQuery} card
     * @param {string} table
     */
    function openTable( card, table ) {
        card.find( '.prxctrl-site-table' ).each( function () {
            $( this ).prop( 'hidden', $( this ).data( 'table' ) !== table );
        } );

        card.find( '.prxctrl-table-toggle' ).each( function () {
            $( this ).attr( 'aria-expanded', $( this ).data( 'table' ) === table ? 'true' : 'false' );
        } );
    } // End openTable()


    /**
     * Mark a screenshot that failed to load so a placeholder shows instead of the alt text.
     *
     * @param {Element} image
     * @param {boolean} broken
     */
    function markScreenshot( image, broken ) {
        $( image ).closest( '.prxctrl-site-thumbnail' ).toggleClass( 'is-broken', broken );
    } // End markScreenshot()


    document.addEventListener( 'error', function ( event ) {
        if ( event.target && event.target.matches && event.target.matches( '.prxctrl-site-thumbnail img' ) ) {
            markScreenshot( event.target, true );
        }
    }, true );

    document.addEventListener( 'load', function ( event ) {
        if ( event.target && event.target.matches && event.target.matches( '.prxctrl-site-thumbnail img' ) ) {
            markScreenshot( event.target, false );
        }
    }, true );


    $( function () {
        restoreCollapsed();

        $( '.prxctrl-site-thumbnail img' ).each( function () {
            if ( this.complete && 0 === this.naturalWidth ) {
                markScreenshot( this, true );
            }
        } );

        $( document ).on( 'click', '.prxctrl-site-toggle', function () {
            var card = $( this ).closest( '.prxctrl-site' );
            setCollapsed( card, ! card.hasClass( 'is-collapsed' ), true );
        } );

        $( document ).on( 'click', '.prxctrl-group-toggle', function () {
            var group = $( this ).closest( '.prxctrl-group' );
            setGroupCollapsed( group, ! group.find( '.prxctrl-group-sites' ).prop( 'hidden' ), true );
        } );

        $( '#prxctrl-expand-all, #prxctrl-collapse-all' ).on( 'click', function () {
            var collapse = 'prxctrl-collapse-all' === this.id;

            $( '.prxctrl-site' ).each( function () {
                $( this ).removeData( 'searchOpened' );
                openTable( $( this ), '' );
                setCollapsed( $( this ), collapse, true );
            } );

            $( '.prxctrl-group' ).each( function () {
                setGroupCollapsed( $( this ), false, true );
            } );
        } );

        $( '#prxctrl-jump' ).on( 'change', function () {
            jumpTo( $( this ).val() );
            $( this ).val( '' );
        } );

        $( '#prxctrl-search-results' ).on( 'click', 'a[data-jump]', function ( event ) {
            event.preventDefault();
            jumpTo( $( this ).attr( 'data-jump' ) );
        } );

        $( document ).on( 'click', '.prxctrl-table-toggle', function () {
            var card = $( this ).closest( '.prxctrl-site' );
            var table = $( this ).data( 'table' );
            var open = 'true' === $( this ).attr( 'aria-expanded' );

            card.removeData( 'searchOpened' );
            openTable( card, open ? '' : table );
        } );

        $( document ).on( 'click', '.prxctrl-check-site-button', function () {
            var card = $( this ).closest( '.prxctrl-site' );

            if ( ! config.isHost ) {
                startChecking( card );
                prx.post( 'prxctrl_check_site', { nonce: config.nonce } ).done( function ( response ) {
                    if ( response.success ) {
                        replaceCard( card, response.data.html );
                    }
                } );
                return;
            }

            checkSite( card );
        } );

        $( '#prxctrl-check-all' ).on( 'click', function () {
            checkMany( $( '.prxctrl-site' ).toArray().map( function ( element ) {
                return $( element );
            } ) );
        } );

        $( '#prxctrl-check-all-cancel' ).on( 'click', function () {
            cancelled = true;
            $( this ).prop( 'hidden', true );
        } );

        $( document ).on( 'click', '.prxctrl-action-button', function () {
            var button = $( this );
            var card = button.closest( '.prxctrl-site' );

            if ( 'true' === button.attr( 'aria-disabled' ) ) {
                prx.toast( button.attr( 'data-reason' ) || i18n.confirm, 'info' );
                return;
            }

            if ( ! window.confirm( prx.format( i18n.confirmAction, button.text().trim() ) ) ) {
                return;
            }

            runAction( card, button.data( 'action' ), button.data( 'wait' ) );
        } );

        function showLoginWait( popup ) {
            var doc = popup.document;
            var wrap = doc.createElement( 'div' );
            var text = doc.createElement( 'p' );

            doc.title = i18n.loginWaitTitle;
            doc.body.style.cssText = 'margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#344054;background:#f9fafb;';
            wrap.style.cssText = 'text-align:center;padding:24px;';
            text.textContent = i18n.loginWait;
            wrap.appendChild( text );
            doc.body.appendChild( wrap );
        }

        $( document ).on( 'click', '.prxctrl-login-button', function () {
            var button = $( this );
            var card = button.closest( '.prxctrl-site' );

            if ( 'true' === button.attr( 'aria-disabled' ) ) {
                prx.toast( button.attr( 'data-reason' ), 'info' );
                return;
            }

            var popup = window.open( 'about:blank', '_blank' );

            if ( popup ) {
                showLoginWait( popup );
                popup.opener = null;
            }

            prx.busy( button, i18n.loginOpening );

            prx.post( 'prxctrl_site_login', { nonce: config.nonce, site_id: card.data( 'site-id' ) } ).done( function ( response ) {
                prx.unbusy( button );

                if ( response.success ) {
                    if ( popup ) {
                        popup.location.href = response.data.url;
                    } else {
                        window.location.href = response.data.url;
                    }
                    return;
                }

                if ( popup ) {
                    popup.close();
                }

                showMessage( card, prx.message( response ), 'error', true );
            } ).fail( function ( xhr ) {
                prx.unbusy( button );

                if ( popup ) {
                    popup.close();
                }

                showMessage( card, prx.message( xhr ), 'error', true );
            } );
        } );

        $( document ).on( 'click', '.prxctrl-notes-edit', function () {
            var notes = $( this ).closest( '.prxctrl-notes' );

            notes.addClass( 'is-editing' );
            notes.find( '.prxctrl-notes-editor' ).prop( 'hidden', false ).find( 'textarea' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.prxctrl-notes-cancel', function () {
            var notes = $( this ).closest( '.prxctrl-notes' );

            notes.removeClass( 'is-editing' );
            notes.find( '.prxctrl-notes-editor' ).prop( 'hidden', true );
        } );

        $( document ).on( 'click', '.prxctrl-notes-save', function () {
            var button = $( this );
            var card = button.closest( '.prxctrl-site' );

            prx.busy( button, '...' );

            prx.post( 'prxctrl_save_notes', { nonce: config.nonce, site_id: card.data( 'site-id' ), notes: button.closest( '.prxctrl-notes' ).find( 'textarea' ).val() } ).done( function ( response ) {
                if ( response.success ) {
                    replaceCard( card, response.data.html );
                    prx.toast( i18n.notesSaved, 'success' );
                    return;
                }

                prx.unbusy( button );
                prx.toast( prx.message( response ), 'error' );
            } ).fail( function ( xhr ) {
                prx.unbusy( button );
                prx.toast( prx.message( xhr ), 'error' );
            } );
        } );

        $( document ).on( 'click', '.prxctrl-ignore-warning', function () {
            var button = $( this );
            var card = button.closest( '.prxctrl-site' );

            button.prop( 'disabled', true );

            prx.post( 'prxctrl_ignore_warning', { nonce: config.nonce, site_id: card.data( 'site-id' ), widget: button.data( 'widget' ), ignore: '1' === String( button.data( 'ignored' ) ) ? 0 : 1 } ).done( function ( response ) {
                if ( response.success ) {
                    replaceCard( card, response.data.html );
                    return;
                }

                button.prop( 'disabled', false );
                prx.toast( prx.message( response ), 'error' );
            } ).fail( function ( xhr ) {
                button.prop( 'disabled', false );
                prx.toast( prx.message( xhr ), 'error' );
            } );
        } );

        $( '.prxctrl-display-toggle' ).on( 'change', function () {
            applyDisplay( $( '.prxctrl-sites' ) );
            saveDisplay();
        } );

        var searchTimer = null;

        $( '#prxctrl-search' ).on( 'input', function () {
            clearTimeout( searchTimer );
            searchTimer = setTimeout( runSearch, 250 );
        } );

        $( '#prxctrl-search-scope' ).on( 'change', runSearch );

        $( document ).on( 'click', '.prxctrl-group-run', function () {
            var group = $( this ).closest( '.prxctrl-group' );
            var select = group.find( '.prxctrl-group-action' );
            var action = select.val();
            var label = select.find( 'option:selected' ).text();
            var cards = [];

            if ( ! action || running ) {
                return;
            }

            group.find( '.prxctrl-site' ).each( function () {
                var card = $( this );

                if ( 'check_site' === action ) {
                    cards.push( card );
                    return;
                }

                var button = card.find( '.prxctrl-action-button[data-action="' + action + '"]' );

                if ( button.length && 'true' !== button.attr( 'aria-disabled' ) ) {
                    cards.push( card );
                }
            } );

            if ( ! cards.length ) {
                prx.toast( i18n.noGroupSites, 'info' );
                return;
            }

            if ( ! window.confirm( prx.format( i18n.confirmGroup, label, cards.length ) ) ) {
                return;
            }

            if ( 'check_site' === action ) {
                checkMany( cards );
                return;
            }

            running = true;
            cancelled = false;
            $( '#prxctrl-check-all-cancel' ).prop( 'hidden', false );

            runQueue( cards, function ( card ) {
                var current = $( '#' + card.attr( 'id' ) );
                return runAction( current, action, current.find( '.prxctrl-action-button[data-action="' + action + '"]' ).data( 'wait' ) );
            }, function ( done, total, card ) {
                setProgress( done, total, prx.format( i18n.progress, done + 1, total, card.data( 'name' ) ) + ' (' + label + ')' );
            } ).then( function ( done, errors ) {
                running = false;
                $( '#prxctrl-check-all-cancel' ).prop( 'hidden', true );
                finishProgress( prx.format( i18n.done, done, errors ) );
            } );
        } );
    } );

} )( jQuery );

/**
 * PLUGINRX CONTROL CENTER: Sites
 *
 * Add and edit sites and groups in modals, drag to reorder (custom sorting only) and check
 * connections. Every change is saved right away; if saving fails, the table goes back to how it was.
 */

( function ( $ ) {
    'use strict';

    var config = window.prxctrlSites || {};
    var prx = window.prxctrl;
    var i18n = config.i18n || {};
    var newCount = 0;
    var editingRow = null;
    var editingGroup = null;
    var dragStart = null;


    /**
     * Get the table.
     *
     * @return {jQuery}
     */
    function table() {
        return $( '#prxctrl-sites-table' );
    } // End table()


    /**
     * Get the tbody for a group.
     *
     * @param {string} groupId
     * @return {jQuery}
     */
    function groupBody( groupId ) {
        return table().find( 'tbody.prxctrl-group-body' ).filter( function () {
            return String( $( this ).attr( 'data-group-id' ) ) === String( groupId );
        } );
    } // End groupBody()


    /**
     * Get the groups currently on the page.
     *
     * @return {Array}
     */
    function groups() {
        var list = [];

        table().find( 'tbody.prxctrl-group-body' ).each( function () {
            var id = String( $( this ).attr( 'data-group-id' ) );

            if ( '0' !== id ) {
                list.push( { id: id, name: $( this ).attr( 'data-name' ) } );
            }
        } );

        return list;
    } // End groups()


    /**
     * Read a row's link values.
     *
     * @param {jQuery} row
     * @return {Object}
     */
    function rowLinks( row ) {
        try {
            var links = JSON.parse( row.attr( 'data-links' ) || '{}' );
            return links && 'object' === typeof links ? links : {};
        } catch ( error ) {
            return {};
        }
    } // End rowLinks()


    /**
     * Refresh the per-group counts, the view counts and the empty state.
     */
    function refreshCounts() {
        var counts = { all: 0, ok: 0, error: 0, never: 0 };

        table().find( 'tbody.prxctrl-group-body' ).each( function () {
            var body = $( this );
            var rows = body.find( 'tr.prxctrl-site-row' );

            body.find( '.prxctrl-group-count' ).text( rows.length );
            body.toggleClass( 'is-empty', 0 === rows.length );

            rows.each( function () {
                var status = $( this ).attr( 'data-status' ) || 'never';

                counts.all++;
                counts[ status ] = ( counts[ status ] || 0 ) + 1;
            } );
        } );

        $( '.prxctrl-status-views a' ).each( function () {
            $( this ).find( '.prxctrl-view-count' ).text( counts[ $( this ).data( 'view' ) ] || 0 );
        } );
    } // End refreshCounts()


    /**
     * Sort rows alphabetically inside a tbody.
     *
     * @param {jQuery} body
     */
    function sortRows( body ) {
        var rows = body.find( 'tr.prxctrl-site-row' ).get();

        rows.sort( function ( a, b ) {
            return String( $( a ).find( '.prxctrl-row-name' ).text() ).localeCompare( String( $( b ).find( '.prxctrl-row-name' ).text() ), undefined, { sensitivity: 'base' } );
        } );

        $.each( rows, function ( index, row ) {
            body.append( row );
        } );
    } // End sortRows()


    /**
     * Sort group bodies alphabetically, keeping "No Group" first.
     */
    function sortGroups() {
        var bodies = table().find( 'tbody.prxctrl-group-body' ).not( '[data-group-id="0"]' ).get();

        bodies.sort( function ( a, b ) {
            return String( $( a ).attr( 'data-name' ) ).localeCompare( String( $( b ).attr( 'data-name' ) ), undefined, { sensitivity: 'base' } );
        } );

        $.each( bodies, function ( index, body ) {
            table().append( body );
        } );
    } // End sortGroups()


    /**
     * Put a row into a group in the right position.
     *
     * @param {jQuery} row
     * @param {string} groupId
     */
    function placeRow( row, groupId ) {
        var body = groupBody( groupId );

        if ( ! body.length ) {
            body = groupBody( '0' );
        }

        row.attr( 'data-group', groupId );

        if ( ! row.parent().is( body ) ) {
            body.append( row );
        }

        if ( ! config.custom ) {
            sortRows( body );
        }
    } // End placeRow()


    /**
     * Build or update a row's cells from its data.
     *
     * @param {jQuery} row
     */
    function paintRow( row ) {
        var name = row.attr( 'data-name' ) || row.attr( 'data-url' );
        var links = $( '<div class="row-actions prxctrl-quick-links"></div>' );
        var loginUrl = row.attr( 'data-login' ) || ( String( row.attr( 'data-url' ) ).replace( /\/$/, '' ) + '/wp-login.php' );
        var values = rowLinks( row );
        var items = [ { label: i18n.login, url: loginUrl } ];

        $.each( config.linkFields || [], function ( index, field ) {
            if ( values[ field.slug ] ) {
                items.push( { label: field.label, url: values[ field.slug ] } );
            }
        } );

        $.each( items, function ( index, item ) {
            var span = $( '<span></span>' ).append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', item.url ).text( item.label ) );

            if ( index < items.length - 1 ) {
                span.append( ' | ' );
            }

            links.append( span );
        } );

        row.find( '.prxctrl-row-name' ).text( name );
        row.find( '.prxctrl-quick-links' ).replaceWith( links );
        row.find( '.prxctrl-row-url' ).empty().append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', row.attr( 'data-url' ) ).text( row.attr( 'data-url' ) ) );
    } // End paintRow()


    /**
     * Create a new, unsaved row.
     *
     * @return {jQuery}
     */
    function createRow() {
        var handle = config.custom ? '<td class="column-handle"><span class="dashicons dashicons-menu prxctrl-site-handle"></span></td>' : '';
        var row = $( '<tr class="prxctrl-site-row is-new" data-status="never" data-has-key="0" data-local="0">' +
            handle +
            '<td class="column-primary"><strong class="prxctrl-row-name"></strong><div class="row-actions prxctrl-quick-links"></div></td>' +
            '<td class="prxctrl-row-url"></td>' +
            '<td class="column-connection"><span class="prxctrl-connection prxctrl-connection-never" aria-live="polite"><span class="dashicons dashicons-marker" aria-hidden="true"></span> <span class="prxctrl-connection-text"></span></span></td>' +
            '<td class="column-checked"><span class="prxctrl-muted"></span></td>' +
            '<td class="column-actions">' +
                '<button type="button" class="button-link prxctrl-edit-site"></button> <span aria-hidden="true">|</span> ' +
                '<button type="button" class="button-link button-link-delete prxctrl-remove-site"></button> <span aria-hidden="true">|</span> ' +
                '<button type="button" class="button-link prxctrl-check-connection"></button>' +
            '</td></tr>' );
        var template = table().find( 'tr.prxctrl-site-row' ).first();

        if ( template.length ) {
            template.children( 'td[data-colname]' ).each( function () {
                row.children( 'td.' + this.className.split( ' ' )[ 0 ] ).attr( 'data-colname', $( this ).attr( 'data-colname' ) );
            } );
        }

        row.attr( 'data-id', 'new' + ( ++newCount ) );
        row.find( '.prxctrl-connection-text' ).text( i18n.unsavedRow );
        row.find( '.column-checked .prxctrl-muted' ).text( '—' );
        row.find( '.prxctrl-edit-site' ).text( i18n.edit );
        row.find( '.prxctrl-remove-site' ).text( i18n.remove );
        row.find( '.prxctrl-check-connection' ).text( i18n.checkConnection );

        return row;
    } // End createRow()


    /**
     * Fill the group select.
     *
     * @param {jQuery} select
     * @param {string} current
     */
    function fillGroupSelect( select, current ) {
        select.empty().append( $( '<option value="0"></option>' ).text( i18n.noGroup ) );

        $.each( groups(), function ( index, group ) {
            select.append( $( '<option></option>' ).val( group.id ).text( group.name ) );
        } );

        select.append( $( '<option value="__new"></option>' ).text( i18n.newGroup ) );
        select.val( current || '0' );
        $( '#prxctrl-site-new-group' ).val( '' ).prop( 'hidden', true );
    } // End fillGroupSelect()


    /**
     * Show an error in a modal.
     *
     * @param {jQuery} modal
     * @param {string} message
     */
    function modalError( modal, message ) {
        modal.find( '.prxctrl-modal-error' ).text( message ).prop( 'hidden', ! message );
    } // End modalError()


    /**
     * Open the site modal.
     *
     * @param {jQuery|null} row
     */
    function openSiteModal( row ) {
        var modal = $( '#prxctrl-site-modal' );
        var isNew = ! row;
        var local = row && '1' === String( row.attr( 'data-local' ) );
        var values = row ? rowLinks( row ) : {};
        var fields = $( '#prxctrl-site-link-fields' ).empty();

        editingRow = row;

        modal.find( '#prxctrl-site-modal-title' ).text( isNew ? i18n.addSite : i18n.editSite );
        modal.find( '#prxctrl-site-modal-submit' ).text( isNew ? i18n.add : i18n.update );
        modal.find( '#prxctrl-site-name' ).val( row ? row.attr( 'data-name' ) : '' );
        modal.find( '#prxctrl-site-url' ).val( row ? row.attr( 'data-url' ) : '' ).prop( 'readonly', !! local );
        modal.find( '#prxctrl-site-key' ).val( row && row.data( 'key' ) ? row.data( 'key' ) : '' ).attr( 'type', 'password' );
        modal.find( '#prxctrl-toggle-key' ).text( i18n.show );
        modal.find( '.prxctrl-key-field' ).prop( 'hidden', !! local );
        modal.find( '#prxctrl-site-key-help' ).text( row && '1' === String( row.attr( 'data-has-key' ) ) ? i18n.keySaved : i18n.keyRequired );
        modal.find( '#prxctrl-site-login' ).val( row ? row.attr( 'data-login' ) : '' );
        modal.find( '#prxctrl-modal-check-result' ).empty();
        fillGroupSelect( modal.find( '#prxctrl-site-group' ), row ? row.attr( 'data-group' ) : '0' );
        modalError( modal, '' );

        $.each( config.linkFields || [], function ( index, field ) {
            var id = 'prxctrl-site-link-' + field.slug;
            var wrap = $( '<div class="prxctrl-field"></div>' );

            wrap.append( $( '<label></label>' ).attr( 'for', id ).text( field.label ) );
            wrap.append( $( '<input type="url" class="prxctrl-site-link" autocomplete="off" placeholder="https://">' ).attr( 'id', id ).attr( 'data-slug', field.slug ).val( values[ field.slug ] || '' ) );
            fields.append( wrap );
        } );

        prx.openModal( modal );
    } // End openSiteModal()


    /**
     * Check that a URL looks valid.
     *
     * @param {string} url
     * @return {boolean}
     */
    function validUrl( url ) {
        return /^https?:\/\/[^\s/$.?#][^\s]*$/i.test( url );
    } // End validUrl()


    /**
     * Normalize a URL the way the server does (no trailing slash).
     *
     * @param {string} url
     * @return {string}
     */
    function normalizeUrl( url ) {
        url = String( url ).trim();

        if ( url && ! /^https?:\/\//i.test( url ) ) {
            url = 'https://' + url;
        }

        return url.replace( /\/+$/, '' );
    } // End normalizeUrl()


    /**
     * Save the site modal into the table.
     */
    function submitSiteModal() {
        var modal = $( '#prxctrl-site-modal' );
        var row = editingRow;
        var local = row && '1' === String( row.attr( 'data-local' ) );
        var url = local ? row.attr( 'data-url' ) : normalizeUrl( modal.find( '#prxctrl-site-url' ).val() );
        var key = modal.find( '#prxctrl-site-key' ).val().trim().toLowerCase();
        var hasKey = row && '1' === String( row.attr( 'data-has-key' ) );
        var duplicate = false;
        var links = {};

        if ( ! validUrl( url ) ) {
            modalError( modal, i18n.urlRequired );
            return;
        }

        table().find( 'tr.prxctrl-site-row' ).each( function () {
            if ( ( ! row || ! $( this ).is( row ) ) && $( this ).attr( 'data-url' ) === url ) {
                duplicate = true;
            }
        } );

        if ( duplicate ) {
            modalError( modal, i18n.urlDuplicate );
            return;
        }

        if ( ! local && key && ! /^[a-f0-9]{64}$/.test( key ) ) {
            modalError( modal, i18n.keyInvalid );
            return;
        }

        if ( ! local && ! key && ! hasKey && ! ( row && row.data( 'key' ) ) ) {
            modalError( modal, i18n.keyRequired );
            return;
        }

        modal.find( '.prxctrl-site-link' ).each( function () {
            var value = $( this ).val().trim();

            if ( value ) {
                links[ $( this ).data( 'slug' ) ] = value;
            }
        } );

        var groupId = modal.find( '#prxctrl-site-group' ).val();
        var newGroup = modal.find( '#prxctrl-site-new-group' ).val().trim();

        if ( '__new' === groupId && ! newGroup ) {
            modalError( modal, i18n.groupRequired );
            return;
        }

        var before = snapshot();

        if ( '__new' === groupId ) {
            groupId = String( createGroupBody( 'newg' + ( ++newCount ), newGroup ).attr( 'data-group-id' ) );

            if ( ! config.custom ) {
                sortGroups();
            }
        }

        if ( ! row ) {
            row = createRow();
        }

        row.attr( 'data-name', modal.find( '#prxctrl-site-name' ).val().trim() );
        row.attr( 'data-url', url );
        row.attr( 'data-login', modal.find( '#prxctrl-site-login' ).val().trim() );
        row.attr( 'data-links', JSON.stringify( links ) );

        if ( key ) {
            row.data( 'key', key );
        }

        paintRow( row );
        placeRow( row, groupId );
        submitChange( modal, before, String( row.attr( 'data-id' ) ), i18n.savedSite );
    } // End submitSiteModal()


    /**
     * Open the group modal.
     *
     * @param {jQuery|null} body
     */
    function openGroupModal( body ) {
        var modal = $( '#prxctrl-group-modal' );
        var list = $( '#prxctrl-group-sites' ).empty();
        var groupId = body ? String( body.attr( 'data-group-id' ) ) : '';

        editingGroup = body;

        modal.find( '#prxctrl-group-modal-title' ).text( body ? i18n.editGroup : i18n.addGroup );
        modal.find( '#prxctrl-group-modal-submit' ).text( body ? i18n.update : i18n.add );
        modal.find( '#prxctrl-group-name' ).val( body ? body.attr( 'data-name' ) : '' );
        modalError( modal, '' );

        table().find( 'tr.prxctrl-site-row' ).each( function () {
            var row = $( this );
            var label = $( '<label></label>' );
            var box = $( '<input type="checkbox">' ).val( row.attr( 'data-id' ) ).prop( 'checked', groupId && String( row.attr( 'data-group' ) ) === groupId );

            label.append( box ).append( ' ' ).append( document.createTextNode( row.find( '.prxctrl-row-name' ).text() ) );
            list.append( label );
        } );

        prx.openModal( modal );
    } // End openGroupModal()


    /**
     * Create a group tbody.
     *
     * @param {string} id
     * @param {string} name
     * @return {jQuery}
     */
    function createGroupBody( id, name ) {
        var columns = table().find( 'thead th' ).length;
        var body = $( '<tbody class="prxctrl-group-body"><tr class="prxctrl-group-row"></tr></tbody>' );
        var headerRow = body.find( 'tr' );

        body.attr( 'data-group-id', id ).attr( 'data-name', name );

        if ( config.custom ) {
            headerRow.append( '<td class="column-handle"><span class="dashicons dashicons-menu prxctrl-group-handle"></span></td>' );
        }

        var cell = $( '<td></td>' ).attr( 'colspan', columns - ( config.custom ? 2 : 1 ) );

        cell.append( '<span class="dashicons dashicons-category" aria-hidden="true"></span> ' );
        cell.append( $( '<strong class="prxctrl-group-label"></strong>' ).text( name ) );
        cell.append( ' <span class="count">(<span class="prxctrl-group-count">0</span>)</span>' );
        headerRow.append( cell );

        var actions = $( '<td class="column-actions"></td>' );

        actions.append( $( '<button type="button" class="button-link prxctrl-edit-group"></button>' ).text( i18n.edit ) );
        actions.append( ' <span aria-hidden="true">|</span> ' );
        actions.append( $( '<button type="button" class="button-link button-link-delete prxctrl-remove-group"></button>' ).text( i18n.deleteGroup ) );
        headerRow.append( actions );
        table().append( body );

        return body;
    } // End createGroupBody()


    /**
     * Save the group modal into the table.
     */
    function submitGroupModal() {
        var modal = $( '#prxctrl-group-modal' );
        var name = modal.find( '#prxctrl-group-name' ).val().trim();
        var body = editingGroup;

        if ( ! name ) {
            modalError( modal, i18n.nameRequired );
            return;
        }

        var before = snapshot();

        if ( ! body ) {
            body = createGroupBody( 'newg' + ( ++newCount ), name );
        }

        body.attr( 'data-name', name ).find( '.prxctrl-group-label' ).text( name );

        var groupId = String( body.attr( 'data-group-id' ) );

        modal.find( '#prxctrl-group-sites input' ).each( function () {
            var row = table().find( 'tr.prxctrl-site-row' ).filter( '[data-id="' + $( this ).val() + '"]' );

            if ( $( this ).is( ':checked' ) ) {
                placeRow( row, groupId );
            } else if ( String( row.attr( 'data-group' ) ) === groupId ) {
                placeRow( row, '0' );
            }
        } );

        if ( ! config.custom ) {
            sortGroups();
        }

        submitChange( modal, before, '', i18n.savedGroup );
    } // End submitGroupModal()


    /**
     * Turn on drag and drop for custom sorting.
     */
    function initSortable() {
        if ( ! config.custom || ! $.fn.sortable ) {
            return;
        }

        var bodies = table().find( 'tbody.prxctrl-group-body' );

        bodies.filter( '.ui-sortable' ).sortable( 'destroy' );

        bodies.sortable( {
            items: '> tr.prxctrl-site-row',
            handle: '.prxctrl-site-handle',
            connectWith: '#prxctrl-sites-table tbody.prxctrl-group-body',
            axis: 'y',
            placeholder: 'prxctrl-row-placeholder',
            helper: function ( event, row ) {
                row.children().each( function () {
                    $( this ).width( $( this ).width() );
                } );

                return row;
            },
            stop: function ( event, ui ) {
                ui.item.children().css( 'width', '' );
                ui.item.attr( 'data-group', ui.item.closest( 'tbody' ).attr( 'data-group-id' ) );
                stopDrag();
            }
        } );

        if ( table().hasClass( 'ui-sortable' ) ) {
            table().sortable( 'destroy' );
        }

        table().sortable( {
            items: '> tbody.prxctrl-group-body:not([data-group-id="0"])',
            handle: '.prxctrl-group-handle',
            axis: 'y',
            stop: stopDrag
        } );
    } // End initSortable()


    /**
     * Get the current order of groups and sites.
     *
     * @return {string}
     */
    function orderKey() {
        return table().find( 'tbody.prxctrl-group-body' ).map( function () {
            return $( this ).attr( 'data-group-id' ) + ':' + $( this ).find( 'tr.prxctrl-site-row' ).map( function () {
                return $( this ).attr( 'data-id' );
            } ).get().join( ',' );
        } ).get().join( '|' );
    } // End orderKey()


    /**
     * Remember the table before a drag. Runs on mousedown, before jQuery UI adds its placeholder.
     */
    function startDrag() {
        dragStart = { html: snapshot(), order: orderKey() };
    } // End startDrag()


    /**
     * Save after a drag that changed the order.
     */
    function stopDrag() {
        if ( ! dragStart || orderKey() === dragStart.order ) {
            return;
        }

        var before = dragStart.html;

        dragStart = null;
        refreshCounts();
        saveChange( before, i18n.savedOrder );
    } // End stopDrag()


    /**
     * Show a connection result in a row.
     *
     * @param {jQuery} target
     * @param {string} state busy|ok|error
     * @param {string} message
     * @param {string} logsUrl
     */
    function showConnection( target, state, message, logsUrl ) {
        var icon = 'busy' === state ? '<span class="dashicons dashicons-update spin" aria-hidden="true"></span>' : ( 'ok' === state ? prx.checkMark() : prx.crossMark() );
        var result = $( '<span class="prxctrl-result"></span>' ).addClass( 'is-' + state ).html( icon );

        result.append( $( '<span></span>' ).text( 'error' === state ? i18n.failed : message ) );

        if ( 'error' === state ) {
            result.attr( 'title', message );
        }

        target.empty().append( result );

        if ( 'error' === state ) {
            target.append( $( '<div class="prxctrl-connection-error"></div>' ).text( message ) );

            if ( logsUrl ) {
                target.append( $( '<a class="prxctrl-log-link"></a>' ).attr( 'href', logsUrl ).text( i18n.viewLogs ) );
            }
        }
    } // End showConnection()


    /**
     * Check one row's connection.
     *
     * @param {jQuery} row
     * @return {Promise}
     */
    function checkRow( row ) {
        var deferred = $.Deferred();
        var target = row.find( '.prxctrl-connection' );
        var id = String( row.attr( 'data-id' ) );
        var data = { nonce: config.nonce, url: row.attr( 'data-url' ), api_key: row.data( 'key' ) || '' };

        if ( /^\d+$/.test( id ) ) {
            data.site_id = id;
        }

        showConnection( target, 'busy', i18n.checking );

        prx.post( 'prxctrl_check_connection', data ).done( function ( response ) {
            if ( response.success ) {
                showConnection( target, 'ok', response.data.message );
                row.attr( 'data-status', 'ok' );
            } else {
                showConnection( target, 'error', prx.message( response ), response.data && response.data.logs );
                row.attr( 'data-status', 'error' );
            }

            refreshCounts();
            deferred.resolve();
        } ).fail( function ( xhr ) {
            showConnection( target, 'error', prx.message( xhr ), config.logsUrl );
            deferred.resolve();
        } );

        return deferred.promise();
    } // End checkRow()


    /**
     * Copy the table body so a failed save can put it back.
     *
     * @return {string}
     */
    function snapshot() {
        return table().children( 'tbody' ).map( function () {
            return this.outerHTML;
        } ).get().join( '' );
    } // End snapshot()


    /**
     * Put the table body back.
     *
     * @param {string} html
     */
    function restore( html ) {
        table().children( 'tbody' ).remove();
        table().append( $.parseHTML( html, document, false ) );
        initSortable();
        refreshCounts();
    } // End restore()


    /**
     * Update the Check All Connections button.
     *
     * @param {number} count
     * @param {string} label
     */
    function updateCheckAll( count, label ) {
        var button = $( '#prxctrl-check-connections' );

        button.prop( 'disabled', ! count );

        if ( label ) {
            button.find( '.prxctrl-check-connections-label' ).text( label );
        }
    } // End updateCheckAll()


    /**
     * Save the whole table.
     *
     * @return {Promise} Resolves with { ok, errors, message }.
     */
    function save() {
        var deferred = $.Deferred();
        var payload = { groups: groups(), sites: [] };

        table().find( 'tr.prxctrl-site-row' ).removeAttr( 'aria-invalid' ).each( function () {
            var row = $( this );

            payload.sites.push( {
                id: row.attr( 'data-id' ),
                name: row.attr( 'data-name' ) || '',
                url: row.attr( 'data-url' ),
                api_key: row.data( 'key' ) || '',
                group: row.closest( 'tbody' ).attr( 'data-group-id' ),
                login_url: row.attr( 'data-login' ) || '',
                links: rowLinks( row )
            } );
        } );

        prx.startSavingTitle();

        prx.post( 'prxctrl_save_sites', { nonce: config.nonce, payload: JSON.stringify( payload ) } ).done( function ( response ) {
            var data = response.data || {};

            prx.stopSavingTitle();

            if ( response.success && data.html ) {
                table().children( 'tbody' ).remove();
                table().append( $.parseHTML( data.html, document, false ) );
                initSortable();
                refreshCounts();
                updateCheckAll( data.count, data.label );
            }

            deferred.resolve( { ok: !! response.success, errors: data.errors || {}, message: prx.message( response ), html: data.html || '' } );
        } ).fail( function ( xhr ) {
            prx.stopSavingTitle();
            deferred.resolve( { ok: false, errors: {}, message: prx.message( xhr ), html: '' } );
        } );

        return deferred.promise();
    } // End save()


    /**
     * Save a change made outside a modal (remove, delete group, drag). Puts the table back on failure.
     *
     * @param {string} before
     * @param {string} successMessage
     */
    function saveChange( before, successMessage ) {
        save().then( function ( result ) {
            if ( result.ok ) {
                prx.toast( successMessage, 'success' );
                return;
            }

            if ( result.html ) {
                table().children( 'tbody' ).remove();
                table().append( $.parseHTML( result.html, document, false ) );
                initSortable();
                refreshCounts();
            } else {
                restore( before );
            }

            prx.toast( firstError( result ), 'error' );
        } );
    } // End saveChange()


    /**
     * Save a change made in a modal. Closes the modal on success; otherwise puts the table back and
     * shows the error in the modal.
     *
     * @param {jQuery} modal
     * @param {string} before
     * @param {string} rowId
     * @param {string} successMessage
     */
    function submitChange( modal, before, rowId, successMessage ) {
        var button = modal.find( '.prxctrl-modal-footer .prxctrl-button:not(.prxctrl-button-secondary)' );

        prx.busy( button, prx.format( '%s...', window.prxctrlCommon.i18n.saving ) );

        save().then( function ( result ) {
            prx.unbusy( button );

            if ( result.ok ) {
                prx.closeModal( modal );
                prx.toast( successMessage, 'success' );
                return;
            }

            restore( before );

            if ( editingRow ) {
                editingRow = table().find( 'tr.prxctrl-site-row[data-id="' + editingRow.attr( 'data-id' ) + '"]' );
            }

            if ( editingGroup ) {
                editingGroup = groupBody( editingGroup.attr( 'data-group-id' ) );
            }

            modalError( modal, rowId && result.errors[ rowId ] ? result.errors[ rowId ] : firstError( result ) );
        } );
    } // End submitChange()


    /**
     * Get the most useful error message from a save result.
     *
     * @param {Object} result
     * @return {string}
     */
    function firstError( result ) {
        var messages = [];

        $.each( result.errors || {}, function ( id, message ) {
            var row = table().find( 'tr.prxctrl-site-row[data-id="' + id + '"]' );
            var name = row.attr( 'data-name' ) || row.attr( 'data-url' ) || '';

            messages.push( name ? name + ': ' + message : message );
        } );

        return messages.length ? messages.join( ' ' ) : result.message;
    } // End firstError()


    $( function () {
        if ( ! table().length ) {
            return;
        }

        initSortable();
        refreshCounts();

        $( '#prxctrl-add-site' ).on( 'click', function () {
            openSiteModal( null );
        } );

        $( '#prxctrl-add-group' ).on( 'click', function () {
            openGroupModal( null );
        } );

        table().on( 'mousedown touchstart', '.prxctrl-site-handle, .prxctrl-group-handle', startDrag );

        $( '#prxctrl-site-group' ).on( 'change', function () {
            var input = $( '#prxctrl-site-new-group' );

            input.prop( 'hidden', '__new' !== $( this ).val() );

            if ( '__new' === $( this ).val() ) {
                input.trigger( 'focus' );
            }
        } );

        $( '#prxctrl-site-modal-submit' ).on( 'click', submitSiteModal );
        $( '#prxctrl-group-modal-submit' ).on( 'click', submitGroupModal );

        $( '#prxctrl-site-modal, #prxctrl-group-modal' ).on( 'keydown', 'input', function ( event ) {
            if ( 'Enter' === event.key ) {
                event.preventDefault();
                $( this ).closest( '.prxctrl-modal' ).find( '.prxctrl-modal-footer .prxctrl-button:not(.prxctrl-button-secondary)' ).trigger( 'click' );
            }
        } );

        $( '#prxctrl-toggle-key' ).on( 'click', function () {
            var input = $( '#prxctrl-site-key' );
            var hidden = 'password' === input.attr( 'type' );

            input.attr( 'type', hidden ? 'text' : 'password' );
            $( this ).text( hidden ? i18n.hide : i18n.show );
        } );

        $( '#prxctrl-modal-check' ).on( 'click', function () {
            var modal = $( '#prxctrl-site-modal' );
            var data = { nonce: config.nonce, url: normalizeUrl( modal.find( '#prxctrl-site-url' ).val() ), api_key: modal.find( '#prxctrl-site-key' ).val().trim() };
            var target = $( '#prxctrl-modal-check-result' );

            if ( editingRow && /^\d+$/.test( String( editingRow.attr( 'data-id' ) ) ) ) {
                data.site_id = editingRow.attr( 'data-id' );
            }

            if ( ! data.api_key && editingRow && editingRow.data( 'key' ) ) {
                data.api_key = editingRow.data( 'key' );
            }

            showConnection( target, 'busy', i18n.checking );

            prx.post( 'prxctrl_check_connection', data ).done( function ( response ) {
                if ( response.success ) {
                    showConnection( target, 'ok', response.data.message );

                    if ( response.data.name && ! modal.find( '#prxctrl-site-name' ).val() ) {
                        modal.find( '#prxctrl-site-name' ).val( response.data.name );
                    }
                } else {
                    showConnection( target, 'error', prx.message( response ), response.data && response.data.logs );
                }
            } ).fail( function ( xhr ) {
                showConnection( target, 'error', prx.message( xhr ), config.logsUrl );
            } );
        } );

        table().on( 'click', '.prxctrl-edit-site', function () {
            openSiteModal( $( this ).closest( 'tr' ) );
        } );

        table().on( 'click', '.prxctrl-remove-site', function () {
            var row = $( this ).closest( 'tr' );

            if ( ! window.confirm( prx.format( i18n.confirmRemove, row.find( '.prxctrl-row-name' ).text() ) ) ) {
                return;
            }

            var before = snapshot();

            row.remove();
            refreshCounts();
            saveChange( before, i18n.removedSite );
        } );

        table().on( 'click', '.prxctrl-check-connection', function () {
            checkRow( $( this ).closest( 'tr' ) );
        } );

        table().on( 'click', '.prxctrl-edit-group', function () {
            openGroupModal( $( this ).closest( 'tbody' ) );
        } );

        table().on( 'click', '.prxctrl-remove-group', function () {
            var body = $( this ).closest( 'tbody' );

            if ( ! window.confirm( prx.format( i18n.confirmRemoveGroup, body.attr( 'data-name' ) ) ) ) {
                return;
            }

            var before = snapshot();

            body.find( 'tr.prxctrl-site-row' ).each( function () {
                placeRow( $( this ), '0' );
            } );

            body.remove();
            refreshCounts();
            saveChange( before, i18n.removedGroup );
        } );

        $( '#prxctrl-check-connections' ).on( 'click', function () {
            var button = $( this );
            var rows = table().find( 'tr.prxctrl-site-row:visible' ).toArray();
            var index = 0;

            prx.busy( button, i18n.checking );

            function next() {
                if ( index >= rows.length ) {
                    prx.unbusy( button );
                    return;
                }

                checkRow( $( rows[ index++ ] ) ).then( next );
            }

            next();
        } );

        $( '.prxctrl-status-views a' ).on( 'click', function ( event ) {
            var view = $( this ).data( 'view' );

            event.preventDefault();
            $( '.prxctrl-status-views a' ).removeClass( 'current' ).removeAttr( 'aria-current' );
            $( this ).addClass( 'current' ).attr( 'aria-current', 'page' );

            table().find( 'tr.prxctrl-site-row' ).each( function () {
                $( this ).toggle( 'all' === view || $( this ).attr( 'data-status' ) === view );
            } );
        } );
    } );

} )( jQuery );

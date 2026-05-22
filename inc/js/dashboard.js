jQuery( function( $ ) {

    // console.log( 'PluginRx Control Center Dashboard JS loaded...' );

    // Toggle Plugins Table
    $( '.prxctrl-toggle-plugins-table' ).on( 'click', function( e ) {
        e.preventDefault();
        var site_id = $( this ).data( 'site-id' );

        // Close themes and admin users tables if open
        $( '#prxctrl-themes-table-' + site_id ).slideUp();
        $( '#prxctrl-admin-users-table-' + site_id ).slideUp();

        // Toggle plugins table
        $( '#prxctrl-plugins-table-' + site_id ).slideToggle();
    } );

    // Toggle Themes Table
    $( '.prxctrl-toggle-themes-table' ).on( 'click', function( e ) {
        e.preventDefault();
        var site_id = $( this ).data( 'site-id' );

        // Close plugins and admin users tables if open
        $( '#prxctrl-plugins-table-' + site_id ).slideUp();
        $( '#prxctrl-admin-users-table-' + site_id ).slideUp();

        // Toggle themes table
        $( '#prxctrl-themes-table-' + site_id ).slideToggle();
    } );

    // Toggle Admin Users Table
    $( '.prxctrl-toggle-admin-users-table' ).on( 'click', function( e ) {
        e.preventDefault();
        var site_id = $( this ).data( 'site-id' );

        // Close plugins and themes tables if open
        $( '#prxctrl-plugins-table-' + site_id ).slideUp();
        $( '#prxctrl-themes-table-' + site_id ).slideUp();

        // Toggle admin users table
        $( '#prxctrl-admin-users-table-' + site_id ).slideToggle();
    } );

    // Table of Contents Navigation
    $( '#prxctrl-toc-select' ).on( 'change', function() {
        var targetId = $( this ).val();
        if ( ! targetId ) {
            return;
        }

        var target = $( '#' + targetId );
        if ( target.length ) {
            $( 'html, body' ).animate( {
                scrollTop: target.offset().top - 73
            }, 400 );
        }
    } );

    // Convert bytes to human readable format
    function formatBytes( bytes ) {

        if ( ! bytes || bytes === 0 ) {
            return '0 B';
        }

        var k = 1024;
        var sizes = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        var i = Math.floor( Math.log( bytes ) / Math.log( k ) );

        return ( bytes / Math.pow( k, i ) ).toFixed( 2 ) + ' ' + sizes[ i ];
    }

    // Clear previous site values and state classes
    function clearSiteValues( siteSection ) {
        siteSection.find( '.prxctrl-site-col span:not(.prxctrl-site-url):not(.prxctrl-icon-url):not(.prxctrl-icon-url .dashicons):not(.prxctrl-integration-wrapper), .prxctrl-site-col .prxctrl-integration-wrapper .prxctrl-integration-value' ).text( '—' );
        siteSection.find( '.prxctrl-site-col' ).removeClass( 'prxctrl-warning prxctrl-debug-enabled' );
        siteSection.find( '.prxctrl-plugins-table tbody, .prxctrl-themes-table tbody, .prxctrl-admin-users-table tbody' ).empty();

        // Remove existing wrapping links
        siteSection
            .find( '.prxctrl-site-col:not(.plugin-count):not(.theme-count):not(.admin-user-count) span:not(.prxctrl-site-url):not(.prxctrl-icon-url .dashicons)' )
            .each( function() {
                var $span = $( this );
                if ( $span.parent( 'a' ).length ) {
                    $span.unwrap(); // removes the <a> but keeps the span
                }
            } );
    }

    // Decode HTML entities
    function decodeHtml( string ) {
        return $( '<textarea>' ).html( string ).text();
    }

    // Populate site data and apply classes to correct columns
    function populateSiteData( siteSection, data ) {

        if ( data.screenshot_url ) {
            var $thumbContainer = siteSection.find( '.prxctrl-site-thumbnail' );
            var cacheBuster = new Date().getTime();
            var freshUrl = data.screenshot_url + '&v=' + cacheBuster;

            // Create a new image object in memory
            var $imgObj = $( '<img />', {
                src: freshUrl,
                alt: 'Site Screenshot',
                referrerpolicy: 'no-referrer',
                class: 'prxctrl-site-screenshot',
            });

            // Retry logic
            $imgObj.on( 'load', function() {
                // Success! Fade it in and remove the placeholder
                $( this ).fadeIn();
                $thumbContainer.html( $( this ) );
            }).on( 'error', function() {
                var attempts = $( this ).data( 'retry-count' ) || 0;
                if ( attempts < 3 ) {
                    $( this ).data( 'retry-count', attempts + 1 );
                    var $self = $( this );
                    setTimeout( function() {
                        var retryBuster = new Date().getTime();
                        $self.attr( 'src', data.screenshot_url + '&v=' + retryBuster );
                    }, 3000 );
                } else {
                    $thumbContainer.html( '<div class="prxctrl-loader-placeholder error">Failed to load</div>' );
                }
            });
        }

        // Populate basic site info
        siteSection.find( '.prxctrl-admin-email' ).text( data.admin_email );
        siteSection.find( '.prxctrl-server-ip' ).text( data.server_ip );
        siteSection.find( '.prxctrl-abspath' ).text( data.abspath );
        siteSection.find( '.prxctrl-last-checked' ).text( data.last_checked );

        siteSection.find( '.prxctrl-is-multisite' ).text( data.is_multisite ? 'Yes' : 'No' );
        siteSection.find( '.prxctrl-blog-id' ).text( data.blog_id );
        siteSection.find( '.prxctrl-wp-version' ).text( data.wordpress_version );
        siteSection.find( '.prxctrl-php-version' ).text( data.php_version );
        siteSection.find( '.prxctrl-wp-debug' ).text( data.wp_debug ? 'Enabled' : 'Disabled' );

        if ( data.is_wp_outdated ) {
            siteSection.find( '.prxctrl-wp-version' )
                .closest( '.prxctrl-site-col' )
                .addClass( 'prxctrl-warning' );
        }

        if ( data.is_php_outdated ) {
            siteSection.find( '.prxctrl-php-version' )
                .closest( '.prxctrl-site-col' )
                .addClass( 'prxctrl-warning' );
        }
        
        if ( data.wp_debug ) {
            siteSection.find( '.prxctrl-wp-debug' )
                .closest( '.prxctrl-site-col' )
                .addClass( 'prxctrl-debug-enabled' );
        }

        // WP version link
        if ( data.is_wp_outdated && data.admin_path ) {
            $( '.prxctrl-wp-version', siteSection ).wrap(
                '<a href="' + data.admin_path.replace(/\/$/, '') + '/' + prxctrl_dashboard.links.wordpress_version + '" target="_blank" class="prxctrl-link"></a>'
            );
        }

        // WP Debug link
        if ( data.wp_debug && data.admin_path ) {
            $( '.prxctrl-wp-debug', siteSection ).wrap(
                '<a href="' + data.admin_path.replace(/\/$/, '') + '/' + prxctrl_dashboard.links.wp_debug + '" target="_blank" class="prxctrl-link"></a>'
            );
        }

        // Populate integrations
        if ( data.integrations ) {
            $.each( data.integrations, function( key, integration ) {

                var container = siteSection.find( '.prxctrl-integration-col' ).filter( function() {
                    return $( this ).find( '.prxctrl-integration-value' ).length &&
                        $( this ).find( '.prxctrl-integration-value' ).data( 'integration-key' ) === key;
                } );

                var valueEl = container.find( '.prxctrl-integration-value' );

                if ( integration.format === 'filesize' ) {
                    integration.value = formatBytes( parseInt( integration.value, 10 ) );
                }

                valueEl.text( integration.value );

                var linkHref = integration.link.match( /^[a-zA-Z][a-zA-Z0-9+.-]*:\/\// )
                    ? integration.link
                    : data.admin_path.replace( /\/$/, '' ) + '/' + integration.link;

                if ( integration.link && data.admin_path ) {
                    valueEl.wrap(
                        '<a href="' + linkHref + '" target="_blank" class="prxctrl-link"></a>'
                    );
                }

                if ( integration.warn ) {
                    container.addClass( 'prxctrl-warning' );
                }
            } );
        }

        // Populate plugins data
        if ( data.plugins ) {

            siteSection.find( '.prxctrl-plugin-count' ).text( data.plugins.length );

            var plugin_updates = data.plugins.filter( function( plugin ) {
                return plugin.update_available;
            } );

            siteSection.find( '.prxctrl-plugin-update-count' ).text( plugin_updates.length );
            
            if ( plugin_updates.length > 0 ) {
                siteSection.find( '.prxctrl-site-col.plugin-count' ).addClass( 'prxctrl-updates-available' );
            } else {
                siteSection.find( '.prxctrl-site-col.plugin-count' ).removeClass( 'prxctrl-updates-available' );
            }

            var pluginsTbody = siteSection.find( '.prxctrl-plugins-table tbody' );

            data.plugins.forEach( function( plugin ) {

                var rowClass = '';
                if ( plugin.active ) {
                    rowClass += 'active-row';
                } else {
                    rowClass += 'inactive-row';
                }
                if ( plugin.update_available ) {
                    rowClass += ' update-available-row';
                }

                var row =
                    '<tr class="' + rowClass + '">' +
                        '<td>' + plugin.name + '</td>' +
                        '<td>' + plugin.version + '</td>' +
                        '<td>' + plugin.author + '</td>' +
                        '<td>' + plugin.plugin_file + '</td>' +
                        '<td>' + ( plugin.active ? 'Yes' : 'No' ) + '</td>' +
                        '<td>' + ( plugin.update_available ? 'Yes' : 'No' ) + '</td>' +
                    '</tr>';

                pluginsTbody.append( row );
            } );
        }

        // Populate themes data
        if ( data.themes ) {

            siteSection.find( '.prxctrl-theme-count' ).text( data.themes.length );

            var activeTheme = data.themes.find( function( theme ) {
                return theme.active;
            } );

            if ( activeTheme ) {
                siteSection.find( '.prxctrl-active-theme' ).text(
                    decodeHtml( activeTheme.name ) + ( activeTheme.version ? ' ' + activeTheme.version : '' )
                );
            }

            var theme_updates = data.themes.filter( function( theme ) {
                return theme.update_available;
            } );

            siteSection.find( '.prxctrl-theme-update-count' ).text( theme_updates.length );
            
            if ( theme_updates.length > 0 ) {
                siteSection.find( '.prxctrl-site-col.theme-count' ).addClass( 'prxctrl-updates-available' );
            } else {
                siteSection.find( '.prxctrl-site-col.theme-count' ).removeClass( 'prxctrl-updates-available' );
            }

            var themesTbody = siteSection.find( '.prxctrl-themes-table tbody' );

            data.themes.forEach( function( theme ) {

                var rowClass = '';
                if ( theme.active ) {
                    rowClass += 'active-row';
                } else {
                    rowClass += 'inactive-row';
                }
                if ( theme.update_available ) {
                    rowClass += ' update-available-row';
                }

                var row =
                    '<tr class="' + rowClass + '">' +
                        '<td>' + theme.name + '</td>' +
                        '<td>' + theme.version + '</td>' +
                        '<td>' + theme.author + '</td>' +
                        '<td>' + ( theme.active ? 'Yes' : 'No' ) + '</td>' +
                        '<td>' + ( theme.update_available ? 'Yes' : 'No' ) + '</td>' +
                    '</tr>';

                themesTbody.append( row );
            } );
        }

        // Populate admin users data
        if ( data.admin_users ) {

            siteSection.find( '.prxctrl-admin-user-count' ).text( data.admin_users.length );

            var usersTbody = siteSection.find( '.prxctrl-admin-users-table tbody' );

            data.admin_users.forEach( function( admin ) {

                var rowClass = ( admin.online_status && admin.online_status === 'online' ) ? 'online-row' : 'offline-row';

                var userEditLink = '';
                if ( siteSection.data( 'admin-path' ) && admin.user_id ) {
                    userEditLink = siteSection.data( 'admin-path' ).replace( /\/$/, '' ) + '/user-edit.php?user_id=' + admin.user_id;
                }

                var row =
                    '<tr class="' + rowClass + '">' +
                        '<td><a href="' + userEditLink + '" target="_blank">' + admin.user_login + '</a></td>' +
                        '<td>' + admin.display_name + '</td>' +
                        '<td>' + admin.user_email + '</td>' +
                        '<td>' + ( admin.role || '' ) + '</td>' +
                        '<td>' + ( admin.is_dev ? 'Yes' : 'No' ) + '</td>' +
                        '<td>' + admin.user_id + '</td>' +
                        '<td>' + ( admin.user_registered || '' ) + '</td>' +
                        '<td class="prxctrl-online-status">' + ( admin.online_status_text || '' ) + '</td>' +
                    '</tr>';

                usersTbody.append( row );
            } );
        }

        // Enable/disable update buttons based on available updates
        siteSection.find( '.prxctrl-update-plugins-button' )
            .prop( 'disabled', ! ( data.plugins && data.plugins.some( plugin => plugin.update_available ) ) );

        siteSection.find( '.prxctrl-update-themes-button' )
            .prop( 'disabled', ! ( data.themes && data.themes.some( theme => theme.update_available ) ) );

        siteSection.find( '.prxctrl-update-wp-button' )
            .prop( 'disabled', ! data.is_wp_outdated );
    }

    // Run Site Check (used on initial load)
    function runSiteCheck( siteSection, triggerButton ) {
        var siteId = siteSection.attr( 'id' ).replace( 'prxctrl-site-', '' );
        var originalText = triggerButton ? triggerButton.text() : null;

        clearSiteValues( siteSection );

        var $thumbContainer = siteSection.find( '.prxctrl-site-thumbnail' );
        $thumbContainer.html( '<div class="prxctrl-loader-placeholder">Loading...</div>' );

        siteSection.removeClass( 'success-fetching error-fetching' ).addClass( 'fetching' );
        
        var actionButtons = siteSection.find( '.prxctrl-site-actions .button' );
        var buttonStates = [];

        actionButtons.each( function() {
            buttonStates.push( $( this ).prop( 'disabled' ) );
        } );
        actionButtons.prop( 'disabled', true );

        if ( triggerButton ) {
            triggerButton.text( triggerButton.data( 'wait-msg' ) );
        }

        var resultMessageBox = siteSection.find( '.prxctrl-result-message' );
        resultMessageBox.removeClass( 'danger success' ).text( '' ).hide();

        return $.ajax( {
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'prxctrl_fetch_site_data',
                site_id: siteId,
                nonce: prxctrl_dashboard.nonce
            }
        } ).done( function( response ) {

            siteSection.removeClass( 'fetching' );
            
            actionButtons.each( function( index ) {
                $( this ).prop( 'disabled', buttonStates[ index ] );
            } );

            if ( triggerButton ) {
                triggerButton.prop( 'disabled', false ).text( originalText );
            }

            if ( response.success ) {
                populateSiteData( siteSection, response.data );
                siteSection.addClass( 'success-fetching' );
            } else {
                siteSection.addClass( 'error-fetching' );
                resultMessageBox
                    .html( response.data.message || 'An unknown error occurred.' )
                    .addClass( 'danger' )
                    .show();
            }

            if ( 'yes' === prxctrl_dashboard.console_enabled ) {
                console.log( response.data );
            }

        } ).fail( function( jqXHR, textStatus, errorThrown ) {

            siteSection.removeClass( 'fetching' ).addClass( 'error-fetching' );
            
            actionButtons.each( function( index ) {
                $( this ).prop( 'disabled', buttonStates[ index ] );
            } );

            if ( triggerButton ) {
                triggerButton.prop( 'disabled', false ).text( originalText );
            }

            resultMessageBox
                .html( 'AJAX error: ' + ( errorThrown || textStatus ) )
                .addClass( 'danger' )
                .show();

            if ( 'yes' === prxctrl_dashboard.console_enabled ) {
                console.log( textStatus, errorThrown );
            }

        } );
    }

    // Individual Site Check Button
    $( '.prxctrl-check-site-button' ).on( 'click', function( e ) {
        e.preventDefault();

        var button = $( this );
        var siteId = button.data( 'site-id' );
        var siteSection = $( '#prxctrl-site-' + siteId );

        button.prop( 'disabled', true );
        runSiteCheck( siteSection, button );
    } );

    // Global Check All Sites Button
    $( '#prxctrl-check-sites' ).on( 'click', async function( e ) {
        e.preventDefault();

        var button = $( this );
        var originalText = button.html();

        var siteSections = $( '.prxctrl-dashboard-sites .prxctrl-site-section' );
        var totalSites = siteSections.length;
        var completedSites = 0;

        var progressText = $( '.prxctrl-check-all-sites-progress' );
        var progressBar = $( '.prxctrl-check-all-sites-progress-bar-fill' );

        button.prop( 'disabled', true ).text( button.data( 'wait-msg' ) );

        progressText.text( '' );
        progressBar.css( 'width', '0%' );

        for ( var i = 0; i < totalSites; i++ ) {
            var siteSection = $( siteSections[ i ] );
            var siteTitle = siteSection.find( '.prxctrl-site-title' ).text().trim();

            completedSites++;

            var percent = Math.round( ( completedSites / totalSites ) * 100 );

            progressText.text(
                completedSites + ' out of ' + totalSites + ' (' + percent + '%) — ' + prxctrl_dashboard.checking + ' ' + siteTitle + '...'
            );

            progressBar.css( 'width', percent + '%' );

            await runSiteCheck( siteSection, null );
        }

        progressText.text(
            totalSites + ' out of ' + totalSites + ' (100%) — ' + prxctrl_dashboard.all_sites_checked
        );

        progressBar.css( 'width', '100%' );

        button.prop( 'disabled', false ).html( originalText );
    } );


    // Run Site Action (Clear Cache, Update Plugins/Themes/WP)
    function runSiteAction( button, actionType ) {
        var originalText = button.text();
        var waitMsg = button.data( 'wait-msg' );
        var siteId = button.data( 'site-id' );
        var siteSection = $( '#prxctrl-site-' + siteId );

        siteSection.removeClass( 'success-fetching error-fetching' ).addClass( 'fetching' );

        var actionButtons = siteSection.find( '.prxctrl-site-actions .button' );
        var buttonStates = [];

        actionButtons.each( function() {
            buttonStates.push( $( this ).prop( 'disabled' ) );
        } );

        actionButtons.prop( 'disabled', true );
        button.text( waitMsg );

        var resultMessageBox = siteSection.find( '.prxctrl-result-message' );
        resultMessageBox.removeClass( 'danger success' ).text( '' ).hide();

        $.ajax( {
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'prxctrl_perform_action',
                type: actionType,
                site_id: siteId,
                nonce: prxctrl_dashboard.nonce
            }
        } ).done( function( response ) {

            siteSection.removeClass( 'fetching' );
            button.text( originalText );

            actionButtons.each( function( index ) {
                $( this ).prop( 'disabled', buttonStates[ index ] );
            } );

            if ( response.success ) {
                resultMessageBox
                    .html( response.data.message || 'Action completed successfully.' )
                    .addClass( 'success' )
                    .show();
            } else {
                siteSection.addClass( 'error-fetching' );
                resultMessageBox
                    .html( response.data.message || 'An unknown error occurred.' )
                    .addClass( 'danger' )
                    .show();
            }

            if ( 'yes' === prxctrl_dashboard.console_enabled ) {
                console.log( response.data );
            }

        } ).fail( function( jqXHR, textStatus, errorThrown ) {

            siteSection.removeClass( 'fetching' ).addClass( 'error-fetching' );
            button.text( originalText );

            actionButtons.each( function( index ) {
                $( this ).prop( 'disabled', buttonStates[ index ] );
            } );

            resultMessageBox
                .html( 'AJAX error: ' + ( errorThrown || textStatus ) )
                .addClass( 'danger' )
                .show();

            if ( 'yes' === prxctrl_dashboard.console_enabled ) {
                console.log( textStatus, errorThrown );
            }

        } );
    }

    // Site Action Buttons (Clear Cache, Update Plugins/Themes/WP)
    $( document ).on( 'click', '.prxctrl-site-actions .button:not(.prxctrl-check-site-button)', function( e ) {
        e.preventDefault();

        var button = $( this );
        if ( button.prop( 'disabled' ) ) {
            return;
        }

        var actionType = button.data( 'action' );
        if ( ! actionType ) {
            return;
        }

        if ( ! confirm( prxctrl_dashboard.confirmation ) ) {
            return;
        }

        runSiteAction( button, actionType );
    } );

} );

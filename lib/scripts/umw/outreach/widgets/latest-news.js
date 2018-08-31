;var umw_latest_news_widget = umw_latest_news_widget || {};
jQuery( function( $ ) {
    umw_latest_news_widget.callbacks = {};
    umw_latest_news_widget.callbacks.ajax_success = function( data, textStatus ) {
        umw_latest_news_widget.callbacks.update_categories( data );
        umw_latest_news_widget.callbacks.update_tags( data );
        umw_latest_news_widget.callbacks.update_thumbsize( data );
    };
    umw_latest_news_widget.callbacks.ajax_start = function( xhr, settings, data ) {
        console.log( 'Starting the AJAX process for UMW Latest News Widget' );
        console.log( data );

        var selector = jQuery( 'select[name="' + data.instance.field_name.replace( '[source]', '[categories_select][]' ) + '"]' );
        selector.closest( 'fieldset' ).hide();
        selector.closest( 'form, .form' ).find( '.floatingCirclesG' ).show();
    };
    umw_latest_news_widget.callbacks.ajax_end = function( xhr, textStatus, data ) {
        console.log( 'Ending the AJAX process for UMW Latest News Widget' );
        console.log( data );

        var selector = jQuery( 'select[name="' + data.instance.field_name.replace( '[source]', '[categories_select][]' ) + '"]' );
        selector.closest( 'form, .form' ).find( '.floatingCirclesG' ).hide();
        selector.closest( 'fieldset' ).show();
        if ( umw_latest_news_widget[Object.keys(umw_latest_news_widget)[0]].hasSelect2 ) {
            selector.closest( 'fieldset' ).find( 'select[multiple]' ).select2({'width':'100%'});
        }
    };
    umw_latest_news_widget.callbacks.update_selector = function( options, fieldname, selected, tax_or_not ) {
        var sel = jQuery( 'select[name="' + fieldname + '"]' );
        sel.empty();
        for ( var i in options ) {
            var selText = '';
            if ( tax_or_not === 'tax' ) {
                if ( options[i].id in selected ) {
                    selText = ' selected="selected"';
                }
                sel.append('<option value="' + options[i].id + '"' + selText + '>[ID: ' + options[i].id + '] ' + options[i].name + ' (' + options[i].count + ' posts)</option>');
            } else {
                if ( i == selected ) {
                    selText = ' selected="selected"';
                }
                sel.append('<option value="' + i + '"' + selText + '>' + i + ' (' + options[i].width + 'x' + options[i].height + ')</option>');
            }
        }
    };
    umw_latest_news_widget.callbacks.update_categories = function( data ) {
        var selector = data.instance.field_name.replace( '[source]', '[categories_select][]' );
        var selected = {};
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) && data.instance.categories !== null ) {
            var tmp = data.instance.categories.split( ',' );
            if ( tmp.length >= 1 ) {
                for (var i in tmp) {
                    selected[tmp[i]] = true;
                }
            }
        }

        umw_latest_news_widget.callbacks.update_selector( data.categories, selector, selected, 'tax' );
    };
    umw_latest_news_widget.callbacks.update_tags = function( data ) {
        var selector = data.instance.field_name.replace( '[source]', '[tags_select][]' );
        var selected = {};
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) && data.instance.tags !== null ) {
            var tmp = data.instance.tags.split( ',' );
            if ( tmp.length >= 1 ) {
                for (var i in tmp) {
                    selected[tmp[i]] = true;
                }
            }
        }

        umw_latest_news_widget.callbacks.update_selector( data.tags, selector, selected, 'tax' );
    };
    umw_latest_news_widget.callbacks.update_thumbsize = function ( data ) {
        var selector = data.instance.field_name.replace( '[source]', '[size_select]' );
        var selected = '';
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) && data.instance.thumbsize !== null ) {
            selected = data.instance.thumbsize;
        }

        umw_latest_news_widget.callbacks.update_selector( data.image_sizes, selector, selected, 'not' );
    };
    umw_latest_news_widget.callbacks.change = function() {
        // Figure out what identifier we used for the object property
        var name = jQuery( this ).attr( 'id' ).replace( 'source', '' );

        if ( name in umw_latest_news_widget ) {
            var obj = umw_latest_news_widget[name];
        } else {
            console.log( 'The field that changed was not found in the defined object, so we are requesting it' );
            var tmpFormObj = jQuery( this ).closest( 'form, .form' );
            var tmpDataObj = {
                'action' : umw_latest_news_widget.default.action,
                'nonce' : umw_latest_news_widget.default.nonce,
                'widget_source_field_id' : tmpFormObj.find( '[name$="[source]"]' ).attr( 'name' ),
                'widget_number' : tmpFormObj.find( '.widget_number' ).val(),
                'widget_id' : tmpFormObj.find( '.widget-id' ).val(),
                'instance_key' : name
            };
            jQuery.ajax( {
                'url' : umw_latest_news_widget.default.ajax_url,
                'data' : tmpDataObj,
                'success' : function( data, textStatus ) {
                    console.log( 'Preparing to add ' + tmpDataObj.instance_key + ' to the umw_latest_news_widget object' );
                    console.log( data );
                    umw_latest_news_widget[tmpDataObj.instance_key] = data;
                    console.log( umw_latest_news_widget );
                    umw_latest_news_widget.callbacks.change.call( tmpFormObj.find( '[name$="[source]"]' ) );
                },
                'error' : function() {
                    console.log( 'There was an error attempting to retrieve the arguments for ' + name );
                    console.log( arguments );
                }
            } );
            return;
        }

        // Identify the AJAX URL to query
        var url = umw_latest_news_widget[name].ajax_url;

        // Set up our GET data
        var data = { 'source' : jQuery( this ).val(), 'action' : umw_latest_news_widget[name].ajax_action, 'instance' : umw_latest_news_widget[name] };

        // Perform our AJAX request
        jQuery.ajax( {
            'url' : url,
            'data' : data,
            'success' : function( data, textStatus ) { return umw_latest_news_widget.callbacks.ajax_success( data, textStatus ); },
            'error' : function() {
                console.log( 'There was an error retrieving the list of categories, tags and media sizes for ' + name );
                console.log( arguments );
            },
            'crossDomain' : true,
            'beforeSend' : function( jqxhr, settings ) { return umw_latest_news_widget.callbacks.ajax_start( jqxhr, settings, data ); },
            'complete' : function( jqxhr, textStatus ) { return umw_latest_news_widget.callbacks.ajax_end( jqxhr, textStatus, data ); }
        } );
    };

    for ( var i in umw_latest_news_widget ) {
        jQuery( 'input[name="' + umw_latest_news_widget[i].field_name + '"]' ).on( 'change', function() { umw_latest_news_widget.callbacks.change.call(this); } );

        if ( 0 !== umw_latest_news_widget[i].widget_id ) {
            jQuery( 'input[name="' + umw_latest_news_widget[i].field_name + '"]' ).trigger( 'change' );
        }
    }

    jQuery(document).on( 'widget-updated widget-added', function( event, widget ) {
        if ( $( widget ).attr( 'id' ).indexOf( 'umw-latest-news' ) < 0 ) {
            console.log( 'The widget that was updated/added does not appear to be a UMW Latest News Widget' );
            return;
        }

        if ( $( widget ).find( 'input[name$="[source]"]' ).length >= 1 ) {
            console.log( widget );
            $( widget ).find( 'input[name$="[source]"]' ).on( 'change', function() { umw_latest_news_widget.callbacks.change.call(this); } ).trigger( 'change' );
        }
    } );
} );

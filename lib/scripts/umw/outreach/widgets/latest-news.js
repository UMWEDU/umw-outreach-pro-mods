;var umw_latest_news_widget = umw_latest_news_widget || {};
jQuery( function( $ ) {
    umw_latest_news_widget.callbacks = {};
    umw_latest_news_widget.callbacks.ajax_success = function( data, textStatus ) {
        umw_latest_news_widget.callbacks.update_categories( data );
        umw_latest_news_widget.callbacks.update_tags( data );
        umw_latest_news_widget.callbacks.update_thumbsize( data );
    };
    umw_latest_news_widget.callbacks.ajax_start = function( xhr, settings, data ) {
        var selector = jQuery( 'select[name="' + data.instance.field_name.replace( '[source]', '[categories_select][]' ) + '"]' );
        selector.closest( 'fieldset' ).hide();
        selector.parent().parent().find( '.floatingCirclesG' ).show();
    };
    umw_latest_news_widget.callbacks.ajax_end = function( xhr, textStatus, data ) {
        var selector = jQuery( 'select[name="' + data.instance.field_name.replace( '[source]', '[categories_select][]' ) + '"]' );
        selector.parent().parent().find( '.floatingCirclesG' ).hide();
        selector.closest( 'fieldset' ).show();
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
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) ) {
            var tmp = data.instance.categories.split( ',' );
            for ( var i in tmp ) {
                selected[tmp[i]] = true;
            }
        }

        umw_latest_news_widget.callbacks.update_selector( data.categories, selector, selected, 'tax' );
    };
    umw_latest_news_widget.callbacks.update_tags = function( data ) {
        var selector = data.instance.field_name.replace( '[source]', '[tags_select][]' );
        var selected = {};
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) ) {
            var tmp = data.instance.tags.split( ',' );
            for ( var i in tmp ) {
                selected[tmp[i]] = true;
            }
        }

        umw_latest_news_widget.callbacks.update_selector( data.tags, selector, selected, 'tax' );
    };
    umw_latest_news_widget.callbacks.update_thumbsize = function ( data ) {
        var selector = data.instance.field_name.replace( '[source]', '[size_select]' );
        var selected = '';
        if ( data.source.replace( /\//g, '' ) === data.instance.source.replace( /\//g, '' ) ) {
            selected = data.instance.thumbsize;
        }

        umw_latest_news_widget.callbacks.update_selector( data.image_sizes, selector, selected, 'not' );
    };

    for ( var i in umw_latest_news_widget ) {
        jQuery( 'input[name="' + umw_latest_news_widget[i].field_name + '"]' ).on( 'change', function() {
            // Figure out what identifier we used for the object property
            var name = jQuery( this ).attr( 'id' ).replace( 'source', '' );

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
                    console.log( arguments );
                },
                'crossDomain' : true,
                'beforeSend' : function( jqxhr, settings ) { return umw_latest_news_widget.callbacks.ajax_start( jqxhr, settings, data ); },
                'complete' : function( jqxhr, textStatus ) { return umw_latest_news_widget.callbacks.ajax_end( jqxhr, textStatus, data ); }
            } );
        } );

        if ( 0 !== umw_latest_news_widget[i].widget_id ) {
            jQuery( 'input[name="' + umw_latest_news_widget[i].field_name + '"]' ).trigger( 'change' );
        }
    }
} );

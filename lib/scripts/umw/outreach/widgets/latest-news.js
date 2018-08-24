var umw_latest_news_widget = umw_latest_news_widget || {};
jQuery( function( $ ) {
    for ( var i in umw_latest_news_widget ) {
        console.log( umw_latest_news_widget[i].field_name );
        jQuery( 'input[name="' + umw_latest_news_widget[i].field_name + '"]' ).on( 'change', function() {
            var name = jQuery( this ).attr( 'id' ).replace( 'source', '' );
            var url = umw_latest_news_widget[name].ajax_url;
            var data = { 'source' : jQuery( this ).val(), 'action' : umw_latest_news_widget[name].ajax_action };
            console.log( data );
            jQuery.get( {
                'url' : url,
                'data' : data,
                'success' : function( data, textStatus ) {
                    console.log( data );
                },
                'error' : function() {
                    console.log( arguments );
                },
                'crossDomain' : true
            } );
        } );
    }
} );

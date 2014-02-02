jQuery( function( $ ) {
    $( '#wpPersona' ).click( function() {
	navigator.id.request();
    } );

    $( '#pt-personalogin' ).click( function( event ) {
	navigator.id.request();
    } );

    $( '#pt-logout > a' ).click( function( event ) {
	navigator.id.logout();
    } );

    navigator.id.watch( {
	loggedInUser: null,
	onlogin: function( assertion ) {
	    var api = new mw.Api();
	    api.post( {
		'action': 'persona',
		'assertion': assertion,
		'token': $( 'input[name="wpLoginToken"]' ).val(),
		'stickhttps': $( '#wpStickHTTPS' ).val()
	    } )
	    .done( function ( data ) {
		console.log( 'Persona login result:', data );
		var vars = [], hash;
		var q = document.URL.split( '?' )[1];
		if( q != undefined ){
		    q = q.split( '&' );
		    for( var i = 0; i < q.length; i++ ){
			hash = q[i].split( '=' );
			vars.push( hash[1] );
			vars[hash[0]] = hash[1];
		    }
		} else {
			q = {};
		}

		var title;
		if( q['returnto'] != undefined ) {
		    title = new mw.Title( q['returnto'] );
			window.location.href = title.getUrl();
		} else if( q['title'] != 'Special:Userlogin' ) {
			window.location.reload();
		} else {
		    title = new mw.Title( 'Main Page' );
			window.location.href = title.getUrl();
		}
	    } )
	    .fail( function ( error ) {
		console.log( 'Persona login failed.', error );
		mw.util.jsMessage( 'Persona login failed.' );
	    } );
	},
	onlogout: function() {}
    } );
} );

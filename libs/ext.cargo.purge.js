( function ( $, mw ) {
	'use strict';

	mw.loader.using( [ 'mediawiki.api', 'mediawiki.notify' ] ).then( function () {

		$( '#ca-cargo-purge a' ).on( 'click', function ( e ) {
			var postArgs = { action: 'purge', titles: mw.config.get( 'wgPageName' ) };
			new mw.Api().post( postArgs ).then(
				function () {
					location.reload();
				},
				function ( err ) {
					mw.notify( err, { type: 'error', title: mw.msg( 'cargo-purgecache-failed' ) } );
				}
			);
			e.preventDefault();
		} );

	} );

}( jQuery, mediaWiki ) );

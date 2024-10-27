( function () {
	'use strict';

	var $purgeLink = $( '#ca-cargo-purge a' ),
		deps = [ 'mediawiki.api' ];
	mw.loader.using( deps ).then( function () {
		$purgeLink.on( 'click', function ( e ) {
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

}() );

$( document ).ready( function() {
    $( '.downloadlink' ).each( function () {
        $( this ).on( 'click', function () {
            var links = $( this ).attr( 'data-fileurls' ).split( ' ' );
            links = links.filter( val => val );

            var count = 0;

            var zip = new JSZip();

            // Get desired filename
            var zipFilename = links.shift();

            // Iterate over the file links
            links.forEach( function( url ) {
                var filename = url.split( '/' ).pop();
                JSZipUtils.getBinaryContent( url, function ( err, data ) {
                    if( err ) {
                        throw err;
                    }
                    // Add file to the zip
                    zip.file( filename, data, { binary:true } );
                    count++;
                    if ( count == links.length ) {
                        zip.generateAsync( { type: "blob" } )
                        .then( function( content ) {
                            saveAs( content, zipFilename );
                        } );
                    }
                } );
            } );
        } );
    } );
} );
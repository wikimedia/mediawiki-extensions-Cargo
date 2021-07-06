jQuery( document ).ready( function() {
	jQuery( 'table td.cargo-pagevalues-fieldinfo' ).each( function() {
		var fieldType = jQuery( this ).attr( 'data-field-type' );
		var allowedValues = jQuery( this ).attr( 'data-allowed-values' );
		var content = '<p align=left><strong>' + mw.message('cargo-field-type').text() + '&nbsp;</strong>' + fieldType + '</p>\n';
		if ( allowedValues.length ) {
			content += '<p align=left><strong>' + mw.message('cargo-allowed-values').text() + '&nbsp;</strong>' + allowedValues + '</p>';
		}
		var popup = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			popup: {
				padded: true,
				align: 'force-right',
				$content: jQuery( content )
			}
		} );
		jQuery( this ).append( popup.$element );
	} );
} );

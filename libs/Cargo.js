/**
 * Cargo.js
 *
 * JavaScript utility functionality for the Cargo extension.
 *
 * @author Yaron Koren
 */

( function ( $, mw ) {

var showText = '[' + mw.msg( 'show' ) + ']';
var hideText = '[' + mw.msg( 'hide' ) + ']';

$('span.cargoMinimizedText')
	.hide()
	.parent().prepend('<a class="cargoToggle">' + showText + '</a> ');

$('a.cargoToggle').click( function() {
	if ( $(this).text() == showText ) {
		$(this).siblings('.cargoMinimizedText').show(400).css('display', 'inline');
		$(this).text(hideText);
	} else {
		$(this).siblings('.cargoMinimizedText').hide(400);
		$(this).text(showText);
	}
});

$('th.cargotables-columncount').each( function() {
	var columnsHelpText =  mw.msg( 'cargo-cargotables-columncountinfo', '<code>_pageName</code>' );
	var popup = new OO.ui.PopupButtonWidget( {
		icon: 'info',
		framed: false,
		popup: {
			padded: true,
			$content: $( '<p style="font-weight: normal">' + columnsHelpText + '</p>' )
		}
	} );
	popup.$element.css( 'margin-left', '5px' );

	$( this ).append( popup.$element );
});

}( jQuery, mediaWiki ) );
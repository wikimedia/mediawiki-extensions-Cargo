/**
 * Cargo.js
 *
 * JavaScript utility functionality for the Cargo extension.
 *
 * @author Yaron Koren
 */

mw.loader.using( ["oojs-ui-core"], function() {

var showText = '[' + mw.msg( 'show' ) + ']';
var showPseudoLink = $( '<a>' ).addClass( 'cargoToggle' ).text( showText );
var hideText = '[' + mw.msg( 'hide' ) + ']';

$('span.cargoMinimizedText')
	.hide()
	.parent().prepend( showPseudoLink );

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
	var columnsHelpText =  mw.msg( 'cargo-cargotables-columncountinfo', '_pageName' )
		// Instead of escaping the entire text, we do manual escaping here, so
		// that we can then do custom formatting of "_pageName".
		.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
		.replace(/_pageName/, "<code>_pageName</code>");
	var columnsHelpTag = $( '<p>' ).css( 'font-weight', 'normal' ).html( columnsHelpText );
	var popup = new OO.ui.PopupButtonWidget( {
		icon: 'info',
		framed: false,
		popup: {
			padded: true,
			$content: columnsHelpTag
		}
	} );
	popup.$element.css( 'margin-left', '5px' );

	$( this ).append( popup.$element );
});

} );

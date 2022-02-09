/**
 * An OOUI-based widget for an autocompleting text input that uses the
 * Cargo 'cargoautocomplete' API.
 *
 * Based heavily on the pf.AutocompleteWidget function/class defined in the
 * Page Forms extension.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 *
 * @constructor
 * @param {Object} config Configuration options
 * @author Yaron Koren
 */

CargoSearchAutocompleteWidget = function ( config ) {
	// Parent constructor
	var textInputConfig = {
		name: config.name,
		// This turns off the local, browser-based autocompletion,
		// which would normally suggest values that the user has
		// typed before on that computer.
		autocomplete: false
	};
	if ( config.value !== undefined ) {
		textInputConfig.value = config.value;
	}
	OO.ui.TextInputWidget.call( this, textInputConfig );
	// Mixin constructors
	OO.ui.mixin.LookupElement.call( this, {} );

	this.config = config;

	// dataCache will temporarily store entity id => entity data mappings of
	// entities, so that if we somehow then alter the text (add characters,
	// remove some) and then adjust our typing to form a known item,
	// it'll recognize it and know what the id was, without us having to
	// select it anew
	this.dataCache = {};
};

OO.inheritClass( CargoSearchAutocompleteWidget, OO.ui.TextInputWidget );
OO.mixinClass( CargoSearchAutocompleteWidget, OO.ui.mixin.LookupElement );

/**
 * @inheritdoc
 */
CargoSearchAutocompleteWidget.prototype.getLookupRequest = function () {
	var
		value = this.getValue(),
		deferred = $.Deferred(),
		api,
		requestParams;

	api = new mw.Api();
	requestParams = {
		action: 'cargoautocomplete',
		format: 'json',
		substr: value
	};

	requestParams.table = this.config.table;
	requestParams.field = this.config.field;
	requestParams.field_is_array = this.config.field_is_array;
	requestParams.where = this.config.where;

	return api.get( requestParams );
};
/**
 * @inheritdoc
 */
CargoSearchAutocompleteWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response || [];
};
/**
 * @inheritdoc
 */
CargoSearchAutocompleteWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	var i,
		item,
		items = [];

	data = data.cargoautocomplete;
	if ( this.maxSuggestions !== undefined ) {
		data = data.slice( 0, this.maxSuggestions - 1 );
	}
	if ( !data ) {
		return [];
	} else if ( data.length === 0 ) {
		// Generate a disabled option with a helpful message in case no results are found.
		return [
			new OO.ui.MenuOptionWidget( {
				disabled: true,
				label: mw.message( 'pf-select2-no-matches' ).text()
			} )
		];
	}
	for ( i = 0; i < data.length; i++ ) {
		item = new OO.ui.MenuOptionWidget( {
			// this data will be passed to onLookupMenuChoose when item is selected
			data: data[ i ],
			label: this.highlightText( data[ i ] )
		} );
		items.push( item );
	}
	return items;
};

CargoSearchAutocompleteWidget.prototype.highlightText = function ( suggestion ) {
	var searchTerm = this.getValue();
	var searchRegexp = new RegExp( '(?![^&;]+;)(?!<[^<>]*)(' +
		searchTerm.replace( /([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, '\\$1' ) +
		')(?![^<>]*>)(?![^&;]+;)', 'gi' );
	var itemLabel = suggestion;
	var loc = itemLabel.search( searchRegexp );
	var t;

	if ( loc >= 0 ) {
		t = itemLabel.slice( 0, Math.max( 0, loc ) ) +
			'<strong>' + itemLabel.substr( loc, searchTerm.length ) + '</strong>' +
			itemLabel.slice( loc + searchTerm.length );
	} else {
		t = itemLabel;
	}

	return new OO.ui.HtmlSnippet( t );
};

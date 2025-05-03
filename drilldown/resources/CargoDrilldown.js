/**
 * CargoDrilldown.js
 *
 * Javascript code for use in the Cargo extension's Special:Drilldown page.
 *
 * Based heavily on the Semantic Drilldown extension's SemanticDrilldown.js
 * file.
 *
 * @author Sanyam Goyal
 * @author Yaron Koren
 */
( function () {

	jQuery.fn.toggleCDValuesDisplay = function () {
		$valuesDiv = jQuery( this ).closest( '.drilldown-filter' )
			.find( '.drilldown-filter-values' );
		if ( $valuesDiv.css( 'display' ) === 'none' ) {
			$valuesDiv.css( 'display', 'block' );
			const downArrowImage = mw.config.get( 'cgDownArrowImage' );
			this.find( 'img' ).attr( 'src', downArrowImage );
		} else {
			$valuesDiv.css( 'display', 'none' );
			const rightArrowImage = mw.config.get( 'cgRightArrowImage' );
			this.find( 'img' ).attr( 'src', rightArrowImage );
		}
	};

	jQuery.fn.showDifferentColors = function () {
		$( this ).each( function () {
			if ( $( this ).attr( 'id' ) % 2 === 0 ) {
				$( this ).attr( 'style', 'background-color:#faf1f1;' );
			} else if ( $( this ).attr( 'id' ) % 2 === 1 ) {
				$( this ).attr( 'style', 'background-color:#f0f9f1;' );
			}
		} );
	};

	jQuery.fn.CDFullTextSearch = function () {
		const searchInput = new OO.ui.SearchInputWidget( {
			type: 'search',
			name: '_search',
			value: $( this ).attr( 'data-search-term' )
		} );
		const searchButton = new OO.ui.ButtonInputWidget( {
			type: 'submit',
			label: mw.msg( 'cargo-drilldown-search' ),
			flags: 'progressive'
		} );
		const searchLayout = new OO.ui.ActionFieldLayout(
			searchInput, searchButton, {
				align: 'top'
			}
		);
		$( this ).html( searchLayout.$element );
	};

	jQuery.fn.CDRemoteAutocomplete = function () {
		const config = {
			name: $( this ).attr( 'data-input-name' ),
			table: $( this ).attr( 'data-cargo-table' ),
			field: $( this ).attr( 'data-cargo-field' ),
			where: $( this ).attr( 'data-cargo-where' )
		};
		const autocompleteInput = new CargoSearchAutocompleteWidget( config );
		const searchButton = new OO.ui.ButtonInputWidget( {
			type: 'submit',
			label: mw.msg( 'cargo-drilldown-search' ),
			flags: 'progressive'
		} );
		const autocompleteLayout = new OO.ui.ActionFieldLayout(
			autocompleteInput, searchButton, {
				label: mw.msg( 'cargo-drilldown-othervalues' ),
				align: 'top'
			}
		);
		$( this ).html( autocompleteLayout.$element );
	};

}() );

$( () => {
	const viewport = '<meta name="viewport" content="width=device-width,initial-scale=1">';
	$( 'head' ).append( viewport );

	$( '.cargoDrilldownRemoteAutocomplete' ).each( function () {
		$( this ).CDRemoteAutocomplete();
	} );
	jQuery( '.drilldown-values-toggle' ).on( 'click', function () {
		$( this ).toggleCDValuesDisplay();
	} );
	jQuery( '.drilldown-parent-tables-value, .drilldown-parent-filters-wrapper' ).showDifferentColors();
	jQuery( '.cargoDrilldownFullTextSearch' ).each( function () {
		$( this ).CDFullTextSearch();
	} );

	const maxWidth = window.matchMedia( '(max-width: 549px)' );

	function mobileView( maxWidth ) {
		if ( maxWidth.matches ) {
			const menu_icon = "<a class='menu_header' id='menu'><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\">\n" +
				'            <path d="M2 6h20v3H2zm0 5h20v3H2zm0 5h20v3H2z"/>\n' +
				'          </svg>' +
				'       </a>',
				div = "<div id='header'> </div>";
			$( '#bodyContent' ).before( div );
			$( '#header' ).append( menu_icon );
			$( '#header' ).append( $( '#firstHeading' ) );

			let menu = $( '#menu' ),
				main = $( '#content, #mw-navigation' ),
				drawer = $( '#drilldown-tables-tabs-wrapper' ),
				formatTabsWrapper = $( '#drilldown-format-tabs-wrapper' );
			if ( drawer.length != 0 ) {
				$( 'body' ).prepend( drawer );
			} else {
				$( 'body' ).prepend( "<div id='drilldown-tables-tabs-wrapper'></div>" );
				drawer = $( '#drilldown-tables-tabs-wrapper' );
			}
			if ( formatTabsWrapper ) {
				const formatLabel = '<p id="formatTabsHeader">Format:</p>';
				drawer.append( formatTabsWrapper );
				formatTabsWrapper.prepend( formatLabel );
			}

			menu.click( ( e ) => {
				drawer.toggleClass( 'open' );
				e.stopPropagation();
			} );
			main.click( () => {
				drawer.removeClass( 'open' );
			} );

			const mapCanvas = $( '.mapCanvas' );
			if ( mapCanvas.length ) {
				const mapWidth = mapCanvas.width(),
					zoom = 1 - ( mapWidth / 700 );
				mapCanvas.css( 'zoom', zoom );
			}

			const tableTabsHeader = $( '#tableTabsHeader' ),
				formatTabsHeader = $( '#formatTabsHeader' ),

				rightArrowImage = mw.config.get( 'cgRightArrowImage' ),
				downArrowImage = mw.config.get( 'cgDownArrowImage' ),
				arrow = "<img src=''>\t";
			tableTabsHeader.prepend( arrow );
			formatTabsHeader.prepend( arrow );

			const tableTabs = $( '#drilldown-tables-tabs' );
			if ( formatTabsWrapper.length != 0 ) {
				formatTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
				tableTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
				tableTabs.toggleClass( 'hide' );
			} else {
				tableTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
				formatTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
			}

			tableTabsHeader.click( ( e ) => {
				if ( tableTabs.hasClass( 'hide' ) ) {
					tableTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
					tableTabs.removeClass( 'hide' );
				} else {
					tableTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
					tableTabs.toggleClass( 'hide' );
				}
				e.stopPropagation();
			} );

			const formatTabs = $( '#drilldown-format-tabs' );
			formatTabsHeader.click( ( e ) => {
				if ( formatTabs.hasClass( 'hide' ) ) {
					formatTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
					formatTabs.removeClass( 'hide' );
				} else {
					formatTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
					formatTabs.toggleClass( 'hide' );
				}
				e.stopPropagation();
			} );

		}
	}

	mobileView( maxWidth ); // Call listener function at run time
	maxWidth.addListener( mobileView );

} );

<?php
/**
 * Adds Exhibit format to Cargo queries.
 *
 * @author @lmorillas
 */

class CargoExhibitFormat extends CargoDeferredFormat {

	function allowedParameters() {
		return array( 'view', 'facets', 'end', 'color', 'topunit', 'toppx', 'bottompx', 'datalabel' );
	}

	/**
	 * @param string $p
	 * @return string
	 **/
	function prependDot( $p ) {
		return '.' . trim($p);
	}

	function createMap( $sqlQueries ){
		$maps_script = '<link rel="exhibit-extension" href="http://api.simile-widgets.org/exhibit/HEAD/extensions/map/map-extension.js"/>';
		$this->mOutput->addHeadItem( $maps_script, $maps_script );

		if ( ! array_key_exists( "latlng", $this->displayParams ) ) {
			$coordFields = $this->getCoordinatesFields( $sqlQueries );
			if ( count( $coordFields ) > 0 ){
				$this->displayParams['latlng'] = $coordFields[0];
			}
		}

		$attrs = array(
			'data-ex-role' => 'view',
			'data-ex-view-class' => "Map",
			'data-ex-latlng' => $this->prependDot( $this->displayParams['latlng'] ),
			'data-ex-autoposition' => "true",
		);

		if ( array_key_exists( "color", $this->displayParams ) ) {
			$attrs["data-ex-color-key"] = $this->prependDot($this->displayParams['color']);
		}

		return Html::element( 'div', $attrs );
	}

	function createTimeline( $sqlQueries ){
		$timeline_script = '<link rel="exhibit-extension" href="http://api.simile-widgets.org/exhibit/HEAD/extensions/time/time-extension.js"/>';
		$this->mOutput->addHeadItem( $timeline_script, $timeline_script );

		// div
		$attrs = array();
		$attrs['data-ex-role'] = 'view';
		$attrs["data-ex-view-class"] = "Timeline";

		if ( ! array_key_exists( "start", $this->displayParams ) ) {
			$dateFields = $this->getDateFields( $sqlQueries );
			if ( count( $dateFields ) > 0 ) {
				$this->displayParams['start'] = $dateFields[0];
			}
		}

		$attrs["data-ex-start"] = $this->prependDot( $this->displayParams['start'] );

		if ( array_key_exists( "end", $this->displayParams ) ) {
			$attrs["data-ex-end"] = $this->prependDot( $this->displayParams['end'] );
		}
		if ( array_key_exists( "color", $this->displayParams ) ) {
			$attrs["data-ex-color-key"] = $this->prependDot( $this->displayParams['color'] );
		}
		if ( array_key_exists( "topunit", $this->displayParams ) ) {
			$attrs["data-ex-top-band-unit"] = $this->displayParams['topunit'];
		}
		if ( array_key_exists( "toppx", $this->displayParams ) ) {
			$attrs["data-ex-top-band-pixels-per-unit"] = $this->displayParams['toppx'];
		}
		if ( array_key_exists( "bottompx", $this->displayParams ) ) {
			$attrs["data-ex-bottom-band-pixels-per-unit"] = $this->displayParams['bottompx'];
		}
		return Html::element( 'div', $attrs );
	}

	/**
	* @param $fields_list array
	*
	*/
	function createTabular( $fieldList ) {
		$columnsList = array();
		foreach ( $fieldList as $field ) {
			if ( strpos( $field, '__' ) == false ) {
				$columnsList[] = $field;
			}
		}

		$attrs = array(
			'data-ex-role' => 'view',
			'data-ex-view-class' => 'Tabular',
			'data-ex-paginate' => "true",
			'data-ex-table-styler' => "tableStyler",

			'data-ex-columns' => implode(',',
				array_map( "CargoExhibitFormat::prependDot", $columnsList ) ),

			'data-ex-column-labels' => implode( ',', array_map( "ucfirst", $columnsList ) )
		);

		return Html::element( 'div', $attrs );
	}

	function createFacets( $facets ){
		// Explode facets and create the div for each of them.
		$text = $this->createSearch();
		foreach ( $facets as $f ) {
			 $attrs = array(
				'data-ex-role' => "facet",
				'data-ex-collapsible' => "true",
				'data-ex-expression' => '.' . $f,
				'data-ex-show-missing' => 'false',
				'data-ex-facet-label' => ucfirst( $f ),
				'style' => "float: left; width: 24%; margin: 0 1% 0 0;"
			);
			$text .= Html::element( 'div', $attrs );
		}
		return Html::rawElement( 'div', array( "class" => "facets", "style" => "overflow: hidden; width: 100%;" ), $text );
	}

	/**
	* @param string $title
	* @return string
	*/
	function createSearch( ) {
		$attrs = array(
			'data-ex-role' => "exhibit-facet",
			'data-ex-facet-class' => "TextSearch",
			'data-ex-facet-label' => wfMessage( 'search' )->text(),
			'style' => "float: left; width: 24%; margin: 0 1% 0 0;"
		);
		return Html::element( 'div', $attrs );
	}

	function createLens( $fieldList ) {
		$lensBody = '<caption><strong data-ex-content=".label"></strong></caption>';
		foreach( $fieldList as $field ) {
			if ( $field != "label" && strpos( $field, '__' ) === false &&
			strpos( $field, '  ' ) === false) {
				$th = "<strong>" . ucfirst( $field ) . "</strong>";
				$lensBody .= "<tr data-ex-if-exists=\".$field\"><td>$th</td><td data-ex-content=\".$field\"></td></tr>";
			}
		}
		$tableAttrs = array(
			'data-ex-role' => 'lens',
			'class' => 'cargoTable',
			'style' => "display: none; width: 100%;"
		);
		return Html::rawElement( 'table', $tableAttrs, $lensBody );
	}

	/**
	 *
	 * @param array $valuesTable
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 * @throws MWException
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		global $cgScriptPath;

		$this->mOutput->addModules( 'ext.cargo.exhibit' );
		$this->mOutput->addModuleStyles( 'ext.cargo.main' );

		$exhibit_busy = $cgScriptPath . "/skins/loading.gif";
		// The "loading" message is just alt-text, so it doesn't really
		// matter that it's hardcoded in English.
		$text = '<img id="loading_exhibit" src="'. $exhibit_busy .'" alt="Loading Exhibit" style="display: none;" >';

		$field_list = array();
		foreach ( $sqlQueries as $sqlQuery ) {
			foreach ( $sqlQuery->mAliasedFieldNames as $alias => $fieldName ) {
				$field_list[] = $alias;
			}
		}

		$csv_properties = '';
		if ( ! in_array( "label", $field_list ) ) {
			// first field will be label!
			$field_list[0] = 'label';
			$csv_properties = 'data-ex-properties="' . implode( ',', $field_list ) . '"';
		}

		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'csv';

		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$dataurl = htmlentities( $ce->getFullURL( $queryParams ) );

		// Data imported as csv
		$datalink = "<link href=\"$dataurl\" type=\"text/csv\" rel=\"exhibit/data\" data-ex-has-column-titles=\"true\" $csv_properties />";

		$this->mOutput->addHeadItem( $datalink, $datalink );

		$this->displayParams = $displayParams;

		// lens
		$text .= $this->createLens( $field_list );

		// Facets
		if ( array_key_exists( 'facets', $displayParams ) ) {
			$facets = array_map('trim', explode( ',' , $displayParams['facets'] ) );
			$text .= $this->createFacets( $facets );
		} else {
			$text .= $this->createFacets( array_slice( $field_list, 0, 3 ) );
		}

		if ( array_key_exists( 'datalabel', $displayParams ) ) {
			$datalabel = trim( $displayParams['datalabel'] );
			// What is this used for?
			$dataplural = $datalabel . 's';
			$data_label_link = <<<EOLABEL
<link href="#cargoExhibit" type="text/html" rel="exhibit/data"
data-ex-item-type="Item" data-ex-label="$datalabel" data-ex-plural-label="$dataplural" />
EOLABEL;
			$this->mOutput->addHeadItem( $data_label_link, $data_label_link );
		}

		// View
		$this->views = array();

		if ( array_key_exists( 'view', $displayParams ) ) {
			$this->views = array_map( 'ucfirst', array_map( 'trim', explode( ',', $displayParams['view'] ) ) );
		} else {
			$this->automateViews( $sqlQueries );
		}

		$text_views = "";

		foreach ( $this->views as $view ) {
			switch ( $view ) {
				case "Timeline":
					$text_views .= $this->createTimeline( $sqlQueries );
					break;
				case "Map":
					$text_views .= $this->createMap( $sqlQueries );
					break;
				case "Tabular":
					$text_views .= $this->createTabular($field_list);
				}
			}

		if ( count( $this->views ) > 1 ){
			$text .= Html::rawElement( 'div',
				array( 'data-ex-role' => "viewPanel" ),
				$text_views );
		} else {
			$text .= $text_views ;
		}

		return '<div id="cargoExhibit">' . $text . '</div>' ;
	}

	/**
	* Initializes $this->views[]
	*/
	function automateViews( $sqlQueries ){
		// map ?
		$coordFields = $this->getCoordinatesFields( $sqlQueries );
		if ( count( $coordFields ) > 0 ){
			$this->views[] = 'Map';
			$this->displayParams['latlng'] = $coordFields[0];
		}

		// timeline ?
		$dateFields = $this->getDateFields( $sqlQueries );
		if ( count( $dateFields ) > 0 ){
			$this->views[] = 'Timeline';
			$this->displayParams['start'] = $dateFields[0];
		}

		$this->views[] = 'Tabular';
	}

	function getCoordinatesFields( $sqlQueries ){
		$coordinatesFields = array();

		foreach ( $sqlQueries as $query){
			$fieldDescriptions = $query->mFieldDescriptions;
			foreach ( $fieldDescriptions as $field => $description ) {
				if ( $description->mType == 'Coordinates' ) {
					$coordinatesFields[] = $field;
				}
			}
		}
		return $coordinatesFields;
	}

	function getDateFields( $sqlQueries ){
		$dateFields = array();

		foreach ( $sqlQueries as $query){
			$fieldDescriptions = $query->mFieldDescriptions;
			foreach ( $fieldDescriptions as $field => $description ) {
				if ( $description->mType == 'Date' || $description->mType == 'Datetime' ) {
					$dateFields[] = $field;
				}
			}
		}
		return $dateFields;
	}
}

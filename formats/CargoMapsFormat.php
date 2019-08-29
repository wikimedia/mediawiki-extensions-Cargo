<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoMapsFormat extends CargoDisplayFormat {

	public static $mappingService = "OpenLayers";
	public static $mapNumber = 1;

	function __construct( $output ) {
		global $wgCargoDefaultMapService;
		parent::__construct( $output );
		self::$mappingService = $wgCargoDefaultMapService;
	}

	public static function allowedParameters() {
		return array(
			'height' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ),
			'width' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ),
			'icon' => array( 'type' => 'string' ),
			'zoom' => array( 'type' => 'int' )
		);
	}

	public static function getScripts() {
		global $wgCargoDefaultMapService;
		if ( $wgCargoDefaultMapService == 'Google Maps' ) {
			return CargoGoogleMapsFormat::getScripts();
		} elseif ( $wgCargoDefaultMapService == 'OpenLayers' ) {
			return CargoOpenLayersFormat::getScripts();
		} else {
			return array();
		}
	}

	/**
	 * Based on the Maps extension's getFileUrl().
	 */
	public static function getImageURL( $imageName ) {
		$title = Title::makeTitle( NS_FILE, $imageName );

		if ( $title == null || !$title->exists() ) {
			return null;
		}

		$imagePage = new ImagePage( $title );
		return $imagePage->getDisplayedFile()->getURL();
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
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$coordinatesFields = array();
		foreach ( $fieldDescriptions as $field => $description ) {
			if ( $description->mType == 'Coordinates' ) {
				$coordinatesFields[] = $field;
			}
		}

		if ( count( $coordinatesFields ) == 0 ) {
			throw new MWException( "Error: no fields of type \"Coordinate\" were specified in this "
			. "query; cannot display in a map." );
		}

		// @TODO - should this check be higher up, i.e. for all
		// formats?
		if ( count( $formattedValuesTable ) == 0 ) {
			throw new MWException( "No results found for this query; not displaying a map." );
		}

		// Add necessary JS scripts.
		$scripts = $this->getScripts();
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$this->mOutput->addHeadItem( $scriptsHTML, $scriptsHTML );
		$this->mOutput->addModules( 'ext.cargo.maps' );

		// Construct the table of data we will display.
		$valuesForMap = array();
		foreach ( $formattedValuesTable as $i => $valuesRow ) {
			$displayedValuesForRow = array();
			foreach ( $valuesRow as $fieldName => $fieldValue ) {
				if ( !array_key_exists( $fieldName, $fieldDescriptions ) ) {
					continue;
				}
				$fieldType = $fieldDescriptions[$fieldName]->mType;
				if ( $fieldType == 'Coordinates' || $fieldType == 'Coordinates part' ) {
					// Actually, we can ignore these.
					continue;
				}
				if ( $fieldValue == '' ) {
					continue;
				}
				$displayedValuesForRow[$fieldName] = $fieldValue;
			}
			// There could potentially be more than one
			// coordinate for this "row".
			// @TODO - handle lists of coordinates as well.
			foreach ( $coordinatesFields as $coordinatesField ) {
				$coordinatesField = str_replace( ' ', '_', $coordinatesField );
				$latValue = $valuesRow[$coordinatesField . '  lat'];
				$lonValue = $valuesRow[$coordinatesField . '  lon'];
				// @TODO - enforce the existence of a field
				// besides the coordinates field(s).
				$firstValue = array_shift( $displayedValuesForRow );
				if ( $latValue != '' && $lonValue != '' ) {
					$valuesForMapPoint = array(
						// 'name' has no formatting
						// (like a link), while 'title'
						// might.
						'name' => array_shift( $valuesTable[$i] ),
						'title' => $firstValue,
						'lat' => $latValue,
						'lon' => $lonValue,
						'otherValues' => $displayedValuesForRow
					);
					if ( array_key_exists( 'icon', $displayParams ) &&
						array_key_exists( $i, $displayParams['icon'] ) ) {
						$iconURL = self::getImageURL( $displayParams['icon'][$i] );
						if ( !is_null( $iconURL ) ) {
							$valuesForMapPoint['icon'] = $iconURL;
						}
					}
					$valuesForMap[] = $valuesForMapPoint;
				}
			}
		}

		$service = self::$mappingService;
		$jsonData = json_encode( $valuesForMap, JSON_NUMERIC_CHECK | JSON_HEX_TAG );
		$divID = "mapCanvas" . self::$mapNumber++;

		if ( array_key_exists( 'height', $displayParams ) ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
		} else {
			$height = "400px";
		}
		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "700px";
		}

		// The 'map data' element does double duty: it holds the full
		// set of map data, as well as, in the tag attributes,
		// settings related to the display, including the mapping
		// service to use.
		$mapDataAttrs = array(
			'class' => 'cargoMapData',
			'style' => 'display: none',
			'mappingService' => $service
		);
		if ( array_key_exists( 'zoom', $displayParams ) ) {
			$mapDataAttrs['zoom'] = $displayParams['zoom'];
		}
		$mapData = Html::element( 'span', $mapDataAttrs, $jsonData );

		$mapCanvasAttrs = array(
			'class' => 'mapCanvas',
			'style' => "height: $height; width: $width;",
			'id' => $divID,
		);
		$mapCanvas = Html::rawElement( 'div', $mapCanvasAttrs, $mapData );
		return $mapCanvas;
	}

}

<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;
use MediaWiki\Title\Title;

class CargoMapsFormat extends CargoDisplayFormat {

	public static $mappingService = "OpenLayers";
	public static $mapNumber = 1;

	public function __construct( $output ) {
		global $wgCargoDefaultMapService;
		parent::__construct( $output );
		self::$mappingService = $wgCargoDefaultMapService;
	}

	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ],
			'icon' => [ 'type' => 'string' ],
			'zoom' => [ 'type' => 'int' ],
			'center' => [ 'type' => 'string' ],
			'cluster' => [ 'type' => 'string' ]
		];
	}

	public static function getScripts() {
		global $wgCargoDefaultMapService;
		if ( $wgCargoDefaultMapService == 'Google Maps' ) {
			return CargoGoogleMapsFormat::getScripts();
		} elseif ( $wgCargoDefaultMapService == 'OpenLayers' ) {
			return CargoOpenLayersFormat::getScripts();
		} elseif ( $wgCargoDefaultMapService == 'Leaflet' ) {
			return CargoLeafletFormat::getScripts();
		} else {
			return [];
		}
	}

	public static function getStyles() {
		global $wgCargoDefaultMapService;
		if ( $wgCargoDefaultMapService == 'Leaflet' ) {
			return CargoLeafletFormat::getStyles();
		} else {
			return [];
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

	public function getImageData( $fileName ) {
		global $wgUploadDirectory;

		if ( $fileName == '' ) {
			return null;
		}
		$fileTitle = Title::makeTitleSafe( NS_FILE, $fileName );
		if ( !$fileTitle->exists() ) {
			throw new MWException( "Error: File \"$fileName\" does not exist on this wiki." );
		}
		$imagePage = new ImagePage( $fileTitle );
		$file = $imagePage->getDisplayedFile();
		$filePath = $wgUploadDirectory . '/' . $file->getUrlRel();
		[ $imageWidth, $imageHeight, $type, $attr ] = getimagesize( $filePath );
		return [ $imageWidth, $imageHeight, $file->getUrl() ];
	}

	/**
	 * @param array $valuesTable
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 * @throws MWException
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$coordinatesFields = [];
		$latField = null;
		$lonField = null;
		$urlField = null;
		foreach ( $fieldDescriptions as $field => $description ) {
			if ( $description->mType == 'Coordinates' ) {
				$coordinatesFields[] = $field;
			}
			if ( $field == 'lat' ) {
				$latField = $field;
			} elseif ( $field == 'lon' ) {
				$lonField = $field;
			} elseif ( $field == 'URL' ) {
				$urlField = $field;
			}
		}

		if ( count( $coordinatesFields ) == 0 && ( $latField == null || $lonField == null ) ) {
			throw new MWException( "Error: no fields of type \"Coordinates\" were specified in this "
			. "query; cannot display in a map." );
		}

		// @TODO - should this check be higher up, i.e. for all
		// formats?
		if ( count( $formattedValuesTable ) == 0 ) {
			throw new MWException( "No results found for this query; not displaying a map." );
		}

		// Add necessary JS scripts and CSS styles.
		$scripts = $this->getScripts();
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$styles = $this->getStyles();
		$stylesHTML = '';
		foreach ( $styles as $style ) {
			$stylesHTML .= Html::linkedStyle( $style );
		}
		$this->mOutput->addHeadItem( $scriptsHTML, $scriptsHTML );
		$this->mOutput->addHeadItem( $stylesHTML, $stylesHTML );
		$this->mOutput->addModules( [ 'ext.cargo.maps' ] );

		// Construct the table of data we will display.
		$valuesForMap = [];
		foreach ( $formattedValuesTable as $i => $valuesRow ) {
			$displayedValuesForRow = [];
			foreach ( $valuesRow as $fieldName => $fieldValue ) {
				if ( !array_key_exists( $fieldName, $fieldDescriptions ) ) {
					continue;
				}
				$fieldType = $fieldDescriptions[$fieldName]->mType;
				// Don't display any values that are going to be included already.
				if ( $fieldType == 'Coordinates' || $fieldType == 'Coordinates part' || $fieldName == $latField || $fieldName == $lonField || $fieldName == $urlField ) {
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
				$latValue = $valuesRow[$coordinatesField . '  lat'] ?? null;
				$lonValue = $valuesRow[$coordinatesField . '  lon'] ?? null;
				if ( $latValue != '' && $lonValue != '' ) {
					$nameValue = array_shift( $valuesTable[$i] );
					// @TODO - enforce the existence of a field
					// besides the coordinates field(s).
					$firstValue = array_shift( $displayedValuesForRow );
					$valuesForMap[] = self::getMapPointValues( $nameValue, $firstValue, $latValue, $lonValue, $displayedValuesForRow, $displayParams, $i );
				}
			}

			if ( $latField !== null && $lonField !== null ) {
				$latValue = $valuesRow[$latField] ?? null;
				$lonValue = $valuesRow[$lonField] ?? null;
				$urlValue = $valuesRow[$urlField] ?? null;
				if ( $latValue != '' && $lonValue != '' ) {
					$nameValue = array_shift( $valuesTable[$i] );
					$titleValue = array_shift( $displayedValuesForRow );
					if ( $urlValue !== null ) {
						$titleValue = Html::element( 'a', [ 'href' => $urlValue ], $titleValue );
					}
					$valuesForMap[] = self::getMapPointValues( $nameValue, $titleValue, $latValue, $lonValue, $displayedValuesForRow, $displayParams, $i );
				}
			}
		}

		$service = self::$mappingService;
		$jsonData = json_encode( $valuesForMap, JSON_NUMERIC_CHECK | JSON_HEX_TAG );
		$divID = "mapCanvas" . self::$mapNumber++;

		if ( $service == 'Leaflet' && array_key_exists( 'image', $displayParams ) ) {
			$fileName = $displayParams['image'];
			$imageData = $this->getImageData( $fileName );
			if ( $imageData == null ) {
				$fileName = null;
			} else {
				[ $imageWidth, $imageHeight, $imageURL ] = $imageData;
			}
		} else {
			$fileName = null;
		}

		if ( array_key_exists( 'height', $displayParams ) && $displayParams['height'] != '' ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
		} else {
			$height = null;
		}

		if ( array_key_exists( 'width', $displayParams ) && $displayParams['width'] != '' ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = null;
		}

		if ( $fileName !== null ) {
			// Do some scaling of the image, if necessary.
			if ( $height !== null && $width !== null ) {
				// Reduce image if it doesn't fit into the
				// assigned rectangle.
				$heightRatio = (int)$height / $imageHeight;
				$widthRatio = (int)$width / $imageWidth;
				$smallerRatio = min( $heightRatio, $widthRatio );
				if ( $smallerRatio < 1 ) {
					$imageHeight *= $smallerRatio;
					$imageWidth *= $smallerRatio;
				}
			} else {
				// Reduce image if it's too big.
				$maxDimension = max( $imageHeight, $imageWidth );
				$maxAllowedSize = 1000;
				if ( $maxDimension > $maxAllowedSize ) {
					$imageHeight *= $maxAllowedSize / $maxDimension;
					$imageWidth *= $maxAllowedSize / $maxDimension;
				}
				$height = $imageHeight . 'px';
				$width = $imageWidth . 'px';
			}
		} else {
			if ( $height == null ) {
				$height = "400px";
			}
			if ( $width == null ) {
				$width = "700px";
			}
		}

		// The 'map data' element does double duty: it holds the full
		// set of map data, as well as, in the tag attributes,
		// settings related to the display, including the mapping
		// service to use.
		$mapDataAttrs = [
			'class' => 'cargoMapData',
			'style' => 'display: none',
			'data-mapping-service' => $service
		];
		if ( array_key_exists( 'zoom', $displayParams ) && $displayParams['zoom'] != '' ) {
			$mapDataAttrs['data-zoom'] = $displayParams['zoom'];
		}
		if ( array_key_exists( 'center', $displayParams ) && $displayParams['center'] != '' ) {
			$mapDataAttrs['data-center'] = $displayParams['center'];
		}
		if ( array_key_exists( 'cluster', $displayParams ) ) {
			$mapDataAttrs['data-cluster'] = strtolower( $displayParams['cluster'] );
		}
		if ( $fileName !== null ) {
			$mapDataAttrs['data-image-path'] = $imageURL;
			$mapDataAttrs['data-height'] = $imageHeight;
			$mapDataAttrs['data-width'] = $imageWidth;
		}

		$mapData = Html::element( 'span', $mapDataAttrs, $jsonData );

		$mapCanvasAttrs = [
			'class' => 'mapCanvas',
			'style' => "height: $height; width: $width;",
			'id' => $divID,
		];
		$mapCanvas = Html::rawElement( 'div', $mapCanvasAttrs, $mapData );
		return $mapCanvas;
	}

	public static function getMapPointValues( $nameValue, $titleValue, $latValue, $lonValue, $displayedValuesForRow, $displayParams, $rowNum ) {
		$valuesForMapPoint = [
			// 'name' has no formatting (like a link), while 'title' might.
			'name' => $nameValue,
			'title' => $titleValue,
			'lat' => $latValue,
			'lon' => $lonValue,
			'otherValues' => $displayedValuesForRow
		];
		if ( array_key_exists( 'icon', $displayParams ) ) {
			$iconFileName = null;
			if ( is_array( $displayParams['icon'] ) ) {
				// Compound query.
				if ( array_key_exists( $rowNum, $displayParams['icon'] ) ) {
					$iconFileName = $displayParams['icon'][$rowNum];
				}
			} else {
				// Regular query.
				$iconFileName = $displayParams['icon'];
			}
			if ( $iconFileName !== null ) {
				$iconURL = self::getImageURL( $iconFileName );
				if ( $iconURL !== null ) {
					$valuesForMapPoint['icon'] = $iconURL;
				}
			}
		}

		return $valuesForMapPoint;
	}

}

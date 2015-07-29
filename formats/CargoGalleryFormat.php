<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoGalleryFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array( 'mode' );
	}

	function getFileTitles( $valuesTable, $fieldDescriptions ) {
		$fileField = null;
		foreach ( $fieldDescriptions as $field => $fieldDesc ) {
			if ( $fieldDesc->mType == 'File' ) {
				$fileField = $field;
				break;
			}
		}

		// If there's no 'File' field in the schema, just use the
		// page name.
		if ( $fileField == null ) {
			$usingPageName = true;
			$fileField = '_pageName';
		} else {
			$usingPageName = false;
		}

		$fileNames = array();
		foreach ( $valuesTable as $row ) {
			if ( array_key_exists( $fileField, $row ) ) {
				$fileNames[] = $row[$fileField];
			}
		}

		$fileTitles = array();
		foreach( $fileNames as $fn ) {
			if ( $usingPageName ) {
				$title = Title::newFromText( $fn );
				if ( $title == null || $title->getNamespace() != NS_FILE ) {
					continue;
				}
			} else {
				$title = Title::makeTitleSafe( NS_FILE, $fn );
				if ( $title == null ) {
					continue;
				}
			}
			$fileTitles[] = $title;
		}

		return $fileTitles;
	}

	/**
	 *
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams Unused
	 * @return string HTML
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$fileTitles = self::getFileTitles( $valuesTable, $fieldDescriptions );

		// Display mode - can be 'traditional'/null, 'nolines',
		// 'packed', 'packed-overlay' or 'packed-hover'; see
		// https://www.mediawiki.org/wiki/Help:Images#Mode_parameter
		$mode = ( array_key_exists( 'mode', $displayParams ) ) ?
			$displayParams['mode'] : null;

		try {
			// @TODO - it would be nice to pass in a context here,
			// if that's possible.
			$gallery = ImageGalleryBase::factory( $mode );
		} catch ( MWException $e ) {
			// User specified something invalid, fallback to default.
			$gallery = ImageGalleryBase::factory( false );
		}

		foreach ( $fileTitles as $title ) {
			$gallery->add( $title );
		}

		$text = "<div id=\"mw-category-media\">\n";
		$text .= $gallery->toHTML();
		$text .= "\n</div>";

		return $text;
	}

}

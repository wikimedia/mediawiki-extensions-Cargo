<?php
/**
 * CargoQueryDisplayer - class for displaying query results.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQueryDisplayer {

	public $mSQLQuery;
	public $mFormat;
	public $mDisplayParams = array();
	public $mParser = null;
	public $mFieldDescriptions;
	public $mFieldTables;

	public static function newFromSQLQuery( $sqlQuery ) {
		$cqd = new CargoQueryDisplayer();
		$cqd->mSQLQuery = $sqlQuery;
		$cqd->mFieldDescriptions = $sqlQuery->mFieldDescriptions;
		$cqd->mFieldTables = $sqlQuery->mFieldTables;
		return $cqd;
	}

	public static function getAllFormatClasses() {
		$formatClasses = array(
			'list' => 'CargoListFormat',
			'ul' => 'CargoULFormat',
			'ol' => 'CargoOLFormat',
			'template' => 'CargoTemplateFormat',
			'embedded' => 'CargoEmbeddedFormat',
			'csv' => 'CargoCSVFormat',
			'excel' => 'CargoExcelFormat',
			'json' => 'CargoJSONFormat',
			'outline' => 'CargoOutlineFormat',
			'tree' => 'CargoTreeFormat',
			'table' => 'CargoTableFormat',
			'dynamic table' => 'CargoDynamicTableFormat',
			'googlemaps' => 'CargoGoogleMapsFormat',
			'openlayers' => 'CargoOpenLayersFormat',
			'calendar' => 'CargoCalendarFormat',
			'timeline' => 'CargoTimelineFormat',
			'category' => 'CargoCategoryFormat',
			'bar chart' => 'CargoBarChartFormat',
			'gallery' => 'CargoGalleryFormat',
			'tag cloud' => 'CargoTagCloudFormat',
		);
		return $formatClasses;
	}

	/**
	 * Given a format name, and a list of the fields, returns the name
	 * of the the function to call for that format.
	 */
	public function getFormatClass() {
		$formatClasses = self::getAllFormatClasses();
		if ( array_key_exists( $this->mFormat, $formatClasses ) ) {
			return $formatClasses[$this->mFormat];
		}

		$formatClass = null;
		wfRunHooks( 'CargoGetFormatClass', array( $this->mFormat, &$formatClass ) );
		if ( $formatClass != null ) {
			return $formatClass;
		}

		if ( count( $this->mFieldDescriptions ) > 1 ) {
			$format = 'table';
		} else {
			$format = 'list';
		}
		return $formatClasses[$format];
	}

	public function getFormatter( $out, $parser = null ) {
		$formatClass = $this->getFormatClass();
		$formatObject = new $formatClass( $out, $parser );
		return $formatObject;
	}

	public function getFormattedQueryResults( $queryResults ) {
		// The assignment will do a copy.
		$formattedQueryResults = $queryResults;
		foreach ( $queryResults as $rowNum => $row ) {
			foreach ( $row as $fieldName => $value ) {
				if ( trim( $value ) == '' ) {
					continue;
				}

				if ( !array_key_exists( $fieldName, $this->mFieldDescriptions ) ) {
					continue;
				}

				$fieldDescription = $this->mFieldDescriptions[$fieldName];
				$tableName = $this->mFieldTables[$fieldName];
				$fieldType = $fieldDescription->mType;

				$text = '';
				if ( $fieldDescription->mIsList ) {
					// There's probably an easier way to do
					// this, using array_map().
					$delimiter = $fieldDescription->mDelimiter;
					$fieldValues = explode( $delimiter, $value );
					foreach ( $fieldValues as $i => $fieldValue ) {
						if ( trim( $fieldValue ) == '' ) {
							continue;
						}
						if ( $i > 0 ) {
							$text .= "$delimiter ";
						}
						$text .= self::formatFieldValue( $fieldValue, $fieldType, $fieldDescription, $this->mParser );
					}
				} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
					$datePrecisionField = str_replace( ' ', '_', $fieldName ) . '__precision';
					if ( array_key_exists( $datePrecisionField, $row ) ) {
						$datePrecision = $row[$datePrecisionField];
					} else {
						$fullDatePrecisionField = $tableName . '.' . $datePrecisionField;
						if ( array_key_exists( $fullDatePrecisionField, $row ) ) {
							$datePrecision = $row[$fullDatePrecisionField];
						} else {
							// This should never
							// happen, but if it
							// does - let's just
							// give up.
							$datePrecision = CargoStore::DATE_ONLY;
						}
					}
					$text = self::formatDateFieldValue( $value, $datePrecision, $fieldType );
				} else {
					$text = self::formatFieldValue( $value, $fieldType, $fieldDescription, $this->mParser );
				}
				if ( $text != '' ) {
					$formattedQueryResults[$rowNum][$fieldName] = $text;
				}
			}
		}
		return $formattedQueryResults;
	}

	public static function formatFieldValue( $value, $type, $fieldDescription, $parser ) {
		if ( $type == 'Integer' ) {
			global $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter;
			return number_format( $value, 0, $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter );
		} elseif ( $type == 'Float' ) {
			global $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter;
			// Can we assume that the decimal mark will be a '.' in the database?
			$numDecimalPlaces = strlen( $value ) - strrpos( $value, '.' ) - 1;
			return number_format( $value, $numDecimalPlaces, $wgCargoDecimalMark,
				$wgCargoDigitGroupingCharacter );
		} elseif ( $type == 'Page' ) {
			$title = Title::newFromText( $value );
			return Linker::link( $title );
		} elseif ( $type == 'File' ) {
			// 'File' values are basically pages in the File:
			// namespace; they are displayed as thumbnails within
			// queries.
			$title = Title::newFromText( $value, NS_FILE );
			return Linker::makeThumbLinkObj( $title, wfLocalFile( $title ), $value, '' );
		} elseif ( $type == 'URL' ) {
			if ( array_key_exists( 'link text', $fieldDescription->mOtherParams ) ) {
				return Html::element( 'a', array( 'href' => $value ),
						$fieldDescription->mOtherParams['link text'] );
			} else {
				// Otherwise, do nothing.
				return $value;
			}
		} elseif ( $type == 'Date' || $type == 'Datetime' ) {
			// This should not get called - date fields
			// have a separate formatting function.
			return $value;
		} elseif ( $type == 'Wikitext' || $type == '' ) {
			return CargoUtils::smartParse( $value, $parser );
		}
		// If it's not any of these specially-handled types, just
		// return the value.
		return $value;
	}

	static function formatDateFieldValue( $dateValue, $datePrecision, $type ) {
		$seconds = strtotime( $dateValue );
		if ( $datePrecision == CargoStore::YEAR_ONLY ) {
			// 'o' is better than 'Y' because it does not add
			// leading zeroes to years with fewer than four digits.
			return date( 'o', $seconds );
		} elseif ( $datePrecision == CargoStore::MONTH_ONLY ) {
			return CargoDrilldownUtils::monthToString( date( 'm', $seconds ) ) .
				' ' . date( 'o', $seconds );
		} else {
			// CargoStore::DATE_AND_TIME or
			// CargoStore::DATE_ONLY
			global $wgAmericanDates;
			if ( $wgAmericanDates ) {
				// We use MediaWiki's representation of month
				// names, instead of PHP's, because its i18n
				// support is of course far superior.
				$dateText = CargoDrilldownUtils::monthToString( date( 'm', $seconds ) );
				$dateText .= ' ' . date( 'j, o', $seconds );
			} else {
				$dateText = date( 'o-m-d', $seconds );
			}
			// @TODO - remove the redundant 'Date' check at some
			// point. It's here because the "precision" constants
			// changed a ittle in version 0.8.
			if ( $type == 'Date' || $datePrecision == CargoStore::DATE_ONLY ) {
				return $dateText;
			}

			// It's a Datetime - add time as well.
			// @TODO - have some variable for 24-hour time display?
			$timeText = date( 'g:i:s A', $seconds );
			return "$dateText $timeText";
		}
	}

	public function displayQueryResults( $formatter, $queryResults ) {
		if ( count( $queryResults ) == 0 ) {
			if ( array_key_exists( 'default', $this->mDisplayParams ) ) {
				return $this->mDisplayParams['default'];
			} else {
				return '<em>' . wfMessage( 'table_pager_empty' )->text() . '</em>'; //default
			}
		}

		$formattedQueryResults = $this->getFormattedQueryResults( $queryResults );
		$text = '';
		if ( array_key_exists( 'intro', $this->mDisplayParams ) ) {
			$text .= $this->mDisplayParams['intro'];
		}
		$text .= $formatter->display( $queryResults, $formattedQueryResults, $this->mFieldDescriptions,
			$this->mDisplayParams );
		if ( array_key_exists( 'outro', $this->mDisplayParams ) ) {
			$text .= $this->mDisplayParams['outro'];
		}
		return $text;
	}

	/**
	 * Display the link to view more results, pointing to Special:ViewData.
	 */
	public function viewMoreResultsLink( $displayHTML = true ) {
		$vd = Title::makeTitleSafe( NS_SPECIAL, 'ViewData' );
		if ( array_key_exists( 'more results text', $this->mDisplayParams ) ) {
			$moreResultsText = $this->mDisplayParams['more results text'];
		}
		else {
			$moreResultsText = wfMessage( 'moredotdotdot' )->parse();
		}

		$queryStringParams = array();
		$sqlQuery = $this->mSQLQuery;
		$queryStringParams['tables'] = $sqlQuery->mTablesStr;
		$queryStringParams['fields'] = $sqlQuery->mFieldsStr;
		if ( $sqlQuery->mOrigWhereStr != '' ) {
			$queryStringParams['where'] = $sqlQuery->mOrigWhereStr;
		}
		if ( $sqlQuery->mJoinOnStr != '' ) {
			$queryStringParams['join_on'] = $sqlQuery->mJoinOnStr;
		}
		if ( $sqlQuery->mGroupByStr != '' ) {
			$queryStringParams['group_by'] = $sqlQuery->mGroupByStr;
		}
		if ( $sqlQuery->mOrderByStr != '' ) {
			$queryStringParams['order_by'] = $sqlQuery->mOrderByStr;
		}
		if ( $this->mFormat != '' ) {
			$queryStringParams['format'] = $this->mFormat;
		}
		$queryStringParams['offset'] = $sqlQuery->mQueryLimit;
		$queryStringParams['limit'] = 100; // Is that a reasonable number in all cases?

		// Add format-specific params.
		foreach ( $this->mDisplayParams as $key => $value ) {
			$queryStringParams[$key] = $value;
		}

		if ( $displayHTML ) {
			return Html::rawElement( 'p', null,
				Linker::link( $vd, $moreResultsText, array(), $queryStringParams ) );
		} else {
			// Display link as wikitext.
			global $wgServer;
			return '[' . $wgServer . $vd->getLinkURL( $queryStringParams ) . ' ' . $moreResultsText . ']';
		}
	}

}

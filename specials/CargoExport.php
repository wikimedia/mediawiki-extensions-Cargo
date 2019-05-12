<?php
/**
 * Displays the results of a Cargo query in one of several possible
 * structured data formats - in some cases for use by an Ajax-based
 * display format.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoExport extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'CargoExport' );
	}

	function execute( $query ) {
		$this->getOutput()->disable();
		$req = $this->getRequest();

		// If no value has been set for 'tables', or 'table', just
		// display a blank screen.
		$tableArray = $req->getArray( 'tables' );
		if ( $tableArray == null ) {
			$tableArray = $req->getArray( 'table' );
		}
		if ( $tableArray == null ) {
			return;
		}
		$fieldsArray = $req->getArray( 'fields' );
		$whereArray = $req->getArray( 'where' );
		$joinOnArray = $req->getArray( 'join_on' );
		$groupByArray = $req->getArray( 'group_by' );
		$havingArray = $req->getArray( 'having' );
		$orderByArray = $req->getArray( 'order_by' );
		$limitArray = $req->getArray( 'limit' );
		$offsetArray = $req->getArray( 'offset' );

		$sqlQueries = array();
		foreach ( $tableArray as $i => $table ) {
			$sqlQueries[] = CargoSQLQuery::newFromValues(
				$table, $fieldsArray[$i], $whereArray[$i], $joinOnArray[$i], $groupByArray[$i],
				$havingArray[$i], $orderByArray[$i], $limitArray[$i], $offsetArray[$i] );
		}

		$format = $req->getVal( 'format' );

		if ( $format == 'fullcalendar' ) {
			$this->displayCalendarData( $sqlQueries );
		} elseif ( $format == 'timeline' ) {
			$this->displayTimelineData( $sqlQueries );
		} elseif ( $format == 'nvd3chart' ) {
			$this->displayNVD3ChartData( $sqlQueries );
		} elseif ( $format == 'csv' ) {
			$delimiter = $req->getVal( 'delimiter' );
			if ( $delimiter == '' ) {
				$delimiter = ',';
			} elseif ( $delimiter == '\t' ) {
				$delimiter = "\t";
			}
			$filename = $req->getVal( 'filename' );
			if ( $filename == '' ) {
				$filename = 'results.csv';
			}
			$parseValues = $req->getCheck( 'parse_values' );
			$this->displayCSVData( $sqlQueries, $delimiter, $filename, $parseValues );
		} elseif ( $format == 'excel' ) {
			$filename = $req->getVal( 'filename' );
			if ( $filename == '' ) {
				$filename = 'results.xls';
			}
			$parseValues = $req->getCheck( 'parse_values' );
			$this->displayExcelData( $sqlQueries, $filename, $parseValues );
		} elseif ( $format == 'json' ) {
			$parseValues = $req->getCheck( 'parse_values' );
			$this->displayJSONData( $sqlQueries, $parseValues );
		} else {
			print wfMessage( "cargo-query-missingformat" )->parse();
		}
	}

	/**
	 * Used for calendar format
	 */
	function displayCalendarData( $sqlQueries ) {
		$req = $this->getRequest();

		$colorArray = $req->getArray( 'color' );
		$textColorArray = $req->getArray( 'text_color' );

		$datesLowerLimit = $req->getVal( 'start' );
		$datesUpperLimit = $req->getVal( 'end' );

		$displayedArray = array();
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			$dateFieldRealNames = array();
			$startDateFieldAliases = array();
			$endDateFieldAliases = array();
			$dateFieldAliases = array();
			foreach ( $sqlQuery->mFieldDescriptions as $alias => $description ) {
				if ( $description->mType == 'Date' || $description->mType == 'Datetime' ||
					$description->mType == 'Start date' || $description->mType == 'Start datetime' ) {
					$dateFieldAliases[] = $alias;
					$realFieldName = $sqlQuery->mAliasedFieldNames[$alias];
					$dateFieldRealNames[] = $realFieldName;
					if ( $description->mType == 'Start date' || $description->mType == 'Start datetime' ) {
						$startDateFieldAliases[] = $alias;
					}
				}
				if ( $description->mType == 'End date' || $description->mType == 'End datetime' ) {
					$endDateFieldAliases[] = $alias;
				}
			}

			$where = $sqlQuery->mWhereStr;
			if ( $where != '' ) {
				$where .= " AND ";
			}
			$where .= "(";
			foreach ( $dateFieldRealNames as $j => $dateField ) {
				if ( $j > 0 ) {
					$where .= " OR ";
				}
				$where .= "($dateField >= '$datesLowerLimit' AND $dateField <= '$datesUpperLimit')";
			}
			$where .= ")";
			$sqlQuery->mWhereStr = $where;

			$queryResults = $sqlQuery->run();

			foreach ( $queryResults as $queryResult ) {
				if ( array_key_exists( 'name', $queryResult ) ) {
					$eventTitle = $queryResult['name'];
				} else {
					$eventTitle = reset( $queryResult );
				}
				if ( array_key_exists( 'color', $queryResult ) ) {
					$eventColor = $queryResult['color'];
				} else {
					$eventColor = $colorArray[$i];
				}
				if ( array_key_exists( 'text color', $queryResult ) ) {
					$eventTextColor = $queryResult['text color'];
				} else {
					$eventTextColor = $textColorArray[$i];
				}
				if ( array_key_exists( 'start', $queryResult ) ) {
					$eventStart = $queryResult['start'];
				} elseif ( !empty( $startDateFieldAliases ) ) {
					$eventStart = $queryResult[$startDateFieldAliases[0]];
				} else {
					$eventStart = $queryResult[$dateFieldAliases[0]];
				}
				if ( array_key_exists( 'end', $queryResult ) ) {
					$eventEnd = $queryResult['end'];
				} elseif ( !empty( $endDateFieldAliases ) ) {
					$eventEnd = $queryResult[$endDateFieldAliases[0]];
				} elseif ( count( $dateFieldAliases ) > 1 && array_key_exists( $dateFieldAliases[1], $queryResult ) ) {
					$eventEnd = $queryResult[$dateFieldAliases[1]];
				} else {
					$eventEnd = null;
				}
				if ( array_key_exists( 'description', $queryResult ) ) {
					$eventDescription = $queryResult['description'];
				} else {
					$eventDescription = null;
				}

				$startDateField = $dateFieldAliases[0];
				$startDate = $queryResult[$startDateField];
				$startDatePrecisionField = $startDateField . '__precision';
				// There might not be a precision field, if,
				// for instance, the date field is an SQL
				// function. Ideally we would figure out
				// the right precision, but for now just
				// go with "DATE_ONLY" - seems safe.
				if ( array_key_exists( $startDatePrecisionField, $queryResult ) ) {
					$startDatePrecision = $queryResult[$startDatePrecisionField];
				} else {
					$startDatePrecision = CargoStore::DATE_ONLY;
				}
				$curEvent = array(
					// Get first field for the title - not
					// necessarily the page name.
					'title' => $eventTitle,
					'start' => $eventStart,
					'end' => $eventEnd,
					'color' => $eventColor,
					'textColor' => $eventTextColor,
					'description' => $eventDescription
				);
				if ( array_key_exists( '_pageName', $queryResult ) ) {
					$title = Title::newFromText( $queryResult['_pageName'] );
					$curEvent['url'] = $title->getLocalURL();
				}
				if ( $startDatePrecision != CargoStore::DATE_AND_TIME ) {
					$curEvent['allDay'] = true;
				}
				$displayedArray[] = $curEvent;
			}
		}

		print json_encode( $displayedArray );
	}

	/**
	 * Used by displayTimelineData().
	 */
	function timelineDatesCmp( $a, $b ) {
		if ( $a['start'] == $b['start'] ) {
			return 0;
		}
		return ( $a['start'] < $b['start'] ) ? -1 : 1;
	}

	function displayTimelineData( $sqlQueries ) {
		$displayedArray = array();
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			$dateFields = array();
			// Make sure the Start date/datetime type field, if any,
			// shows up before the End date/datetime type field in
			// the $dateFields array.
			foreach ( $sqlQuery->mFieldDescriptions as $field => $description ) {
				if ( $description->mType == 'Start date' || $description->mType == 'Start datetime' ) {
					$dateFields[] = $field;
				}
			}
			foreach ( $sqlQuery->mFieldDescriptions as $field => $description ) {
				if ( $description->mType == 'Date' || $description->mType == 'Datetime' ||
					$description->mType == 'End date' || $description->mType == 'End datetime' ) {
					$dateFields[] = $field;
				}
			}

			$queryResults = $sqlQuery->run();

			foreach ( $queryResults as $queryResult ) {
				$eventDescription = '';
				$firstField = true;
				$startDateValue = null;
				$endDateValue = null;
				foreach ( $sqlQuery->mFieldDescriptions as $fieldName => $fieldDescription ) {
					if ( $dateFields[0] == $fieldName ) {
						$startDateValue = $queryResult[$fieldName];
						if ( !( $fieldDescription->mType == "Start date" || $fieldDescription->mType == "Start datetime" ) ) {
							continue;
						}
						foreach ( $sqlQuery->mFieldDescriptions as $fieldName2 => $fieldDescription2 ) {
							if ( $fieldDescription2->mType == "End date" || $fieldDescription2->mType == "End datetime" ) {
								$endDateValue = $queryResult[$fieldName2];
								break;
							}
						}
					}
					// Don't display the first field (it'll
					// be the title), or date fields.
					if ( $firstField ) {
						$firstField = false;
						continue;
					}
					if ( in_array( $fieldName, $dateFields ) ) {
						continue;
					}
					if ( !array_key_exists( $fieldName, $queryResult ) ) {
						continue;
					}
					$fieldValue = $queryResult[$fieldName];
					if ( trim( $fieldValue ) == '' ) {
						continue;
					}
					$eventDescription .= "<strong>$fieldName:</strong> $fieldValue<br />\n";
				}

				if ( array_key_exists( 'name', $queryResult ) ) {
					$eventTitle = $queryResult['name'];
				} else {
					// Get first field for the 'title' - not
					// necessarily the page name.
					$eventTitle = reset( $queryResult );
				}

				$eventDisplayDetails = array(
					'title' => $eventTitle,
					'description' => $eventDescription,
					'start' => $startDateValue,
				);
				if ( $endDateValue !== null ) {
					$eventDisplayDetails['end'] = $endDateValue;
				} else {
					// If there's no end date, set the
					// end date to the start date.
					$eventDisplayDetails['end'] = $startDateValue;
				}

				// If we have the name of the page on which
				// the event is defined, link to that -
				// otherwise, don't link to anything.
				// (In most cases, the _pageName field will
				// also be the title of the event.)
				if ( array_key_exists( '_pageName', $queryResult ) ) {
					$title = Title::newFromText( $queryResult['_pageName'] );
					$eventDisplayDetails['link'] = $title->getFullURL();
				}
				$displayedArray[] = $eventDisplayDetails;
			}
		}
		// Sort by date, ascending.
		usort( $displayedArray, 'self::timelineDatesCmp' );

		$displayedArray = array( 'events' => $displayedArray );
		print json_encode( $displayedArray, JSON_HEX_TAG | JSON_HEX_QUOT );
	}

	function displayNVD3ChartData( $sqlQueries ) {
		$req = $this->getRequest();

		// We'll only use the first query, if there's more than one.
		$sqlQuery = $sqlQueries[0];
		$queryResults = $sqlQuery->run();

		// Handle date precision fields, which come alongside date fields.
		foreach ( $queryResults as $i => $curRow ) {
			foreach ( $curRow as $fieldName => $value ) {
				if ( strpos( $fieldName, '__precision' ) == false ) {
					continue;
				}
				$dateField = str_replace( '__precision', '', $fieldName );
				if ( !array_key_exists( $dateField, $curRow ) ) {
					continue;
				}
				$origDateValue = $curRow[$dateField];
				// Years by themselves lead to a display
				// problem, for some reason, so add a space.
				$queryResults[$i][$dateField] = CargoQueryDisplayer::formatDateFieldValue( $origDateValue, $value, 'Date' ) . ' ';
				unset( $queryResults[$i][$fieldName] );
			}
		}

		// @TODO - this array needs to be longer.
		$colorsArray = array( '#60BD68', '#FAA43A', '#5DA6DA', '#CC333F' );

		// Initialize everything, using the field names.
		$firstRow = reset( $queryResults );
		$displayedArray = array();
		$labelNames = array();
		$fieldNum = 0;
		foreach ( $firstRow as $fieldName => $value ) {
			if ( $fieldNum == 0 ) {
				$labelNames[] = $value;
			} else {
				$curSeries = array(
					'key' => $fieldName,
					'color' => $colorsArray[$fieldNum - 1],
					'values' => array()
				);
				$displayedArray[] = $curSeries;
			}
			$fieldNum++;
		}

		foreach ( $queryResults as $i => $queryResult ) {
			$fieldNum = 0;
			foreach ( $queryResult as $fieldName => $value ) {
				if ( $fieldNum == 0 ) {
					$labelName = $value;
					if ( trim( $value ) == '' ) {
						// Display blank labels as "None".
						$labelName = $this->msg( 'powersearch-togglenone' )->text();
					}
				} else {
					$displayedArray[$fieldNum - 1]['values'][] = array(
						'label' => $labelName,
						'value' => $value
					);
				}
				$fieldNum++;
			}
		}

		print json_encode( $displayedArray, JSON_NUMERIC_CHECK | JSON_HEX_TAG );
	}

	/**
	 * Turn all wikitext into HTML in a set of query results.
	 */
	function parseWikitextInQueryResults( $queryResults ) {
		$parsedQueryResults = array();
		foreach ( $queryResults as $rowNum => $rowValues ) {
			$parsedQueryResults[$rowNum] = array();
			foreach ( $rowValues as $colName => $value ) {
				$parsedQueryResults[$rowNum][$colName] = CargoUtils::smartParse( $value, null );
			}
		}
		return $parsedQueryResults;
	}

	function displayCSVData( $sqlQueries, $delimiter, $filename, $parseValues ) {
		header( "Content-Type: text/csv" );
		header( "Content-Disposition: attachment; filename=$filename" );

		$queryResultsArray = array();
		$allHeaders = array();
		foreach ( $sqlQueries as $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			if ( $parseValues ) {
				$queryResults = $this->parseWikitextInQueryResults( $queryResults );
			}
			$allHeaders = array_merge( $allHeaders, array_keys( reset( $queryResults ) ) );
			$queryResultsArray[] = $queryResults;
		}

		// Remove duplicates from headers array.
		$allHeaders = array_unique( $allHeaders );

		$out = fopen( 'php://output', 'w' );

		// Display header row.
		fputcsv( $out, $allHeaders, $delimiter );

		// Display the data.
		foreach ( $queryResultsArray as $queryResults ) {
			foreach ( $queryResults as $queryResultRow ) {
				// Put in a blank if this row doesn't contain
				// a certain column (this will only happen
				// for compound queries).
				$displayedRow = array();
				foreach ( $allHeaders as $header ) {
					if ( array_key_exists( $header, $queryResultRow ) ) {
						$displayedRow[$header] = $queryResultRow[$header];
					} else {
						$displayedRow[$header] = null;
					}
				}
				fputcsv( $out, $displayedRow, $delimiter );
			}
		}
		fclose( $out );
	}

	function displayExcelData( $sqlQueries, $filename, $parseValues ) {
		// We'll only use the first query, if there's more than one.
		$sqlQuery = $sqlQueries[0];
		$queryResults = $sqlQuery->run();
		if ( $parseValues ) {
			$queryResults = $this->parseWikitextInQueryResults( $queryResults );
		}

		$file = new PHPExcel();
		$file->setActiveSheetIndex( 0 );

		// Create array with header row and query results.
		$header[] = array_keys( reset( $queryResults ) );
		$rows = array_merge( $header, $queryResults );

		$file->getActiveSheet()->fromArray( $rows, null, 'A1' );
		header( "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" );
		header( "Content-Disposition: attachment;filename=$filename" );
		header( "Cache-Control: max-age=0" );

		$writer = PHPExcel_IOFactory::createWriter( $file, 'Excel5' );

		$writer->save( 'php://output' );
	}

	function displayJSONData( $sqlQueries, $parseValues ) {
		header( "Content-Type: application/json" );

		$allQueryResults = array();
		foreach ( $sqlQueries as $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			if ( $parseValues ) {
				$queryResults = $this->parseWikitextInQueryResults( $queryResults );
			}

			// Turn "List" fields into arrays.
			foreach ( $sqlQuery->mFieldDescriptions as $alias => $fieldDescription ) {
				if ( $fieldDescription->mIsList ) {
					$delimiter = $fieldDescription->getDelimiter();
					for ( $i = 0; $i < count( $queryResults ); $i++ ) {
						$curValue = $queryResults[$i][$alias];
						if ( !is_array( $curValue ) ) {
							$queryResults[$i][$alias] = explode( $delimiter, $curValue );
						}
					}
				}
			}

			$allQueryResults = array_merge( $allQueryResults, $queryResults );
		}

		if ( $parseValues ) {
			$jsonOptions = JSON_PRETTY_PRINT;
		} else {
			$jsonOptions = JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_PRETTY_PRINT;
		}
		print json_encode( $allQueryResults, $jsonOptions );
	}
}

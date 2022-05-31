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
	public function __construct() {
		parent::__construct( 'CargoExport' );
	}

	public function execute( $query ) {
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

		$sqlQueries = [];
		foreach ( $tableArray as $i => $table ) {
			$fields = $fieldsArray[$i];
			$where = $whereArray !== null ? $whereArray[$i] : null;
			$joinOn = $joinOnArray !== null ? $joinOnArray[$i] : null;
			$groupBy = $groupByArray !== null ? $groupByArray[$i] : null;
			$having = $havingArray !== null ? $havingArray[$i] : null;
			$orderBy = $orderByArray !== null ? $orderByArray[$i] : null;
			$limit = $limitArray !== null ? $limitArray[$i] : null;
			$offset = $offsetArray !== null ? $offsetArray[$i] : null;
			$sqlQueries[] = CargoSQLQuery::newFromValues( $table,
				$fields, $where, $joinOn, $groupBy, $having,
				$orderBy, $limit, $offset );
		}

		$format = $req->getVal( 'format' );

		try {
			if ( $format == 'fullcalendar' ) {
				$this->displayCalendarData( $sqlQueries );
			} elseif ( $format == 'timeline' ) {
				$this->displayTimelineData( $sqlQueries );
			} elseif ( $format == 'gantt' ) {
				$this->displayGanttData( $sqlQueries );
			} elseif ( $format == 'bpmn' ) {
				$this->displayBPMNData( $sqlQueries );
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
			} elseif ( $format == 'bibtex' ) {
				$defaultEntryType = $req->getVal( 'default_entry_type' );
				if ( $defaultEntryType == '' ) {
					$defaultEntryType = 'article';
				}
				$this->displayBibtexData( $sqlQueries, $defaultEntryType );
			} elseif ( $format === 'icalendar' ) {
				$this->displayIcalendarData( $sqlQueries );
			} else {
				// Let other extensions display the data if they have defined their own "deferred"
				// formats. This is an unusual hook in that functions that use it have to return false;
				// otherwise the error message will be displayed.
				$result = Hooks::run( 'CargoDisplayExportData', [ $format, $sqlQueries, $req ] );
				if ( $result ) {
					print $this->msg( "cargo-query-missingformat" )->parse();
				}
			}
		} catch ( Exception $e ) {
			print $e->getMessage();
		}
	}

	/**
	 * Used for calendar format
	 */
	private function displayCalendarData( $sqlQueries ) {
		$req = $this->getRequest();

		$colorArray = $req->getArray( 'color' );
		$textColorArray = $req->getArray( 'text_color' );

		$datesLowerLimit = $req->getVal( 'start' );
		$datesUpperLimit = $req->getVal( 'end' );

		$displayedArray = [];
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			list( $startDateField, $endDateField ) = $sqlQuery->getMainStartAndEndDateFields();

			$where = $sqlQuery->mWhereStr;
			if ( $where != '' ) {
				$where .= " AND ";
			}
			$where .= "(";
			foreach ( $sqlQuery->mDateFieldPairs as $j => $dateFieldPair ) {
				if ( $j > 0 ) {
					$where .= " OR ";
				}
				$startDateFieldName = $dateFieldPair['start'][0];
				if ( array_key_exists( 'end', $dateFieldPair ) ) {
					$endDateFieldName = $dateFieldPair['end'][0];
				} else {
					$endDateFieldName = $startDateFieldName;
				}
				$where .= "($endDateFieldName > '$datesLowerLimit' AND $startDateFieldName < '$datesUpperLimit')";
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
				} elseif ( $colorArray != null && array_key_exists( $i, $colorArray ) ) {
					$eventColor = $colorArray[$i];
				} else {
					$eventColor = null;
				}
				if ( array_key_exists( 'text color', $queryResult ) ) {
					$eventTextColor = $queryResult['text color'];
				} elseif ( $textColorArray != null && array_key_exists( $i, $textColorArray ) ) {
					$eventTextColor = $textColorArray[$i];
				} else {
					$eventTextColor = null;
				}
				$eventStart = $queryResult[$startDateField];
				$eventEnd = ( $endDateField !== null ) ? $queryResult[$endDateField] : null;
				if ( array_key_exists( 'description', $queryResult ) ) {
					$eventDescription = $queryResult['description'];
				} else {
					$eventDescription = null;
				}

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
				$curEvent = [
					// Get first field for the title - not
					// necessarily the page name.
					'title' => $eventTitle,
					'start' => $eventStart,
					'end' => $eventEnd,
					'color' => $eventColor,
					'textColor' => $eventTextColor,
					'description' => $eventDescription
				];
				if ( array_key_exists( '_pageName', $queryResult ) ) {
					$title = Title::newFromText( $queryResult['_pageName'] );
					$curEvent['url'] = $title->getLocalURL();
				} elseif ( array_key_exists( 'link', $queryResult ) ) {
					$title = Title::newFromText( $queryResult['link'] );
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
	 * Used for gantt format
	 */
	private function displayGanttData( $sqlQueries ) {
		print self::getGanttJSONData( $sqlQueries );
	}

	public static function getGanttJSONData( $sqlQueries ) {
		$displayedArray['data'] = [];
		$displayedArray['links'] = [];
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			list( $startDateField, $endDateField ) = $sqlQuery->getMainStartAndEndDateFields();

			$queryResults = $sqlQuery->run();
			$n = 1;
			foreach ( $queryResults as $queryResult ) {
				if ( array_key_exists( 'name', $queryResult ) ) {
					$eventTitle = $queryResult['name'];
				} else {
					$eventTitle = reset( $queryResult );
				}
				if ( array_key_exists( '_pageID', $queryResult ) ) {
					$eventID = $queryResult['_pageID'];
				} else {
					$eventID = $n;
					$n++;
				}

				if ( !isset( $queryResult[$startDateField] ) ) {
					continue;
				}
				$eventStart = $queryResult[$startDateField];
				$eventEnd = ( $endDateField !== null && isset( $queryResult[$endDateField] ) ) ? $queryResult[$endDateField] : null;
				if ( array_key_exists( 'duration', $queryResult ) ) {
					$eventDuration = $queryResult['duration'];
				} else {
					$eventDuration = 1;
				}
				if ( array_key_exists( 'target', $queryResult ) ) {
					$target = $queryResult['target'];
				} else {
					$target = null;
				}

				$data = [
					'id' => $eventID,
					'text' => $eventTitle,
					'start_date' => $eventStart,
					'end_date' => $eventEnd,
					'duration' => $eventDuration
				];
				$links = [
					'id' => $eventID,
					'source' => $eventID,
					'target' => $target,
					'type' => "0"
				];
				array_push( $displayedArray['data'], $data );
				array_push( $displayedArray['links'], $links );
			}
			for ( $t = 0; $t < count( $displayedArray['links'] ); $t++ ) {
				if ( $displayedArray['links'][$t]['target'] != null ) {
					$temp = $displayedArray['links'][$t]['target'];
					$key = array_search( $temp, array_column( $displayedArray['data'], 'text' ) );
					$displayedArray['links'][$t]['target'] = $displayedArray['links'][$key]['id'];
				}
			}
		}
		return json_encode( $displayedArray );
	}

	/**
	 * Used for bpmn format
	 */
	private function displayBPMNData( $sqlQueries ) {
		$req = $this->getRequest();
		$sequenceFlows = [];
		$elements = [];
		$t = 1;
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			foreach ( $queryResults as $queryResult ) {
				if ( array_key_exists( 'name', $queryResult ) ) {
					$name = $queryResult['name'];
				} else {
					$name = reset( $queryResult );
				}
				if ( array_key_exists( 'label', $queryResult ) ) {
					$label = $queryResult['label'];
				} else {
					$label = "";
				}
				if ( array_key_exists( 'type', $queryResult ) ) {
					$eventType = $queryResult['type'];
				} else {
					continue;
				}
				if ( array_key_exists( 'sources', $queryResult ) ) {
					$source = $queryResult['sources'];
				} else {
					$source = "";
				}
				if ( array_key_exists( 'flowLabels', $queryResult ) ) {
					$flowLabels = $queryResult['flowLabels'];
				} else {
					$flowLabels = "";
				}
				if ( array_key_exists( 'linked', $queryResult ) ) {
					$linkedpage = $queryResult['linked'];
				} else {
					$linkedpage = "";
				}

				$curEvent = [
					'name' => $name,
					'label' => $label,
					'type' => $eventType,
					'source' => $source,
					'linkedpage' => $linkedpage,
					'flowLabels' => $flowLabels
				];

				if ( str_contains( $curEvent['type'], 'Event' ) ) {
					$curEvent['height'] = "36";
					$curEvent['width'] = "36";
				} elseif ( str_contains( $curEvent['type'], 'Gateway' ) ) {
					$curEvent['height'] = "50";
					$curEvent['width'] = "50";
				} else {
					$curEvent['height'] = "80";
					$curEvent['width'] = "100";
				}
				$curEvent['id'] = $curEvent['type'] . $t;
				$t++;
				array_push( $elements, $curEvent );
			}
		}

		header( 'Content-Type: text/xml' );
		$XML = '<?xml version="1.0" encoding="UTF-8"?>';
		// Needed to restore highlighting in vi - <?
		$XML .= '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" 
		xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" 
		xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" 
		id="Definitions_18vnora" 
		targetNamespace="http://bpmn.io/schema/bpmn" 
		exporter="bpmn-js (https://demo.bpmn.io)" 
		exporterVersion="4.0.3">
		<bpmn:process id="Process_1" isExecutable="false">';

		// XML for BPMN Process
		foreach ( $elements as $i => $task ) {
			if ( is_array( $task ) && $task['type'] != "" ) {
				$XML .= '<bpmn:' . $task[ 'type' ] . ' id="' . $task['id'];
				if ( $task['name'] != "" ) {
					if ( $task['label'] != "" ) {
						$XML .= '" name="' . $task['label'];
					} else {
						$XML .= '" name="' . $task['name'];
					}
				}
				if ( $task[ 'linkedpage' ] != "" ) {
					$XML .= '&#10;[[' . $task['linkedpage'] . ']]';
				}
				$XML .= '"></bpmn:' . $task['type'] . '>';
			}
		}
		foreach ( $elements as $elementNum => $element ) {
			if ( !array_key_exists( 'source', $element ) ) {
				continue;
			}
			$sources = explode( ", ", $element['source'] );
			$labels = explode( ", ", $element['flowLabels'] );
			if ( count( $sources ) == 1 ) {
				$sourceElementName = $element['source'];
				$key = array_search( $sourceElementName, array_column( $elements, 'name' ) );
				if ( $key === false ) {
						continue;
				}
				$sequenceFlows[] = [
						'type' => 'sequenceFlow',
						'source' => $elements[$key]['id'],
						'target' => $element['id'],
						'name' => $element['flowLabels'],
						'id' => 'sequenceFlow' . ( count( $sequenceFlows ) + 1 )
				];
			} else {
				foreach ( $sources as $sourceNum => $sourceElementName ) {
					$key = array_search( $sourceElementName, array_column( $elements, 'name' ) );
					if ( $key === false ) {
							continue;
					}
					$sequenceFlows[] = [
						'type' => 'sequenceFlow',
						'source' => $elements[$key]['id'],
						'target' => $element['id'],
						'name' => $labels[$sourceNum],
						'id' => 'sequenceFlow' . ( count( $sequenceFlows ) + 1 )
					];
				}
			}
		}
		foreach ( $sequenceFlows as $i => $task ) {
			if ( is_array( $task ) && $task['type'] == "sequenceFlow" ) {
				$XML .= '<bpmn:sequenceFlow id="' . $task['id'] . '" sourceRef="' . $task['source'] . '" targetRef="' . $task['target'] . '" name="' . $task['name'] . '"/>';
			}
		}
		$XML .= '</bpmn:process></bpmn:definitions>';
		print $XML;
	}

	/**
	 * Used by displayTimelineData().
	 */
	private function timelineDatesCmp( $a, $b ) {
		if ( $a['start'] == $b['start'] ) {
			return 0;
		}
		return ( $a['start'] < $b['start'] ) ? -1 : 1;
	}

	private function displayTimelineData( $sqlQueries ) {
		$displayedArray = [];
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			list( $startDateField, $endDateField ) = $sqlQuery->getMainStartAndEndDateFields();

			$queryResults = $sqlQuery->run();

			foreach ( $queryResults as $queryResult ) {
				$eventDescription = '';
				$firstField = true;

				if ( array_key_exists( 'name', $queryResult ) ) {
					$eventTitle = $queryResult['name'];
				} else {
					// Get first field for the 'title' - not
					// necessarily the page name.
					$eventTitle = reset( $queryResult );
				}

				if ( !isset( $queryResult[$startDateField] ) ) {
					continue;
				}
				$startDateValue = $queryResult[$startDateField];
				if ( $endDateField !== null && isset( $queryResult[$endDateField] ) ) {
					$endDateValue = $queryResult[$endDateField];
				} else {
					$endDateValue = $startDateValue;
				}

				$eventDisplayDetails = [
					'title' => $eventTitle,
					'description' => $eventDescription,
					'start' => $startDateValue,
					'end' => $endDateValue
				];

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

		$displayedArray = [ 'events' => $displayedArray ];
		print json_encode( $displayedArray, JSON_HEX_TAG | JSON_HEX_QUOT );
	}

	private function displayNVD3ChartData( $sqlQueries ) {
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
		$colorsArray = [ '#60BD68', '#FAA43A', '#5DA6DA', '#CC333F' ];

		// Initialize everything, using the field names.
		$firstRow = reset( $queryResults );
		$displayedArray = [];
		$labelNames = [];
		$fieldNum = 0;
		foreach ( $firstRow as $fieldName => $value ) {
			if ( $fieldNum == 0 ) {
				$labelNames[] = $value;
			} else {
				$curSeries = [
					'key' => $fieldName,
					'color' => $colorsArray[$fieldNum - 1],
					'values' => []
				];
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
					$displayedArray[$fieldNum - 1]['values'][] = [
						'label' => $labelName,
						'value' => $value
					];
				}
				$fieldNum++;
			}
		}

		print json_encode( $displayedArray, JSON_NUMERIC_CHECK | JSON_HEX_TAG );
	}

	/**
	 * Turn all wikitext into HTML in a set of query results.
	 */
	private function parseWikitextInQueryResults( $queryResults ) {
		$parsedQueryResults = [];
		foreach ( $queryResults as $rowNum => $rowValues ) {
			$parsedQueryResults[$rowNum] = [];
			foreach ( $rowValues as $colName => $value ) {
				$parsedQueryResults[$rowNum][$colName] = CargoUtils::smartParse( $value, null );
			}
		}
		return $parsedQueryResults;
	}

	private function displayCSVData( $sqlQueries, $delimiter, $filename, $parseValues ) {
		header( "Content-Type: text/csv" );
		header( "Content-Disposition: attachment; filename=$filename" );

		$queryResultsArray = [];
		$allHeaders = [];
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
				$displayedRow = [];
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

	private function displayExcelData( $sqlQueries, $filename, $parseValues ) {
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

	private function displayJSONData( $sqlQueries, $parseValues ) {
		$allQueryResults = [];
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
		$json = json_encode( $allQueryResults, $jsonOptions );
		$this->outputFile( 'application/json', 'export', 'json', $json );
	}

	private function displayBibtexData( $sqlQueries, $defaultEntryType ) {
		$text = '';
		foreach ( $sqlQueries as $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			$text .= CargoBibtexFormat::generateBibtexEntries( $queryResults,
					$sqlQuery->mFieldDescriptions,
					[ 'default entry type' => $defaultEntryType ] );
		}
		$this->outputFile( 'text/plain', 'results.bib', 'bib', $text );
	}

	/**
	 * Output in the icalendar format.
	 *
	 * @param CargoSQLQuery[] $sqlQueries
	 */
	private function displayIcalendarData( $sqlQueries ) {
		$req = $this->getRequest();
		$format = new CargoICalendarFormat( $this->getOutput() );
		$calendar = $format->getCalendar( $req, $sqlQueries );

		$filename = $req->getText( 'filename', 'export.ics' );
		$this->outputFile( 'text/calendar', $filename, 'ics', $calendar );
	}

	/**
	 * Output a file, with a normalized name and appropriate HTTP headers.
	 *
	 * @param string $contentType The MIME type of the file.
	 * @param string $filename The filename. It doesn't matter if it has the extension or not.
	 * @param string $fileExtension The file extension, without a leading dot.
	 * @param string $data The file contents.
	 * @param string $disposition Either 'inline' (the default) or 'attachment'.
	 */
	private function outputFile( $contentType, $filename, $fileExtension, $data, $disposition = 'inline' ) {
		// Clean the filename and make sure it has the correct extension.
		$filenameTitle = Title::newFromText( wfStripIllegalFilenameChars( $filename ) );
		$filename = $filenameTitle->getDBkey();
		if ( substr( $filename, -strlen( '.' . $fileExtension ) ) !== '.' . $fileExtension ) {
			$filename .= '.' . $fileExtension;
		}

		$disposition = in_array( $disposition, [ 'inline', 'attachment' ] ) ? $disposition : 'inline';
		header( 'Content-Type: ' . $contentType );
		header( 'Content-Disposition: ' . $disposition . ';filename=' . $filename );
		file_put_contents( 'php://output', $data );
	}
}

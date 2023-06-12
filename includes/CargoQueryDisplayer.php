<?php

use MediaWiki\MediaWikiServices;

/**
 * CargoQueryDisplayer - class for displaying query results.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQueryDisplayer {

	private const FORMAT_CLASSES = [
		'list' => CargoListFormat::class,
		'ul' => CargoULFormat::class,
		'ol' => CargoOLFormat::class,
		'template' => CargoTemplateFormat::class,
		'embedded' => CargoEmbeddedFormat::class,
		'csv' => CargoCSVFormat::class,
		'excel' => CargoExcelFormat::class,
		'feed' => CargoFeedFormat::class,
		'json' => CargoJSONFormat::class,
		'outline' => CargoOutlineFormat::class,
		'tree' => CargoTreeFormat::class,
		'table' => CargoTableFormat::class,
		'dynamic table' => CargoDynamicTableFormat::class,
		'map' => CargoMapsFormat::class,
		'googlemaps' => CargoGoogleMapsFormat::class,
		'leaflet' => CargoLeafletFormat::class,
		'openlayers' => CargoOpenLayersFormat::class,
		'calendar' => CargoCalendarFormat::class,
		'icalendar' => CargoICalendarFormat::class,
		'timeline' => CargoTimelineFormat::class,
		'gantt' => CargoGanttFormat::class,
		'bpmn' => CargoBPMNFormat::class,
		'category' => CargoCategoryFormat::class,
		'bar chart' => CargoBarChartFormat::class,
		'pie chart' => CargoPieChartFormat::class,
		'gallery' => CargoGalleryFormat::class,
		'slideshow' => CargoSlideshowFormat::class,
		'tag cloud' => CargoTagCloudFormat::class,
		'exhibit' => CargoExhibitFormat::class,
		'bibtex' => CargoBibtexFormat::class,
		'zip' => CargoZipFormat::class,
	];

	public $mSQLQuery;
	public $mFormat;
	public $mDisplayParams = [];
	public $mParser = null;
	public $mFieldDescriptions = [];
	public $mFieldTables;

	public static function newFromSQLQuery( $sqlQuery ) {
		$cqd = new CargoQueryDisplayer();
		$cqd->mSQLQuery = $sqlQuery;
		$cqd->mFieldDescriptions = $sqlQuery->mFieldDescriptions;
		$cqd->mFieldTables = $sqlQuery->mFieldTables;
		return $cqd;
	}

	/**
	 * @return string[] List of {@see CargoDisplayFormat} subclasses
	 */
	public static function getAllFormatClasses() {
		$formatClasses = self::FORMAT_CLASSES;

		// Let other extensions add their own formats - or even
		// remove formats, if they want to.
		MediaWikiServices::getInstance()->getHookContainer()->run( 'CargoSetFormatClasses', [ &$formatClasses ] );

		return $formatClasses;
	}

	/**
	 * Given a format name, and a list of the fields, returns the name
	 * of the class to instantiate for that format.
	 * @return string
	 */
	public function getFormatClass() {
		$formatClasses = self::getAllFormatClasses();
		if ( array_key_exists( $this->mFormat, $formatClasses ) ) {
			return $formatClasses[$this->mFormat];
		}

		if ( count( $this->mFieldDescriptions ) > 1 ) {
			$format = 'table';
		} else {
			$format = 'list';
		}
		return $formatClasses[$format];
	}

	/**
	 * @param ParserOutput $out
	 * @param Parser|null $parser
	 * @return CargoDisplayFormat
	 */
	public function getFormatter( $out, $parser = null ) {
		$formatClass = $this->getFormatClass();
		$formatObject = new $formatClass( $out, $parser );
		return $formatObject;
	}

	public function getFormattedQueryResults( $queryResults, $escapeValues = false ) {
		global $wgScriptPath, $wgServer;

		// The assignment will do a copy.
		$formattedQueryResults = $queryResults;
		foreach ( $queryResults as $rowNum => $row ) {
			foreach ( $row as $fieldName => $value ) {
				if ( $value === null || trim( $value ) === '' ) {
					continue;
				}

				if ( !array_key_exists( $fieldName, $this->mFieldDescriptions ) ) {
					continue;
				}

				$fieldDescription = $this->mFieldDescriptions[$fieldName];
				if ( is_array( $this->mFieldTables ) && array_key_exists( $fieldName, $this->mFieldTables ) ) {
					$fieldTableName = $this->mFieldTables[$fieldName];
				}
				$fieldType = $fieldDescription->mType;

				$text = '';
				if ( $fieldDescription->mIsList ) {
					// There's probably an easier way to do
					// this, using array_map().
					$delimiter = $fieldDescription->getDelimiter();
					// We need to decode it in case the delimiter is ;
					$valueDecoded = html_entity_decode( $value );
					$fieldValues = explode( $delimiter, $valueDecoded );
					foreach ( $fieldValues as $i => $fieldValue ) {
						if ( trim( $fieldValue ) == '' ) {
							continue;
						}
						if ( $i > 0 ) {
							// Use a bullet point as
							// the list delimiter -
							// it's better than using
							// a comma, or the
							// defined delimiter,
							// because it's more
							// consistent and makes
							// it clearer whether
							// list parsing worked.
							$text .= ' <span class="CargoDelimiter">&bull;</span> ';
						}
						$text .= self::formatFieldValue( $fieldValue, $fieldType, $fieldDescription, $this->mParser, $escapeValues );
					}
				} elseif ( $fieldDescription->isDateOrDatetime() ) {
					$datePrecisionField = $fieldName . '__precision';
					if ( $fieldName[0] == '_' ) {
						// Special handling for pre-specified fields.
						$datePrecision = ( $fieldType == 'Datetime' ) ? CargoStore::DATE_AND_TIME : CargoStore::DATE_ONLY;
					} elseif ( array_key_exists( $datePrecisionField, $row ) ) {
						$datePrecision = $row[$datePrecisionField];
					} else {
						$fullDatePrecisionField = $fieldTableName . '.' . $datePrecisionField;
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
				} elseif ( $fieldType == 'Boolean' ) {
					// Displaying a check mark for "yes"
					// and an x mark for "no" would be
					// cool, but those are apparently far
					// from universal symbols.
					$text = ( $value == true ) ? wfMessage( 'htmlform-yes' )->escaped() : wfMessage( 'htmlform-no' )->escaped();
				} elseif ( $fieldType == 'Searchtext' && $this->mSQLQuery && array_key_exists( $fieldName, $this->mSQLQuery->mSearchTerms ) ) {
					$searchTerms = $this->mSQLQuery->mSearchTerms[$fieldName];
					$text = Html::rawElement( 'span', [ 'class' => 'searchresult' ], self::getTextSnippet( $value, $searchTerms ) );
				} elseif ( $fieldType == 'Rating' ) {
					$rate = $value * 20;
					$url = $wgServer . $wgScriptPath . '/' . 'extensions/Cargo/resources/images/star-rating-sprite-1.png';
					$text = '<span style="display: block; width: 65px; height: 13px; background: url(\'' . $url . '\') 0 0;">
						<span style="display: block; width: ' . $rate . '%; height: 13px; background: url(\'' . $url . '\') 0 -13px;"></span>';
				} else {
					$text = self::formatFieldValue( $value, $fieldType, $fieldDescription, $this->mParser, $escapeValues );
				}

				if ( array_key_exists( 'max display chars', $this->mDisplayParams ) && ( $fieldType == 'Text' || $fieldType == 'Wikitext' ) ) {
					$maxDisplayChars = $this->mDisplayParams['max display chars'];
					if ( strlen( $text ) > $maxDisplayChars && strlen( strip_tags( $text ) ) > $maxDisplayChars ) {
						$text = '<span class="cargoMinimizedText">' . $text . '</span>';
					}
				}

				if ( $text != '' ) {
					$formattedQueryResults[$rowNum][$fieldName] = $text;
				}
			}
		}
		return $formattedQueryResults;
	}

	public static function formatFieldValue( $value, $type, $fieldDescription, $parser, $escapeValue ) {
		if ( $type == 'Integer' ) {
			global $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter;
			return number_format( $value, 0, $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter );
		} elseif ( $type == 'Float' ) {
			global $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter;
			// Can we assume that the decimal mark will be a '.' in the database?
			$locOfDecimalPoint = strrpos( $value, '.' );
			if ( $locOfDecimalPoint === false ) {
				// Better to show "17.0" than "17", if it's a Float.
				$numDecimalPlaces = 1;
			} else {
				$numDecimalPlaces = strlen( $value ) - $locOfDecimalPoint - 1;
			}
			return number_format( $value, $numDecimalPlaces, $wgCargoDecimalMark,
				$wgCargoDigitGroupingCharacter );
		} elseif ( $type == 'Page' ) {
			$title = Title::newFromText( $value );
			if ( $title == null ) {
				return null;
			}
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			// Hide the namespace in the display?
			global $wgCargoHideNamespaceName;
			if ( in_array( $title->getNamespace(), $wgCargoHideNamespaceName ) ) {
				return CargoUtils::makeLink( $linkRenderer, $title, htmlspecialchars( $title->getRootText() ) );
			} else {
				return CargoUtils::makeLink( $linkRenderer, $title );
			}
		} elseif ( $type == 'File' ) {
			// 'File' values are basically pages in the File:
			// namespace; they are displayed as thumbnails within
			// queries.
			$title = Title::newFromText( $value, NS_FILE );
			if ( $title == null || !$title->exists() ) {
				return $value;
			}

			// If it's a redirect, use the redirect target instead.
			if ( $title->isRedirect() ) {
				$page = CargoUtils::makeWikiPage( $title );
				$title = $page->getRedirectTarget();
				if ( !$title->exists() ) {
					return $title->getText();
				}
			}

			$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $title );
			return Linker::makeThumbLinkObj(
				$title,
				$file,
				$value,
				''
			);
		} elseif ( $type == 'URL' ) {
			// Validate URL - regexp code copied from Sanitizer::validateAttributes().
			$hrefExp = '/^(' . wfUrlProtocols() . ')[^\s]+$/';
			if ( !preg_match( $hrefExp, $value ) ) {
				if ( $escapeValue ) {
					return htmlspecialchars( $value );
				} else {
					return $value;
				}
			} elseif ( array_key_exists( 'link text', $fieldDescription->mOtherParams ) ) {
				return Html::element( 'a', [ 'href' => $value ],
						$fieldDescription->mOtherParams['link text'] );
			} else {
				// Otherwise, display the URL as a link.
				global $wgNoFollowLinks;
				$linkParams = [ 'href' => $value, 'class' => 'external free' ];
				if ( $wgNoFollowLinks ) {
					$linkParams['rel'] = 'nofollow';
				}
				return Html::element( 'a', $linkParams, $value );
			}
		} elseif ( $type == 'Date' || $type == 'Datetime' ) {
			// This should not get called - date fields
			// have a separate formatting function.
			return $value;
		} elseif ( $type == 'Wikitext' || $type == 'Wikitext string' || $type == '' ) {
			return CargoUtils::smartParse( $value, $parser );
		} elseif ( $type == 'Searchtext' ) {
			if ( $escapeValue ) {
				$value = htmlspecialchars( $value );
			}
			if ( strlen( $value ) > 300 ) {
				return substr( $value, 0, 300 ) . ' ...';
			} else {
				return $value;
			}
		}

		// If it's not any of these specially-handled types, just
		// return the value.
		if ( $escapeValue ) {
			$value = htmlspecialchars( $value );
		}
		return $value;
	}

	public static function formatDateFieldValue( $dateValue, $datePrecision, $type ) {
		// Quick escape.
		if ( $dateValue == '' ) {
			return '';
		}

		$seconds = strtotime( $dateValue );
		// 'Y' adds leading zeroes to years with fewer than four digits,
		// so remove them.
		$yearString = ltrim( date( 'Y', $seconds ), '0' );
		if ( $datePrecision == CargoStore::YEAR_ONLY ) {
			return $yearString;
		} elseif ( $datePrecision == CargoStore::MONTH_ONLY ) {
			return CargoDrilldownUtils::monthToString( date( 'm', $seconds ) ) .
				" $yearString";
		} else {
			// CargoStore::DATE_AND_TIME or
			// CargoStore::DATE_ONLY
			global $wgAmericanDates;
			if ( $wgAmericanDates ) {
				// We use MediaWiki's representation of month
				// names, instead of PHP's, because its i18n
				// support is of course far superior.
				$dateText = CargoDrilldownUtils::monthToString( date( 'm', $seconds ) );
				$dateText .= ' ' . date( 'j', $seconds ) . ", $yearString";
			} else {
				$dateText = "$yearString-" . date( 'm-d', $seconds );
			}
			// @TODO - remove the redundant 'Date' check at some
			// point. It's here because the "precision" constants
			// changed a ittle in version 0.8.
			if ( $type == 'Date' || $datePrecision == CargoStore::DATE_ONLY ) {
				return $dateText;
			}

			// It's a Datetime - add time as well.
			global $wgCargo24HourTime;
			if ( $wgCargo24HourTime ) {
				$timeText = date( 'G:i:s', $seconds );
			} else {
				$timeText = date( 'g:i:s A', $seconds );
			}
			return "$dateText $timeText";
		}
	}

	/**
	 * Based on MediaWiki's SqlSearchResult::getTextSnippet()
	 */
	public function getTextSnippet( $text, $terms ) {
		foreach ( $terms as $i => $term ) {
			// Try to map from a MySQL search to a PHP one -
			// this code could probably be improved.
			$term = str_replace( [ '"', "'", '+', '*' ], '', $term );
			// What is the point of this...?
			if ( strpos( $term, '*' ) !== false ) {
				$term = '\b' . $term . '\b';
			}
			$terms[$i] = $term;
		}

		// Replace newlines, etc. with spaces for better readability.
		$text = preg_replace( '/\s+/', ' ', $text );
		$h = new SearchHighlighter();
		if ( count( $terms ) > 0 ) {
			// In the core MediaWiki equivalent of this code,
			// there is a check here of the flag
			// $wgAdvancedSearchHighlighting. Instead, we always
			// call the more expensive function, highlightText()
			// rather than highlightSimple(), because we're not
			// that concerned about performance.
			return $h->highlightText( $text, $terms );
		} else {
			return $h->highlightNone( $text );
		}
	}

	/**
	 * @param CargoDisplayFormat $formatter
	 * @param array[] $queryResults
	 * @return mixed|string
	 */
	public function displayQueryResults( $formatter, $queryResults ) {
		if ( count( $queryResults ) == 0 ) {
			if ( array_key_exists( 'default', $this->mDisplayParams ) ) {
				return $this->mDisplayParams['default'];
			} else {
				return '<em>' . wfMessage( 'table_pager_empty' )->escaped() . '</em>'; // default
			}
		}

		$formattedQueryResults = $this->getFormattedQueryResults( $queryResults, true );
		$text = '';

		// If this is the 'template' format, let the formatter print
		// out the intro and outro, so they can be parsed at the same
		// time as the main body. In theory, this should be done for
		// every result format, but in practice, probably only with
		// 'template' could there be complex formatting (like a table
		// with a header and footer) where this approach to parsing
		// would make a difference.
		if ( array_key_exists( 'intro', $this->mDisplayParams ) && !( $formatter instanceof CargoTemplateFormat ) ) {
			$text .= CargoUtils::smartParse( $this->mDisplayParams['intro'], null );
		}
		try {
			$text .= $formatter->display( $queryResults, $formattedQueryResults, $this->mFieldDescriptions,
				$this->mDisplayParams );
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}
		if ( array_key_exists( 'outro', $this->mDisplayParams ) && !( $formatter instanceof CargoTemplateFormat ) ) {
			$text .= CargoUtils::smartParse( $this->mDisplayParams['outro'], null );
		}
		return $text;
	}

	/**
	 * Display the link to view more results, pointing to Special:ViewData.
	 */
	public function viewMoreResultsLink( $displayHTML = true ) {
		$vd = Title::makeTitleSafe( NS_SPECIAL, 'ViewData' );
		if ( array_key_exists( 'more results text', $this->mDisplayParams ) ) {
			$moreResultsText = htmlspecialchars( $this->mDisplayParams['more results text'] );
			// If the value is blank, don't show a link at all.
			if ( $moreResultsText == '' ) {
				return '';
			}
		} else {
			$moreResultsText = wfMessage( 'moredotdotdot' )->parse();
		}

		$queryStringParams = [];
		$sqlQuery = $this->mSQLQuery;
		$queryStringParams['tables'] = $sqlQuery->mTablesStr;
		$queryStringParams['fields'] = $sqlQuery->mFieldsStr;
		if ( $sqlQuery->mOrigWhereStr != '' ) {
			$queryStringParams['where'] = $sqlQuery->mOrigWhereStr;
		}
		if ( $sqlQuery->mJoinOnStr != '' ) {
			$queryStringParams['join_on'] = $sqlQuery->mJoinOnStr;
		}
		if ( $sqlQuery->mOrigGroupByStr != '' ) {
			$queryStringParams['group_by'] = $sqlQuery->mOrigGroupByStr;
		}
		if ( $sqlQuery->mOrigHavingStr != '' ) {
			$queryStringParams['having'] = $sqlQuery->mOrigHavingStr;
		}
		$queryStringParams['order_by'] = $sqlQuery->mOrigOrderBy;
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
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$link = CargoUtils::makeLink( $linkRenderer, $vd, $moreResultsText, [], $queryStringParams );
			return Html::rawElement( 'p', null, $link );
		} else {
			// Display link as wikitext.
			global $wgServer;
			return '[' . $wgServer . $vd->getLinkURL( $queryStringParams ) . ' ' . $moreResultsText . ']';
		}
	}

}

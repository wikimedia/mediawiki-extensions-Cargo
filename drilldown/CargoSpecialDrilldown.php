<?php
/**
 * Displays an interface to let the user drill down through all Cargo data.
 *
 * Based heavily on SD_BrowseData.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDrilldown extends IncludableSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'Drilldown' );
	}

	function execute( $query ) {
		global $cgScriptPath;

		$request = $this->getRequest();
		$out = $this->getOutput();
		$title = $this->getPageTitle();

		if ( $this->including() ) {
			global $wgParser;
			$wgParser->disableCache();
		}
		$this->setHeaders();
		$out->addModules( 'ext.cargo.drilldown' );
		$out->addScript( '<!--[if IE]><link rel="stylesheet" href="' . $cgScriptPath .
			'/drilldown/resources/CargoDrilldownIEFixes.css" media="screen" /><![endif]-->' );

		// Should this be a user setting?
		$numResultsPerPage = 250;
		list( $limit, $offset ) = wfCheckLimits( $numResultsPerPage, 'limit' );
		// Get information on current table and the filters
		// that have already been applied from the query string.
		$tableName = str_replace( '_', ' ', $request->getVal( '_table' ) );
		// if query string did not contain this variable, try the URL
		if ( !$tableName ) {
			$queryparts = explode( '/', $query, 1 );
			$tableName = isset( $queryparts[0] ) ? $queryparts[0] : '';
		}
		if ( !$tableName ) {
			$tableTitle = $this->msg( 'drilldown' )->text();
		} else {
			$tableTitle = $this->msg( 'drilldown' )->text() . html_entity_decode(
					$this->msg( 'colon-separator' )->text() ) . str_replace( '_', ' ', $tableName );
		}
		// If no table was specified, go with the first table,
		// alphabetically.
		if ( !$tableName ) {
			$tableNames = CargoUtils::getTables();
			if ( count( $tableNames ) == 0 ) {
				// There are no tables - just exit now.
				return 0;
			}
			$tableName = $tableNames[0];
		}

		$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
		$filters = array();
		foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// Skip "hidden" fields.
			if ( $fieldDescription->mIsHidden ) {
				continue;
			}

			// Skip coordinate fields.
			if ( $fieldDescription->mType == 'Coordinates' ) {
				continue;
			}

			$curFilter = new CargoFilter();
			$curFilter->setName( $fieldName );
			$curFilter->setTableName( $tableName );
			$curFilter->setFieldDescription( $fieldDescription );
			$filters[] = $curFilter;
		}

		$filter_used = array();
		foreach ( $filters as $i => $filter ) {
			$filter_used[] = false;
		}
		$applied_filters = array();
		$remaining_filters = array();
		foreach ( $filters as $i => $filter ) {
			$filter_name = str_replace( array( ' ', "'" ), array( '_', "\'" ), $filter->name );
			$search_terms = $request->getArray( '_search_' . $filter_name );
			$lower_date = $request->getArray( '_lower_' . $filter_name );
			$upper_date = $request->getArray( '_upper_' . $filter_name );
			if ( $vals_array = $request->getArray( $filter_name ) ) {
				foreach ( $vals_array as $j => $val ) {
					$vals_array[$j] = str_replace( '_', ' ', $val );
				}
				$applied_filters[] = CargoAppliedFilter::create( $filter, $vals_array );
				$filter_used[$i] = true;
			} elseif ( $search_terms != null ) {
				$applied_filters[] = CargoAppliedFilter::create( $filter, array(), $search_terms );
				$filter_used[$i] = true;
			} elseif ( $lower_date != null || $upper_date != null ) {
				$applied_filters[] = CargoAppliedFilter::create( $filter, array(), null, $lower_date,
						$upper_date );
				$filter_used[$i] = true;
			}
		}
		// add every unused filter to the $remaining_filters array,
		// unless it requires some other filter that hasn't been applied
		foreach ( $filters as $i => $filter ) {
			$matched_all_required_filters = true;
			foreach ( $filter->required_filters as $required_filter ) {
				$found_match = false;
				foreach ( $applied_filters as $af ) {
					if ( $af->filter->name == $required_filter ) {
						$found_match = true;
					}
				}
				if ( !$found_match ) {
					$matched_all_required_filters = false;
					continue;
				}
			}
			if ( $matched_all_required_filters ) {
				if ( !$filter_used[$i] ) $remaining_filters[] = $filter;
			}
		}

		$out->addHTML( "\n\t\t\t\t<div class=\"drilldown-results\">\n" );
		$rep = new CargoDrilldownPage(
			$tableName, $applied_filters, $remaining_filters, $offset, $limit );
		$num = $rep->execute( $query );
		$out->addHTML( "\n\t\t\t</div> <!-- drilldown-results -->\n" );

		// This has to be set last, because otherwise the QueryPage
		// code will overwrite it.
		$out->setPageTitle( $tableTitle );

		return $num;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

class CargoDrilldownPage extends QueryPage {
	public $tableName = "";
	public $applied_filters = array();
	public $remaining_filters = array();
	public $showSingleTable = false;

	/**
	 * Initialize the variables of this page
	 *
	 * @param string $tableName
	 * @param array $applied_filters
	 * @param array $remaining_filters
	 * @param int $offset
	 * @param int $limit
	 */
	function __construct( $tableName, $applied_filters, $remaining_filters, $offset, $limit ) {
		parent::__construct( 'Drilldown' );

		$this->tableName = $tableName;
		$this->applied_filters = $applied_filters;
		$this->remaining_filters = $remaining_filters;
		$this->offset = $offset;
		$this->limit = $limit;
	}

	/**
	 *
	 * @param string $tableName
	 * @param array $applied_filters
	 * @param string $filter_to_remove
	 * @return string
	 */
	function makeBrowseURL( $tableName, $applied_filters = array(), $filter_to_remove = null ) {
		$dd = SpecialPage::getTitleFor( 'Drilldown' );
		$url = $dd->getLocalURL() . '/' . $tableName;
		if ( $this->showSingleTable ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= "_single";
		}
		foreach ( $applied_filters as $af ) {
			if ( $af->filter->name == $filter_to_remove ) {
				continue;
			}
			if ( count( $af->values ) == 0 ) {
				// do nothing
			} elseif ( count( $af->values ) == 1 ) {
				$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
				$url .= urlencode( str_replace( ' ', '_', $af->filter->name ) ) . "=" .
					urlencode( str_replace( ' ', '_', $af->values[0]->text ) );
			} else {
				usort( $af->values, array( "CargoFilterValue", "compare" ) );
				foreach ( $af->values as $j => $fv ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
					$url .= urlencode( str_replace( ' ', '_', $af->filter->name ) ) . "[$j]=" .
						urlencode( str_replace( ' ', '_', $fv->text ) );
				}
			}
			if ( $af->search_terms != null ) {
				foreach ( $af->search_terms as $j => $search_term ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
					$url .= '_search_' . urlencode( str_replace( ' ', '_', $af->filter->name ) .
							'[' . $j . ']' ) . "=" . urlencode( str_replace( ' ', '_', $search_term ) );
				}
			}
		}
		return $url;
	}

	function getName() {
		return "Drilldown";
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function printTablesList( $tables ) {
		global $wgCargoDrilldownUseTabs;

		$chooseTableText = $this->msg( 'cargo-drilldown-choosetable' )->text() .
			$this->msg( 'colon-separator' )->text();
		if ( $wgCargoDrilldownUseTabs ) {
			$cats_wrapper_class = "drilldown-tables-tabs-wrapper";
			$cats_list_class = "drilldown-tables-tabs";
		} else {
			$cats_wrapper_class = "drilldown-tables-wrapper";
			$cats_list_class = "drilldown-tables";
		}
		$text = <<<END

				<div id="$cats_wrapper_class">

END;
		if ( $wgCargoDrilldownUseTabs ) {
			$text .= <<<END
					<p id="tableNamesHeader">$chooseTableText</p>
					<ul id="$cats_list_class">

END;
		} else {
			$text .= <<<END
					<ul id="$cats_list_class">
					<li id="tableNamesHeader">$chooseTableText</li>

END;
		}
		$cdb = CargoUtils::getDB();
		foreach ( $tables as $table ) {
			$res = $cdb->select( $table, 'COUNT(*)' );
			$row = $cdb->fetchRow( $res );
			$tableRows = $row[0];
			$realTableName = str_replace( '_', ' ', $table );
			$tableStr = "$realTableName ($tableRows)";
			if ( $this->tableName == $table ) {
				$text .= '						<li class="tableName selected">';
				$text .= $tableStr;
			} else {
				$text .= '						<li class="tableName">';
				$tableURL = $this->makeBrowseURL( $table );
				$text .= Html::element( 'a', array( 'href' => $tableURL, 'title' => $chooseTableText ),
						$tableStr );
			}
			$text .= "</li>\n";
		}
		$text .= <<<END
					</li>
				</ul>
			</div>

END;
		return $text;
	}

	/**
	 * Create the full display of the filter line, once the text for
	 * the "results" (values) for this filter has been created.
	 */
	function printFilterLine( $filterName, $isApplied, $isNormalFilter, $resultsLine ) {
		global $cgScriptPath;

		$filterLabel = str_replace( '_', ' ', $filterName );
		$text = <<<END
				<div class="drilldown-filter">
					<div class="drilldown-filter-label">

END;
		// No point showing arrow if it's just a
		// single text or date input.
		if ( $isNormalFilter ) {
			if ( $isApplied ) {
				$arrowImage = "$cgScriptPath/drilldown/resources/right-arrow.png";
			} else {
				$arrowImage = "$cgScriptPath/drilldown/resources/down-arrow.png";
			}
			$text .= <<<END
					<a class="drilldown-values-toggle" style="cursor: default;"><img src="$arrowImage" /></a>

END;
		}
		$text .= "\t\t\t\t\t$filterLabel:";
		if ( $isApplied ) {
			$add_another_str = $this->msg( 'cargo-drilldown-addanothervalue' )->text();
			$text .= " <span class=\"drilldown-filter-notes\">($add_another_str)</span>";
		}
		$displayText = ( $isApplied ) ? 'style="display: none;"' : '';
		$text .= <<<END

					</div>
					<div class="drilldown-filter-values" $displayText>$resultsLine
					</div>
				</div>

END;
		return $text;
	}

	/**
	 * Print a "nice" version of the value for a filter, if it's some
	 * special case like 'other', 'none', a boolean, etc.
	 */
	function printFilterValue( $filter, $value ) {
		$value = str_replace( '_', ' ', $value );
		// if it's boolean, display something nicer than "0" or "1"
		if ( $value === ' other' ) {
			return Html::element( 'span', array( 'style' => 'font-style: italic;' ),
					$this->msg( 'htmlform-selectorother-other' )->text() );
		} elseif ( $value === ' none' ) {
			return Html::element( 'span', array( 'style' => 'font-style: italic;' ),
					$this->msg( 'powersearch-togglenone' )->text() );
		} elseif ( $filter->fieldDescription->mType === 'Boolean' ) {
			// Use existing MW messages for "Yes" and "No".
			if ( $value == true ) {
				return $this->msg( 'htmlform-yes' )->text();
			} else {
				return $this->msg( 'htmlform-no' )->text();
			}
		} else {
			return $value;
		}
	}

	/**
	 * Print the line showing 'OR' values for a filter that already has
	 * at least one value set
	 */
	function printAppliedFilterLine( $af ) {
		$results_line = "";
		$current_filter_values = array();
		foreach ( $this->applied_filters as $af2 ) {
			if ( $af->filter->name == $af2->filter->name ) {
				$current_filter_values = $af2->values;
			}
		}
		if ( $af->filter->allowed_values != null ) {
			$or_values = $af->filter->allowed_values;
		} else {
			$or_values = $af->getAllOrValues();
		}
		if ( $af->search_terms != null ) {
			$curSearchTermNum = count( $af->search_terms );
			if ( count( $or_values ) >= 300 ) {
				$results_line = $this->printTextInput( $af->filter->name, $curSearchTermNum );
			} else {
				// HACK - printComboBoxInput() needs values as
				// the *keys* of the array
				$filter_values = array();
				foreach ( $or_values as $or_value ) {
					$filter_values[$or_value] = '';
				}
				$results_line = $this->printComboBoxInput(
					$af->filter->name, $curSearchTermNum, $filter_values );
				}
			return $this->printFilterLine( $af->filter->name, true, true, $results_line );
		}
		// add 'Other' and 'None', regardless of whether either has
		// any results - add 'Other' only if it's not a date field
		if ( $af->filter->fieldDescription->mType != 'Date' ) {
			$or_values[] = '_other';
		}
		$or_values[] = '_none';
		foreach ( $or_values as $i => $value ) {
			if ( $i > 0 ) {
				$results_line .= " · ";
			}
			$filter_text = $this->printFilterValue( $af->filter, $value );
			$applied_filters = $this->applied_filters;
			foreach ( $applied_filters as $af2 ) {
				if ( $af->filter->name == $af2->filter->name ) {
					$or_fv = CargoFilterValue::create( $value, $af->filter );
					$af2->values = array_merge( $current_filter_values, array( $or_fv ) );
				}
			}
			// show the list of OR values, only linking
			// the ones that haven't been used yet
			$found_match = false;
			foreach ( $current_filter_values as $fv ) {
				if ( $value == $fv->text ) {
					$found_match = true;
					break;
				}
			}
			if ( $found_match ) {
				$results_line .= "\n\t\t\t\t$filter_text";
			} else {
				$filter_url = $this->makeBrowseURL( $this->tableName, $applied_filters );
				$results_line .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
						array( 'href' => $filter_url,
						'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text() ), $filter_text );
			}
			foreach ( $applied_filters as $af2 ) {
				if ( $af->filter->name == $af2->filter->name ) {
					$af2->values = $current_filter_values;
				}
			}
		}
		return $this->printFilterLine( $af->filter->name, true, true, $results_line );
	}

	function printUnappliedFilterValues( $cur_url, $f, $filter_values ) {
		global $wgCargoDrilldownSmallestFontSize, $wgCargoDrilldownLargestFontSize;

		$results_line = "";
		// set font-size values for filter "tag cloud", if the
		// appropriate global variables are set
		if ( $wgCargoDrilldownSmallestFontSize > 0 && $wgCargoDrilldownLargestFontSize > 0 ) {
			$lowest_num_results = min( $filter_values );
			$highest_num_results = max( $filter_values );
			if ( $lowest_num_results != $highest_num_results ) {
				$scale_factor = ( $wgCargoDrilldownLargestFontSize - $wgCargoDrilldownSmallestFontSize ) /
					( log( $highest_num_results ) - log( $lowest_num_results ) );
			}
		}
		// now print the values
		$num_printed_values = 0;
		foreach ( $filter_values as $value_str => $num_results ) {
			if ( $num_printed_values++ > 0 ) {
				$results_line .= " · ";
			}
			$filter_text = $this->printFilterValue( $f, $value_str );
			$filter_text .= " ($num_results)";
			$filter_url = $cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
				urlencode( str_replace( ' ', '_', $value_str ) );
			if ( $wgCargoDrilldownSmallestFontSize > 0 && $wgCargoDrilldownLargestFontSize > 0 ) {
				if ( $lowest_num_results != $highest_num_results ) {
					$font_size = round( ((log( $num_results ) - log( $lowest_num_results )) * $scale_factor ) +
						$wgCargoDrilldownSmallestFontSize );
				} else {
					$font_size = ( $wgCargoDrilldownSmallestFontSize + $wgCargoDrilldownLargestFontSize ) / 2;
				}
				$results_line .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
					array( 'href' => $filter_url,
						'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text(),
						'style' => "font-size: {$font_size}px"
					 ), $filter_text );
			} else {
				$results_line .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
					array( 'href' => $filter_url,
						'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text()
					), $filter_text );
			}
		}
		return $results_line;
	}

	/**
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/NumberUtils.js)
	 * - though that one is in Javascript.
	 */
	function getNearestNiceNumber( $num, $previousNum, $nextNum ) {
		if ( is_null( $previousNum ) ) {
			$smallestDifference = $nextNum - $num;
		} elseif ( is_null( $nextNum ) ) {
			$smallestDifference = $num - $previousNum;
		} else {
			$smallestDifference = min( $num - $previousNum, $nextNum - $num );
		}

		$base10LogOfDifference = log10( $smallestDifference );
		$significantFigureOfDifference = floor( $base10LogOfDifference );

		$powerOf10InCorrectPlace = pow( 10, $significantFigureOfDifference );
		$significantDigitsOnly = round( $num / $powerOf10InCorrectPlace );
		$niceNumber = $significantDigitsOnly * $powerOf10InCorrectPlace;

		// Special handling if it's the first or last number in the
		// series - we have to make sure that the "nice" equivalent is
		// on the right "side" of the number.
		//
		// That's especially true for the last number -
		// it has to be greater, not just equal to, because of the way
		// number filtering works.
		// ...or does it??
		if ( $previousNum == null && $niceNumber > $num ) {
			$niceNumber -= $powerOf10InCorrectPlace;
		}
		if ( $nextNum == null && $niceNumber < $num ) {
			$niceNumber += $powerOf10InCorrectPlace;
		}

		return $niceNumber;
	}

	/**
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/NumberUtils.js)
	 * - though that one is in Javascript.
	 */
	function generateIndividualFilterValuesFromNumbers( $uniqueValues ) {
		$propertyValues = array();
		foreach ( $uniqueValues as $uniqueValue => $numInstances ) {
			$propertyValues[] = array(
				'lowerNumber' => $uniqueValue,
				'higherNumber' => null,
				'numValues' => $numInstances
			);
		}
		return $propertyValues;
	}

	/**
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/NumberUtils.js)
	 * - though that one is in Javascript.
	 */
	function generateFilterValuesFromNumbers( $numberArray ) {
		global $wgCargoDrilldownNumRangesForNumbers;

		$numNumbers = count( $numberArray );

		// First, find the number of unique values - if it's the value
		// of $wgCargoDrilldownNumRangesForNumbers, or fewer, just display
		// each one as its own bucket.
		$numUniqueValues = 0;
		$uniqueValues = array();
		foreach ( $numberArray as $curNumber ) {
			if ( !array_key_exists( $curNumber, $uniqueValues ) ) {
				$uniqueValues[$curNumber] = 1;
				$numUniqueValues++;
				if ( $numUniqueValues > $wgCargoDrilldownNumRangesForNumbers ) {
					continue;
				}
			} else {
				// We do this now to save time on the next step,
				// if we're creating individual filter values.
				$uniqueValues[$curNumber] ++;
			}
		}

		if ( $numUniqueValues <= $wgCargoDrilldownNumRangesForNumbers ) {
			return $this->generateIndividualFilterValuesFromNumbers( $uniqueValues );
		}

		$propertyValues = array();
		$separatorValue = $numberArray[0];

		// Make sure there are at least, on average, five numbers per
		// bucket.
		// @HACK - add 3 to the number so that we don't end up with
		// just one bucket ( 7 + 3 / 5 = 2).
		$numBuckets = min( $wgCargoDrilldownNumRangesForNumbers, floor( ( $numNumbers + 3 ) / 5 ) );
		$bucketSeparators = array();
		$bucketSeparators[] = $numberArray[0];
		for ( $i = 1; $i < $numBuckets; $i++ ) {
			$separatorIndex = floor( $numNumbers * $i / $numBuckets ) - 1;
			$previousSeparatorValue = $separatorValue;
			$separatorValue = $numberArray[$separatorIndex];
			if ( $separatorValue == $previousSeparatorValue ) {
				continue;
			}
			$bucketSeparators[] = $separatorValue;
		}
		$lastValue = ceil( $numberArray[count( $numberArray ) - 1] );
		if ( $lastValue != $separatorValue ) {
			$bucketSeparators[] = $lastValue;
		}

		// Get the closest "nice" (few significant digits) number for
		// each of the bucket separators, with the number of significant digits
		// required based on their proximity to their neighbors.
		// The first and last separators need special handling.
		$bucketSeparators[0] = $this->getNearestNiceNumber( $bucketSeparators[0], null,
			$bucketSeparators[1] );
		for ( $i = 1; $i < count( $bucketSeparators ) - 1; $i++ ) {
			$bucketSeparators[$i] = $this->getNearestNiceNumber( $bucketSeparators[$i],
				$bucketSeparators[$i - 1], $bucketSeparators[$i + 1] );
		}
		$bucketSeparators[count( $bucketSeparators ) - 1] = $this->getNearestNiceNumber(
			$bucketSeparators[count( $bucketSeparators ) - 1],
			$bucketSeparators[count( $bucketSeparators ) - 2], null );

		$oldSeparatorValue = $bucketSeparators[0];
		for ( $i = 1; $i < count( $bucketSeparators ); $i++ ) {
			$separatorValue = $bucketSeparators[$i];
			$propertyValues[] = array(
				'lowerNumber' => $oldSeparatorValue,
				'higherNumber' => $separatorValue,
				'numValues' => 0,
			);
			$oldSeparatorValue = $separatorValue;
		}

		$curSeparator = 0;
		for ( $i = 0; $i < count( $numberArray ); $i++ ) {
			if ( $curSeparator < count( $propertyValues ) - 1 ) {
				$curNumber = $numberArray[$i];
				while ( $curNumber >= $bucketSeparators[$curSeparator + 1] ) {
					$curSeparator++;
				}
			}
			$propertyValues[$curSeparator]['numValues'] ++;
		}

		return $propertyValues;
	}

	function printNumber( $num ) {
		global $wgCargoDecimalMark, $wgCargoDigitGroupingCharacter;

		// Get the precision, i.e. the number of decimals to display.
		// Copied from http://stackoverflow.com/questions/2430084/php-get-number-of-decimal-digits
		if ( (int)$num == $num ) {
			$numDecimals = 0;
		} else {
			$numDecimals = strlen( $num ) - strrpos( $num, '.' ) - 1;
		}

		// number_format() adds in commas for each thousands place.
		return number_format( $num, $numDecimals, $wgCargoDecimalMark,
			$wgCargoDigitGroupingCharacter );
	}

	function printNumberRanges( $filter_name, $filter_values ) {
		// We generate $cur_url here, instead of passing it in, because
		// if there's a previous value for this filter it may be
		// removed.
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->applied_filters, $filter_name );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';

		$numberArray = array();
		$numNoneValues = 0;
		foreach ( $filter_values as $value => $num_instances ) {
			if ( $value == ' none' ) {
				$numNoneValues = $num_instances;
			} else {
				for ( $i = 0; $i < $num_instances; $i++ ) {
					$numberArray[] = $value;
				}
			}
		}
		// Put into numerical order.
		sort( $numberArray );

		$text = '';
		$filterValues = $this->generateFilterValuesFromNumbers( $numberArray );

		// If there were any 'none' values, add them in to the
		// beginning of the array.
		if ( $numNoneValues > 0 ) {
			$noneBucket = array(
				'lowerNumber' => ' none',
				'higherNumber' => null,
				'numValues' => $numNoneValues
			);
			array_unshift( $filterValues, $noneBucket );
		}

		foreach ( $filterValues as $i => $curBucket ) {
			if ( $i > 0 ) {
				$text .= " &middot; ";
			}
			if ( $curBucket['lowerNumber'] === ' none' ) {
				$curText = $this->printFilterValue( null, ' none' );
			} else {
				$curText = $this->printNumber( $curBucket['lowerNumber'] );
				if ( $curBucket['higherNumber'] != null ) {
					$curText .= ' - ' . $this->printNumber( $curBucket['higherNumber'] );
				}
			}
			$curText .= ' (' . $curBucket['numValues'] . ') ';
			$filterURL = $cur_url . "$filter_name=" . $curBucket['lowerNumber'];
			if ( $curBucket['higherNumber'] != null ) {
				$filterURL .= '-' . $curBucket['higherNumber'];
			}
			$text .= '<a href="' . $filterURL . '">' . $curText . '</a>';
		}
		return $text;
	}

	function printTextInput( $filter_name, $instance_num, $cur_value = null ) {
		global $wgRequest;

		$filter_name = str_replace( ' ', '_', $filter_name );
		// URL-decode the filter name - necessary if it contains
		// any non-Latin characters.
		$filter_name = urldecode( $filter_name );

		// Add on the instance number, since it can be one of a string
		// of values.
		$filter_name .= '[' . $instance_num . ']';

		$inputName = "_search_$filter_name";

		$text = <<< END
<form method="get">

END;

		foreach ( $wgRequest->getValues() as $key => $val ) {
			if ( $key != $inputName ) {
				if ( is_array( $val ) ) {
					foreach ( $val as $i => $realVal ) {
						$keyString = $key . '[' . $i . ']';
						$text .= Html::hidden( $keyString, $realVal ) . "\n";
					}
				} else {
					$text .= Html::hidden( $key, $val ) . "\n";
				}
			}
		}

		$text .=<<< END
	<input type="text" name="$inputName" value="$cur_value" />
	<br />

END;

		$text .= Html::input( null, $this->msg( 'searchresultshead' )->text(), 'submit',
				array( 'style' => 'margin: 4px 0 8px 0;' ) ) . "\n";
		$text .= "</form>\n";
		return $text;
	}

	function printComboBoxInput( $filter_name, $instance_num, $filter_values, $cur_value = null ) {
		global $wgRequest;

		$filter_name = str_replace( ' ', '_', $filter_name );
		// URL-decode the filter name - necessary if it contains
		// any non-Latin characters.
		$filter_name = urldecode( $filter_name );

		// Add on the instance number, since it can be one of a string
		// of values.
		$filter_name .= '[' . $instance_num . ']';

		$inputName = "_search_$filter_name";

		$text = <<< END
<form method="get">

END;

		foreach ( $wgRequest->getValues() as $key => $val ) {
			if ( $key != $inputName ) {
				if ( is_array( $val ) ) {
					foreach ( $val as $i => $realVal ) {
						$keyString = $key . '[' . $i . ']';
						$text .= Html::hidden( $keyString, $realVal ) . "\n";
					}
				} else {
					$text .= Html::hidden( $key, $val ) . "\n";
				}
			}
		}

		$text .=<<< END
	<div class="ui-widget">
		<select class="cargoDrilldownComboBox" name="$cur_value">
			<option value="$inputName"></option>;

END;
		foreach ( $filter_values as $value => $num_instances ) {
			if ( $value != '_other' && $value != '_none' ) {
				$display_value = str_replace( '_', ' ', $value );
				$text .= "\t\t" . Html::element( 'option', array(
						'value' => $display_value ), $display_value ) . "\n";
			}
		}

		$text .=<<<END
		</select>
	</div>

END;

		$text .= Html::input( null, $this->msg( 'searchresultshead' )->text(), 'submit',
				array( 'style' => 'margin: 4px 0 8px 0;' ) ) . "\n";
		$text .= "</form>\n";
		return $text;
	}

	/**
	 * Appears to be unused
	 *
	 * @global Language $wgContLang
	 * @param string $input_name Used in the HTML name attribute
	 * @param type $cur_value an array that may contain the keys 'day', 'month' & 'year' with an
	 * integer value
	 * @return string
	 */
	function printDateInput( $input_name, $cur_value = null ) {
		/** @todo Shouldn't this use the user language? */
		global $wgContLang;
		$month_names = $wgContLang->getMonthNamesArray();

		if ( is_array( $cur_value ) && array_key_exists( 'month', $cur_value ) ) {
			$selected_month = $cur_value['month'];
		} else {
			$selected_month = null;
		}
		$text = ' <select name="' . $input_name . "[month]\">\n";
		/** @todo Always ouputs an American Date. Should use global. */
		# global $wgAmericanDates;
		foreach ( $month_names as $i => $name ) {
			// pad out month to always be two digits
			$month_value = str_pad( $i, 2, "0", STR_PAD_LEFT );
			$selected_str = ( $i == $selected_month ) ? "selected" : "";
			$text .= "\t<option value=\"$month_value\" $selected_str>$name</option>\n";
		}
		$text .= "\t</select>\n";
		$text .= '<input name="' . $input_name . '[day]" type="text" size="2" value="' .
			$cur_value['day'] . '" />' . "\n";
		$text .= '<input name="' . $input_name . '[year]" type="text" size="4" value="' .
			$cur_value['year'] . '" />' . "\n";
		return $text;
	}

	/**
	 * Print the line showing 'AND' values for a filter that has not
	 * been applied to the drilldown
	 */
	function printUnappliedFilterLine( $f, $cur_url = null ) {
		global $wgCargoDrilldownMinValuesForComboBox;

		$fieldType = $f->fieldDescription->mType;
		if ( $fieldType == 'Date' ) {
			$filter_values = $f->getTimePeriodValues( $this->applied_filters );
		} else {
			$filter_values = $f->getAllValues( $this->applied_filters );
		}
		if ( !is_array( $filter_values ) ) {
			return $this->printFilterLine( $f->name, false, false, $filter_values );
		}

		$filter_name = urlencode( str_replace( ' ', '_', $f->name ) );
		$normal_filter = true;
		if ( count( $filter_values ) == 0 ) {
			$results_line = '(' . $this->msg( 'cargo-drilldown-novalues' )->text() . ')';
		} elseif ( $fieldType == 'Integer' || $fieldType == 'Float' ) {
			$results_line = $this->printNumberRanges( $filter_name, $filter_values );
		} elseif ( count( $filter_values ) >= 300 ) {
			// If it's really big, just show a text input.
			// @TODO - this should ideally use remote
			// autocompletion instead.
			$results_line = $this->printTextInput( $filter_name, 0 );
			$normal_filter = false;
		} elseif ( count( $filter_values ) >= $wgCargoDrilldownMinValuesForComboBox ) {
			$results_line = $this->printComboBoxInput( $filter_name, 0, $filter_values );
			$normal_filter = false;
		} else {
			// If $cur_url wasn't passed in, we have to create it.
			$cur_url = $this->makeBrowseURL( $this->tableName, $this->applied_filters, $f->name );
			$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
			$results_line = $this->printUnappliedFilterValues( $cur_url, $f, $filter_values );
		}

		$text = $this->printFilterLine( $f->name, false, $normal_filter, $results_line );
		return $text;
	}

	function getPageHeader() {
		global $wgRequest;
		global $cgScriptPath;

		$tables = CargoUtils::getTables();
		// if there are no tables, escape quickly
		if ( count( $tables ) == 0 ) {
			return "";
		}

		$header = "";
		$this->showSingleTable = $wgRequest->getCheck( '_single' );
		if ( !$this->showSingleTable ) {
			$header .= $this->printTablesList( $tables );
		}
		// If there are no fields for this table,
		// escape now that we've (possibly) printed the
		// tables list
		if ( ( count( $this->applied_filters ) == 0 ) &&
			( count( $this->remaining_filters ) == 0 ) ) {
			return $header;
		}
		$header .= '				<div id="drilldown-header">' . "\n";
		if ( count( $this->applied_filters ) > 0 ) {
			$tableURL = $this->makeBrowseURL( $this->tableName );
			$header .= '<a href="' . $tableURL . '" title="' .
				$this->msg( 'cargo-drilldown-resetfilters' )->text() . '">' .
				str_replace( '_', ' ', $this->tableName ) . '</a>';
		} else {
			$header .= str_replace( '_', ' ', $this->tableName );
		}
		foreach ( $this->applied_filters as $i => $af ) {
			$header .= ( $i == 0 ) ? " > " :
				"\n\t\t\t\t\t<span class=\"drilldown-header-value\">&</span> ";
			$filter_label = str_replace( '_', ' ', $af->filter->name );
			// Add an "x" to remove this filter, if it has more
			// than one value.
			if ( count( $this->applied_filters[$i]->values ) > 1 ) {
				$temp_filters_array = $this->applied_filters;
				array_splice( $temp_filters_array, $i, 1 );
				$remove_filter_url = $this->makeBrowseURL( $this->tableName, $temp_filters_array );
				array_splice( $temp_filters_array, $i, 0 );
				$header .= $filter_label . ' <a href="' . $remove_filter_url . '" title="' .
					$this->msg( 'cargo-drilldown-removefilter' )->text() .
					'"><img src="' . $cgScriptPath . '/drilldown/resources/filter-x.png" /></a> : ';
			} else {
				$header .= "$filter_label: ";
			}
			foreach ( $af->values as $j => $fv ) {
				if ( $j > 0 ) {
					$header .= ' <span class="drilldown-or">' .
						$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
				}
				$filter_text = $this->printFilterValue( $af->filter, $fv->text );
				$temp_filters_array = $this->applied_filters;
				$removed_values = array_splice( $temp_filters_array[$i]->values, $j, 1 );
				$remove_filter_url = $this->makeBrowseURL( $this->tableName, $temp_filters_array );
				array_splice( $temp_filters_array[$i]->values, $j, 0, $removed_values );
				$header .= "\n	" . '				<span class="drilldown-header-value">' .
					$filter_text . '</span> <a href="' . $remove_filter_url . '" title="' .
					$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
					$cgScriptPath . '/drilldown/resources/filter-x.png" /></a>';
			}

			if ( $af->search_terms != null ) {
				foreach ( $af->search_terms as $j => $search_term ) {
					if ( $j > 0 ) {
						$header .= ' <span class="drilldown-or">' .
							$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
					}
					$temp_filters_array = $this->applied_filters;
					$removed_values = array_splice( $temp_filters_array[$i]->search_terms, $j, 1 );
					$remove_filter_url = $this->makeBrowseURL( $this->tableName, $temp_filters_array );
					array_splice( $temp_filters_array[$i]->search_terms, $j, 0, $removed_values );
					$header .= "\n\t" . '<span class="drilldown-header-value">~ \'' . $search_term .
						'\'</span> <a href="' . $remove_filter_url . '" title="' .
						$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
						$cgScriptPath . '/drilldown/resources/filter-x.png" /> </a>';
				}
			} elseif ( $af->lower_date != null || $af->upper_date != null ) {
				$header .= "\n\t" . Html::element( 'span', array( 'class' => 'drilldown-header-value' ),
						$af->lower_date_string . " - " . $af->upper_date_string );
			}
		}
		$header .= "</div>\n";
		$drilldown_description = $this->msg( 'cargo-drilldown-docu' )->text();
		$header .= "				<p>$drilldown_description</p>\n";
		// Display every filter, each on its own line; each line will
		// contain the possible values, and, in parentheses, the
		// number of pages that match that value.
		$header .= "				<div class=\"drilldown-filters\">\n";
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->applied_filters );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
		$tableSchemas = CargoUtils::getTableSchemas( array( $this->tableName ) );
		$tableSchema = $tableSchemas[$this->tableName];
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldType = $fieldDescription->mType;
			// Some field types shouldn't get a filter at all.
			if ( in_array( $fieldType, array( 'URL', 'Wikitext' ) ) ) {
				continue;
			} elseif ( $fieldType == 'Text' && $fieldDescription->mSize != null &&
				$fieldDescription->mSize > 100 ) {
				continue;
			}
			$f = new CargoFilter();
			$f->setName( $fieldName );
			$f->setTableName( $this->tableName );
			$f->setFieldDescription( $fieldDescription );
			foreach ( $this->applied_filters as $af ) {
				if ( $af->filter->name == $f->name ) {
					if ( $fieldType == 'Date' || $fieldType == 'Integer' || $fieldType == 'Float' ) {
						$header .= $this->printUnappliedFilterLine( $f );
					} else {
						$header .= $this->printAppliedFilterLine( $af );
					}
				}
			}
			foreach ( $this->remaining_filters as $rf ) {
				if ( $rf->name == $f->name ) {
					$header .= $this->printUnappliedFilterLine( $rf, $cur_url );
				}
			}
		}
		$header .= "				</div> <!-- drilldown-filters -->\n";
		return $header;
	}

	/**
	 * Used to set URL for additional pages of results.
	 */
	function linkParameters() {
		$params = array();
		if ( $this->showSingleTable ) {
			$params['_single'] = null;
		}
		$params['_table'] = $this->tableName;
		foreach ( $this->applied_filters as $i => $af ) {
			if ( count( $af->values ) == 1 ) {
				$key_string = str_replace( ' ', '_', $af->filter->name );
				$value_string = str_replace( ' ', '_', $af->values[0]->text );
				$params[$key_string] = $value_string;
			} else {
				// HACK - QueryPage's pagination-URL code,
				// which uses wfArrayToCGI(), doesn't support
				// two-dimensional arrays, which is what we
				// need - instead, add the brackets directly
				// to the key string
				foreach ( $af->values as $i => $value ) {
					$key_string = str_replace( ' ', '_', $af->filter->name . "[$i]" );
					$value_string = str_replace( ' ', '_', $value->text );
					$params[$key_string] = $value_string;
				}
			}
		}
		return $params;
	}

	function getRecacheDB() {
		return CargoUtils::getDB();
	}

	function getQueryInfo() {
		$cdb = CargoUtils::getDB();

		$tableNames = array( $this->tableName );
		$conds = array();
		$joinConds = array();
		foreach ( $this->applied_filters as $i => $af ) {
			$conds[] = $af->checkSQL();
			if ( $af->filter->fieldDescription->mIsList ) {
				$fieldTableName = $this->tableName . '__' . $af->filter->name;
				$tableNames[] = $fieldTableName;
				$joinConds[$fieldTableName] = array(
					'LEFT OUTER JOIN',
					$cdb->tableName( $this->tableName ) . '._ID = ' . $cdb->tableName( $fieldTableName ) . '._rowID'
				);
			}
		}

		$aliasedFieldNames = array(
			'title' => '_pageName',
			'value' => '_pageName',
			'namespace' => '_pageNamespace',
			'ID' => '_pageID'
		);

		$queryInfo = array(
			'tables' => $tableNames,
			'fields' => $aliasedFieldNames,
			'conds' => $conds,
			'join_conds' => $joinConds,
			'options' => array()
		);

		return $queryInfo;
	}

	function getOrderFields() {
		return array( '_pageName' );
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		$title = Title::makeTitle( $result->namespace, $result->value );
		return $skin->makeLinkObj( $title, htmlspecialchars( $title->getText() ) );
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use - Unused
	 * @param Database $dbr Database (read) connection to use
	 * @param int $res Result pointer
	 * @param int $num Number of available result rows - Unused
	 * @param int $offset Paging offset - Unused
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		$valuesTable = array();
		$cdb = CargoUtils::getDB();
		while ( $row = $cdb->fetchRow( $res ) ) {
			$valuesTable[] = array( 'title' => $row['title'] );
		}
		$queryDisplayer = new CargoQueryDisplayer();
		$fieldDescription = new CargoFieldDescription();
		$fieldDescription->mType = 'Page';
		$queryDisplayer->mFieldDescriptions = array( 'title' => $fieldDescription );
		$queryDisplayer->mFormat = 'category';
		$formatter = $queryDisplayer->getFormatter( $out );
		$html = $queryDisplayer->displayQueryResults( $formatter, $valuesTable );
		$out->addHTML( $html );
	}

	function openList( $offset ) {

	}

	function closeList() {
		return "\n\t\t\t<br style=\"clear: both\" />\n";
	}
}

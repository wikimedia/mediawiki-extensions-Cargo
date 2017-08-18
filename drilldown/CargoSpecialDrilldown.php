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
		global $cgScriptPath, $wgCargoPageDataColumns;
		global $wgCargoFileDataColumns;

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

		// Get information on current table and the filters
		// that have already been applied from the query string.
		$tableName = str_replace( '_', ' ', $request->getVal( '_table' ) );
		// if query string did not contain this variable, try the URL
		if ( !$tableName ) {
			$queryparts = explode( '/', $query, 1 );
			$tableName = isset( $queryparts[0] ) ? $queryparts[0] : '';
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
		$all_filters = array();
		$fullTextSearchTerm = null;
		$searchablePages = in_array( 'fullText', $wgCargoPageDataColumns );
		$searchableFiles = false;

		// Get this term, whether or not this is actually a searchable
		// table; no point doing complex logic here to determine that.
		$vals_array = $request->getArray( '_search' );
		if ( $vals_array != null ) {
			$fullTextSearchTerm = $vals_array[0];
		}

		foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !$fieldDescription->mIsHidden && $fieldDescription->mType == 'File' && in_array( 'fullText', $wgCargoFileDataColumns ) ) {
				$searchableFiles = true;
			}
		}

		if ( $searchableFiles ) {
			$numResultsPerPage = 100;
		} else {
			$numResultsPerPage = 250;
		}
		list( $limit, $offset ) = $request->getLimitOffset( $numResultsPerPage, 'limit' );

		foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// Skip "hidden" fields.
			if ( $fieldDescription->mIsHidden ) {
				continue;
			}

			// Some field types shouldn't get a filter at all.
			if ( in_array( $fieldDescription->mType, array( 'Text', 'File', 'Coordinates', 'URL', 'Email', 'Wikitext', 'Searchtext' ) ) ) {
				continue;
			}

			$all_filters[] = new CargoFilter( $fieldName, $tableName, $fieldDescription, $searchablePages, $searchableFiles );
		}

		$filter_used = array();
		foreach ( $all_filters as $i => $filter ) {
			$filter_used[] = false;
		}
		$applied_filters = array();
		$remaining_filters = array();
		foreach ( $all_filters as $i => $filter ) {
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
		// Add every unused filter to the $remaining_filters array,
		// unless it requires some other filter that hasn't been applied.
		foreach ( $all_filters as $i => $filter ) {
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
			$tableName, $all_filters, $applied_filters, $remaining_filters, $fullTextSearchTerm, $searchablePages, $searchableFiles, $offset, $limit );
		$num = $rep->execute( $query );
		$out->addHTML( "\n\t\t\t</div> <!-- drilldown-results -->\n" );

		// This has to be set last, because otherwise the QueryPage
		// code will overwrite it.
		if ( !$tableName ) {
			$tableTitle = $this->msg( 'drilldown' )->text();
		} else {
			$tableTitle = $this->msg( 'drilldown' )->text() . html_entity_decode(
					$this->msg( 'colon-separator' )->text() ) . $rep->displayTableName( $tableName );
		}
		$out->setPageTitle( $tableTitle );

		return $num;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

class CargoDrilldownPage extends QueryPage {
	public $tableName = "";
	public $all_filters = array();
	public $applied_filters = array();
	public $remaining_filters = array();
	public $fullTextSearchTerm;
	public $searchablePages;
	public $searchableFiles;
	public $showSingleTable = false;

	/**
	 * Initialize the variables of this page
	 *
	 * @param string $tableName
	 * @param array $applied_filters
	 * @param array $remaining_filters
	 * @param string $fullTextSearchTerm
	 * @param boolean $searchablePages
	 * @param boolean $searchableFiles
	 * @param int $offset
	 * @param int $limit
	 */
	function __construct( $tableName, $all_filters, $applied_filters, $remaining_filters, $fullTextSearchTerm, $searchablePages, $searchableFiles, $offset, $limit ) {
		parent::__construct( 'Drilldown' );

		$this->tableName = $tableName;
		$this->all_filters = $all_filters;
		$this->applied_filters = $applied_filters;
		$this->remaining_filters = $remaining_filters;
		$this->fullTextSearchTerm = $fullTextSearchTerm;
		$this->searchablePages = $searchablePages;
		$this->searchableFiles = $searchableFiles;
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
	function makeBrowseURL( $tableName, $searchTerm = null, $applied_filters = array(), $filter_to_remove = null ) {
		$dd = SpecialPage::getTitleFor( 'Drilldown' );
		$url = $dd->getLocalURL() . '/' . $tableName;
		if ( $this->showSingleTable ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= "_single";
		}

		if ( $searchTerm != null ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= '_search=' . urlencode( $searchTerm );
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
			if( $cdb->tableExists( $table ) == false ) {
				$text .= '<li class="tableName error">' . $table . "</li>";
				continue;
			}
			$res = $cdb->select( $table, 'COUNT(*) AS total' );
			$row = $cdb->fetchRow( $res );
			$tableRows = $row['total'];
			$tableStr = $this->displayTableName( $table ) . " ($tableRows)";
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

	function displayTableName( $tableName = null ) {
		if ( $tableName == null ) {
			$tableName = $this->tableName;
		}

		if ( $tableName == '_pageData' ) {
			return $this->msg( 'cargo-drilldown-allpages' );
		} elseif ( $tableName == '_fileData' ) {
			return $this->msg( 'cargo-drilldown-allfiles' );
		} else {
			return str_replace( '_', ' ', $tableName );
		}
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
		} elseif ( $filter->fieldDescription->mIsHierarchy && preg_match( "/^~within (.+)/", $value ) ) {
			$matches = array();
			preg_match( "/^~within (.+)/", $value, $matches );
			return wfMessage( 'cargo-drilldown-hierarchy-within', $matches[1] )->parse();
		} else {
			return $value;
		}
	}

	/**
	 * Print the line showing 'OR' values for a filter that already has
	 * at least one value set
	 */
	function printAppliedFilterLine( $af ) {
		if ( $af->filter->fieldDescription->mIsHierarchy ) {
			return $this->printAppliedFilterLineForHierarchy( $af );
		}
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
		// Add 'Other' and 'None', regardless of whether either has
		// any results - add 'Other' only if it's not a date field.
		$fieldType = $af->filter->fieldDescription->mType;
		if ( $fieldType != 'Date' && $fieldType != 'Datetime' ) {
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
				$filter_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $applied_filters );
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

	function printAppliedFilterLineForHierarchy( $af ) {
		$applied_filters = $this->applied_filters;
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, array() );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
		// Drilldown for hierarchy is designed for literal 'drilldown'
		// Therefore it has single filter value applied at anytime
		$filter_value = "";
		$isFilterValueNotWithin = false;
		if ( count ( $af->values ) > 0 ) {
			$filter_value = $af->values[0]->text;
			$matches = array();
			preg_match( "/^~within (.+)/", $filter_value, $matches );
			if ( count( $matches ) > 0 ) {
				$filter_value = $matches[1];
			} else {
				$isFilterValueNotWithin = true;
			}
		}
		$drilldownHierarchyRoot = CargoDrilldownHierarchy::newFromWikiText( $af->filter->fieldDescription->mHierarchyStructure );
		$stack = new SplStack();
		// preorder traversal of the tree
		$stack->push( $drilldownHierarchyRoot );
		while ( !$stack->isEmpty() ) {
			$node = $stack->pop();
			if ( $node->mRootValue === $filter_value ) {
				$drilldownHierarchyRoot = $node;
				break;
			}
			for( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
				$stack->push( $node->mChildren[$i] );
			}
		}
		if ( $isFilterValueNotWithin === true ) {
			CargoDrilldownHierarchy::computeNodeCountForTreeByFilter( $node,
				$af->filter, null, $applied_filters );
			$results_line = wfMessage( 'cargo-drilldown-hierarchy-only', $node->mRootValue )->parse() . " ($node->mExactRootMatchCount)";
		} else {
			$results_line = $this->printFilterValuesForHierarchy( $cur_url, $af->filter, null, $applied_filters, $drilldownHierarchyRoot );
		}
		return $this->printFilterLine( $af->filter->name, false, true, $results_line );
	}

	function printUnappliedFilterValues( $cur_url, $f, $filter_values ) {
		$results_line = "";
		// now print the values
		$num_printed_values = 0;
		foreach ( $filter_values as $value_str => $num_results ) {
			if ( $num_printed_values++ > 0 ) {
				$results_line .= " · ";
			}
			$filter_url = $cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
				urlencode( str_replace( ' ', '_', $value_str ) );
			$results_line .= $this->printFilterValueLink( $f, $value_str, $num_results, $filter_url, $filter_values );
		}
		return $results_line;
	}

	function printUnappliedFilterValuesForHierarchy( $cur_url, $f, $fullTextSearchTerm, $applied_filters ) {
		// construct the tree of CargoDrilldownHierarchy
		$drilldownHierarchyRoot = CargoDrilldownHierarchy::newFromWikiText( $f->fieldDescription->mHierarchyStructure );
		return $this->printFilterValuesForHierarchy( $cur_url, $f, $fullTextSearchTerm, $applied_filters, $drilldownHierarchyRoot );
	}

	function printFilterValuesForHierarchy( $cur_url, $f, $fullTextSearchTerm, $applied_filters, $drilldownHierarchyRoot ) {
		// compute counts
		$filter_values = CargoDrilldownHierarchy::computeNodeCountForTreeByFilter( $drilldownHierarchyRoot,
			$f, $fullTextSearchTerm, $applied_filters );
		$results_line = "";
		$num_printed_values = 0;
		$stack = new SplStack();
		// preorder traversal of the tree
		$stack->push( $drilldownHierarchyRoot );
		while ( !$stack->isEmpty() ) {
			$node = $stack->pop();
			if ( $node != ")" ) {
				if ( $node->mLeft !== 1 ) {
					// check if its not __pseudo_root__ node, then only print
					if ( $num_printed_values++ > 0 ) {
						$results_line .= " · ";
					}
					// generate a url to encode WITHIN search information by a "~within_" prefix in value_str
					$filter_url = $cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
						urlencode( str_replace( ' ', '_', "~within_" . $node->mRootValue ) );
					// generate respective <a> tag with value and its count
					$results_line .= ( $node === $drilldownHierarchyRoot )?$node->mRootValue . " ($node->mWithinTreeMatchCount)":
						$this->printFilterValueLink( $f, $node->mRootValue, $node->mWithinTreeMatchCount, $filter_url, $filter_values );
				}
				if ( count( $node->mChildren ) > 0 ) {
					if ( $node->mLeft !== 1 ) {
						$filter_url = $cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
							urlencode( str_replace( ' ', '_', $node->mRootValue ) );
						$results_line .= " · ";
						$results_line .= "(" . $this->printFilterValueLink( $f,
							wfMessage( 'cargo-drilldown-hierarchy-only', $node->mRootValue )->parse() ,
							$node->mExactRootMatchCount, $filter_url, $filter_values );
						$stack->push( ")" );
					}
					$i = count( $node->mChildren ) - 1;
					while ( $i >= 0 ) {
						$stack->push( $node->mChildren[$i] );
						$i = $i - 1;
					}
				}
			} else {
				$results_line .= " ) ";
			}
		}
		return $results_line;
	}

	function printFilterValueLink( $f, $value_str, $num_results, $filter_url, $filter_values ) {
		global $wgCargoDrilldownSmallestFontSize, $wgCargoDrilldownLargestFontSize;
		// set font-size values for filter_values filter "tag cloud", if the
		// appropriate global variables are set
		$scale_factor = 1;
		if ( $wgCargoDrilldownSmallestFontSize > 0 && $wgCargoDrilldownLargestFontSize > 0 ) {
			$lowest_num_results = min( $filter_values );
			$highest_num_results = max( $filter_values );
			if ( $lowest_num_results != $highest_num_results ) {
				$scale_factor = ( $wgCargoDrilldownLargestFontSize - $wgCargoDrilldownSmallestFontSize ) /
					( log( $highest_num_results ) - log( $lowest_num_results ) );
			}
		}
		$result_line_part = "";
		$filter_text = $this->printFilterValue( $f, $value_str );
		$filter_text .= " ($num_results)";
		if ( $wgCargoDrilldownSmallestFontSize > 0 && $wgCargoDrilldownLargestFontSize > 0 ) {
			if ( $lowest_num_results != $highest_num_results ) {
				$font_size = round( ((log( $num_results ) - log( $lowest_num_results )) * $scale_factor ) +
					$wgCargoDrilldownSmallestFontSize );
			} else {
				$font_size = ( $wgCargoDrilldownSmallestFontSize + $wgCargoDrilldownLargestFontSize ) / 2;
			}
			$result_line_part .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
				array( 'href' => $filter_url,
					'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text(),
					'style' => "font-size: {$font_size}px"
					), $filter_text );
		} else {
			$result_line_part .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
				array( 'href' => $filter_url,
					'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text()
				), $filter_text );
		}
		return $result_line_part;
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
				while ( ( $curSeparator < count( $bucketSeparators ) - 2 ) && ( $curNumber >= $bucketSeparators[$curSeparator + 1] ) ) {
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
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $this->applied_filters, $filter_name );
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

	function printTextInput( $filter_name, $instance_num, $is_full_text_search = false, $cur_value = null, $has_remote_autocompletion = false, $filter_is_list = false ) {
		global $wgRequest;

		$filterStr = str_replace( ' ', '_', $filter_name );
		// URL-decode the filter name - necessary if it contains
		// any non-Latin characters.
		$filterStr = urldecode( $filterStr );

		// Add on the instance number, since it can be one of a string
		// of values.
		$filterStr .= '[' . $instance_num . ']';

		if ( strpos( $filterStr, '_search' ) === 0 ) {
			$inputName = '_search';
		} else {
			$inputName = "_search_$filterStr";
		}

		if ( $cur_value != null ) {
			$cur_value = htmlentities( $cur_value );
		}

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

		if ( $is_full_text_search ) {
			// Make this input narrower than the standard MediaWiki search
			// input, to accomodate the list of tables on the side.
			$text .=<<< END
<div class="oo-ui-iconElement oo-ui-textInputWidget mw-widget-titleInputWidget" style="max-width: 40em;" data-ooui>
	<input type="text" name="$inputName" value="$cur_value" />
<span class='oo-ui-iconElement-icon oo-ui-icon-search'></span>
<span class='oo-ui-indicatorElement-indicator'></span>
</div>

END;
		} else {
			$inputAttrs = array();
			if ( $has_remote_autocompletion ) {
				$inputAttrs['class'] = "cargoDrilldownRemoteAutocomplete";
				$inputAttrs['data-cargo-table'] = $this->tableName;
				$inputAttrs['data-cargo-field'] = $filter_name;
				if ( $filter_is_list ) {
					$inputAttrs['data-cargo-field-is-list'] = true;
				}
				$inputAttrs['size'] = 30;
				$inputAttrs['style'] = 'padding-left: 3px;';
				$whereSQL = '';
				foreach ( $this->applied_filters as $i => $af ) {
					if ( $i > 0 ) {
						$whereSQL .= ' AND ';
					}
					$whereSQL .= $af->checkSQL();
				}
				$inputAttrs['data-cargo-where'] = $whereSQL;
			}
			$text .= Html::input( $inputName, $cur_value, 'text', $inputAttrs ) . "\n";
			$text .= "<br />\n\n";
		}

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
		$isHierarchy = $f->fieldDescription->mIsHierarchy;
		if( $cur_url === null ) {
			// If $cur_url wasn't passed in, we have to create it.
			$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $this->applied_filters, $f->name );
			$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
		}

		if( $isHierarchy ) {
			$results_line = $this->printUnappliedFilterValuesForHierarchy( $cur_url, $f,
				$this->fullTextSearchTerm, $this->applied_filters );
			return $this->printFilterLine( $f->name, false, true, $results_line );
		} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
			$filter_values = $f->getTimePeriodValues( $this->fullTextSearchTerm, $this->applied_filters );
		} else {
			$filter_values = $f->getAllValues( $this->fullTextSearchTerm, $this->applied_filters );
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
		} elseif ( count( $filter_values ) >= 250 ) {
			// Lots of values - switch to remote autocompletion.
			$results_line = $this->printTextInput( $filter_name, 0, false, null, true, $f->fieldDescription->mIsList );
			$normal_filter = false;
		} elseif ( count( $filter_values ) >= $wgCargoDrilldownMinValuesForComboBox ) {
			$results_line = $this->printComboBoxInput( $filter_name, 0, $filter_values );
			$normal_filter = false;
		} else {
			$results_line = $this->printUnappliedFilterValues( $cur_url, $f, $filter_values );
		}

		$text = $this->printFilterLine( $f->name, false, $normal_filter, $results_line );
		return $text;
	}

	function getPageHeader() {
		global $wgRequest, $wgCargoPageDataColumns, $wgCargoFileDataColumns;
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

		$displaySearchInput = ( $this->tableName == '_fileData' &&
			in_array( 'fullText', $wgCargoFileDataColumns ) ) ||
			( $this->tableName != '_fileData' &&
			( $this->searchablePages || $this->searchableFiles ) );

		// If there are no fields for this table,
		// escape now that we've (possibly) printed the
		// tables list.
		if ( count( $this->all_filters ) == 0 && !$displaySearchInput ) {
			return $header;
		}

		$appliedFiltersHTML = '				<div id="drilldown-header">' . "\n";
		if ( count( $this->applied_filters ) > 0 || $this->fullTextSearchTerm != null ) {
			$tableURL = $this->makeBrowseURL( $this->tableName );
			$appliedFiltersHTML .= '<a href="' . $tableURL . '" title="' .
				$this->msg( 'cargo-drilldown-resetfilters' )->text() . '">' .
				$this->displayTableName() . '</a>';
		} else {
			$appliedFiltersHTML .= $this->displayTableName();
		}

		if ( $this->fullTextSearchTerm != null ) {
			$appliedFiltersHTML .= " > ";
			$appliedFiltersHTML .= $this->msg( 'cargo-drilldown-fulltext' )->text() . ': ';

			$remove_filter_url = $this->makeBrowseURL( $this->tableName, null, $this->applied_filters );
			$appliedFiltersHTML .= "\n\t" . '<span class="drilldown-header-value">~ \'' .
				$this->fullTextSearchTerm .
				'\'</span> <a href="' . $remove_filter_url . '" title="' .
				$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
				$cgScriptPath . '/drilldown/resources/filter-x.png" /></a> ';
		}

		foreach ( $this->applied_filters as $i => $af ) {
			$appliedFiltersHTML .= ( $i == 0 && $this->fullTextSearchTerm == null ) ? " > " :
				"\n\t\t\t\t\t<span class=\"drilldown-header-value\">&</span> ";
			$filter_label = str_replace( '_', ' ', $af->filter->name );
			// Add an "x" to remove this filter, if it has more
			// than one value.
			if ( count( $this->applied_filters[$i]->values ) > 1 ) {
				$temp_filters_array = $this->applied_filters;
				array_splice( $temp_filters_array, $i, 1 );
				$remove_filter_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $temp_filters_array );
				array_splice( $temp_filters_array, $i, 0 );
				$appliedFiltersHTML .= $filter_label . ' <a href="' . $remove_filter_url . '" title="' .
					$this->msg( 'cargo-drilldown-removefilter' )->text() .
					'"><img src="' . $cgScriptPath . '/drilldown/resources/filter-x.png" /></a> : ';
			} else {
				$appliedFiltersHTML .= "$filter_label: ";
			}
			foreach ( $af->values as $j => $fv ) {
				if ( $j > 0 ) {
					$appliedFiltersHTML .= ' <span class="drilldown-or">' .
						$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
				}
				$filter_text = $this->printFilterValue( $af->filter, $fv->text );
				$temp_filters_array = $this->applied_filters;
				$removed_values = array_splice( $temp_filters_array[$i]->values, $j, 1 );
				$remove_filter_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $temp_filters_array );
				array_splice( $temp_filters_array[$i]->values, $j, 0, $removed_values );
				$appliedFiltersHTML .= "\n	" . '				<span class="drilldown-header-value">' .
					$filter_text . '</span> <a href="' . $remove_filter_url . '" title="' .
					$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
					$cgScriptPath . '/drilldown/resources/filter-x.png" /></a>';
			}

			if ( $af->search_terms != null ) {
				foreach ( $af->search_terms as $j => $search_term ) {
					if ( $j > 0 ) {
						$appliedFiltersHTML .= ' <span class="drilldown-or">' .
							$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
					}
					$temp_filters_array = $this->applied_filters;
					$removed_values = array_splice( $temp_filters_array[$i]->search_terms, $j, 1 );
					$remove_filter_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $temp_filters_array );
					array_splice( $temp_filters_array[$i]->search_terms, $j, 0, $removed_values );
					$appliedFiltersHTML .= "\n\t" . '<span class="drilldown-header-value">~ \'' . $search_term .
						'\'</span> <a href="' . $remove_filter_url . '" title="' .
						$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
						$cgScriptPath . '/drilldown/resources/filter-x.png" /> </a>';
				}
			} elseif ( $af->lower_date != null || $af->upper_date != null ) {
				$appliedFiltersHTML .= "\n\t" . Html::element( 'span', array( 'class' => 'drilldown-header-value' ),
						$af->lower_date_string . " - " . $af->upper_date_string );
			}
		}

		$appliedFiltersHTML .= "</div>\n";
		$header .= $appliedFiltersHTML;
		$drilldown_description = $this->msg( 'cargo-drilldown-docu' )->text();
		$header .= "				<p>$drilldown_description</p>\n";

		// Display every filter, each on its own line; each line will
		// contain the possible values, and, in parentheses, the
		// number of pages that match that value.
		$filtersHTML = "				<div class=\"drilldown-filters\">\n";
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm, $this->applied_filters );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';

		if ( $displaySearchInput ) {
			$fullTextSearchInput = $this->printTextInput( '_search', 0, true, $this->fullTextSearchTerm );
			$filtersHTML .= self::printFilterLine( $this->msg( 'cargo-drilldown-fulltext' )->text(), false, false, $fullTextSearchInput );
		}

		foreach ( $this->all_filters as $f ) {
			foreach ( $this->applied_filters as $af ) {
				if ( $af->filter->name == $f->name ) {
					$fieldType = $f->fieldDescription->mType;
					if ( in_array( $fieldType, array( 'Date', 'Datetime', 'Integer', 'Float' ) ) ) {
						$filtersHTML .= $this->printUnappliedFilterLine( $f );
					} else {
						$filtersHTML .= $this->printAppliedFilterLine( $af );
					}
				}
			}
			foreach ( $this->remaining_filters as $rf ) {
				if ( $rf->name == $f->name ) {
					$filtersHTML .= $this->printUnappliedFilterLine( $rf, $cur_url );
				}
			}
		}
		$filtersHTML .= "				</div> <!-- drilldown-filters -->\n";

		if ( count( $this->all_filters ) == 0 ) {
			return '';
		}

		$header .= $filtersHTML;

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
		if ( $this->fullTextSearchTerm != '' ) {
			$params['_search'] = $this->fullTextSearchTerm;
		}
		foreach ( $this->applied_filters as $i => $af ) {
			if ( count( $af->values ) == 1 ) {
				$key_string = str_replace( ' ', '_', $af->filter->name );
				$value_string = str_replace( ' ', '_', $af->values[0]->text );
				$params[$key_string] = $value_string;
			} else {
				// @HACK - QueryPage's pagination-URL code,
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

		if ( $this->fullTextSearchTerm != null ) {
			list( $curTableNames, $curConds, $curJoinConds ) =
				self::getFullTextSearchQueryParts( $this->fullTextSearchTerm, $this->tableName, $this->searchablePages, $this->searchableFiles );
			$tableNames = array_merge( $tableNames, $curTableNames );
			$conds = array_merge( $conds, $curConds );
			$joinConds = array_merge( $joinConds, $curJoinConds );
		}

		foreach ( $this->applied_filters as $i => $af ) {
			list( $curTableNames, $curConds, $curJoinConds ) = $af->getQueryParts( $this->tableName );
			$tableNames = array_merge( $tableNames, $curTableNames );
			$conds = array_merge( $conds, $curConds );
			$joinConds = array_merge( $joinConds, $curJoinConds );
		}

		$aliasedFieldNames = array(
			'title' => CargoUtils::escapedFieldName( $cdb, $this->tableName, '_pageName' ),
			'value' => CargoUtils::escapedFieldName( $cdb, $this->tableName, '_pageName' ),
			'namespace' => CargoUtils::escapedFieldName( $cdb, $this->tableName, '_pageNamespace' ),
			'ID' => CargoUtils::escapedFieldName( $cdb, $this->tableName, '_pageID' )
		);

		if ( $this->fullTextSearchTerm != null ) {
			if ( $this->tableName == '_fileData' || !$this->searchablePages ) {
				$aliasedFieldNames['fileText'] = CargoUtils::escapedFieldName( $cdb, '_fileData', '_fullText' );
				$aliasedFieldNames['foundFileMatch'] = '1';
			} else {
				$aliasedFieldNames['pageText'] = CargoUtils::escapedFieldName( $cdb, '_pageData', '_fullText' );
			}
			if ( $this->searchableFiles ) {
				$aliasedFieldNames['fileName'] = CargoUtils::escapedFieldName( $cdb, '_fileData', '_pageName' );
				$aliasedFieldNames['fileText'] = CargoUtils::escapedFieldName( $cdb, '_fileData', '_fullText' );
				// @HACK - the result set may contain both
				// pages and files that match the search term.
				// So how do we know, for each result row,
				// whether it's for a page or a file? We add
				// the "match on file" clause as a (boolean)
				// query field. There may be a more efficient
				// way to do this, using SQL variables or
				// something.
				$aliasedFieldNames['foundFileMatch'] = CargoUtils::fullTextMatchSQL( $cdb, '_fileData', '_fullText', $this->fullTextSearchTerm );
			}
		}

		$queryInfo = array(
			'tables' => $tableNames,
			'fields' => $aliasedFieldNames,
			'conds' => $conds,
			'join_conds' => $joinConds,
			'options' => array()
		);

		return $queryInfo;
	}

	static function getFullTextSearchQueryParts( $searchTerm, $mainTableName, $searchablePages, $searchableFiles ) {
		$cdb = CargoUtils::getDB();

		$tableNames = array();
		$conds = array();
		$joinConds = array();

		// Special quick handling for the "data" tables.
		if ( $mainTableName == '_fileData' ) {
			$conds[] = CargoUtils::fullTextMatchSQL( $cdb, '_fileData', '_fullText', $searchTerm );
			return array( $tableNames, $conds, $joinConds );
		} elseif ( $mainTableName == '_pageData' ) {
			$conds[] = CargoUtils::fullTextMatchSQL( $cdb, '_pageData', '_fullText', $searchTerm );
			return array( $tableNames, $conds, $joinConds );
		}

		if ( $searchablePages ) {
			$tableNames[] = '_pageData';
			$joinConds['_pageData'] = array(
				'LEFT OUTER JOIN',
				CargoUtils::escapedFieldName( $cdb, $mainTableName, '_pageID' ) .
				' = ' .
				CargoUtils::escapedFieldName( $cdb, '_pageData', '_pageID' )
			);
		}

		if ( $searchableFiles ) {
			$fileTableName = $mainTableName . '___files';
			$tableNames[] = $fileTableName;
			$joinConds[$fileTableName] = array(
				'LEFT OUTER JOIN',
				CargoUtils::escapedFieldName( $cdb, $mainTableName, '_pageID' ) .
				' = ' .
				CargoUtils::escapedFieldName( $cdb, $fileTableName, '_pageID' )	
			);
			$tableNames[] = '_fileData';
			$joinConds['_fileData'] = array(
				'JOIN',
				CargoUtils::escapedFieldName( $cdb, $fileTableName, '_fileName' ) .
				' = ' .
				CargoUtils::escapedFieldName( $cdb, '_fileData', '_pageTitle' )
			);
			if ( $searchablePages ) {
				$conds[] = CargoUtils::fullTextMatchSQL( $cdb, '_fileData', '_fullText', $searchTerm ) . ' OR ' .
					CargoUtils::fullTextMatchSQL( $cdb, '_pageData', '_fullText', $searchTerm );
			} else {
				$conds[] = CargoUtils::fullTextMatchSQL( $cdb, '_fileData', '_fullText', $searchTerm );
			}
		} else {
			$conds[] = CargoUtils::fullTextMatchSQL( $cdb, '_pageData', '_fullText', $searchTerm );
		}

		return array( $tableNames, $conds, $joinConds );
	}

	function getOrderFields() {
		$cdb = CargoUtils::getDB();
		return array( CargoUtils::escapedFieldName( $cdb, $this->tableName, '_pageTitle' ) );
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		$title = Title::makeTitle( $result->namespace, $result->value );
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}
		return CargoUtils::makeLink( $linkRenderer, $title, htmlspecialchars( $title->getText() ) );
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
		$pageTextStr = $this->msg( 'cargo-drilldown-pagetext' )->text();
		$fileNameStr = $this->msg( 'cargo-drilldown-filename' )->text();
		$fileTextStr = $this->msg( 'cargo-drilldown-filetext' )->text();

		// @HACK - the current SQL query may return the same page as a
		// result more than once. So keep an array of pages that have
		// been returned so that we show each page only once.
		$matchingPages = array();
		while ( $row = $cdb->fetchRow( $res ) ) {
			$pageName = $row['title'];
			$curValue = array( 'title' => $pageName );
			$pageNamespace = $this->getLanguage()->getFormattedNsText( $row['namespace'] );
			if ( $pageNamespace != '' ) {
				$curValue['namespace'] = $pageNamespace;
			}
			if ( array_key_exists( 'foundFileMatch', $row ) && $row['foundFileMatch'] ) {
				if ( array_key_exists( 'fileName', $row ) ) {
					// Not used for _fileData drilldown.
					$curValue[$fileNameStr] = $row['fileName'];
				}
				$curValue[$fileTextStr] = $row['fileText'];
				$valuesTable[] = $curValue;
			} elseif ( array_key_exists( 'pageText', $row ) ) {
				if ( !in_array( $pageName, $matchingPages ) ) {
					$curValue[$pageTextStr] = $row['pageText'];
					$valuesTable[] = $curValue;
					$matchingPages[] = $pageName;
				}
			} else {
				$valuesTable[] = $curValue;
			}
		}

		$queryDisplayer = new CargoQueryDisplayer();
		$fieldDescription = new CargoFieldDescription();
		$fieldDescription->mType = 'Page';
		$queryDisplayer->mFieldDescriptions = array( 'title' => $fieldDescription );

		if ( $this->fullTextSearchTerm != null ) {
			$searchTerms = CargoUtils::smartSplit( ' ', $this->fullTextSearchTerm );
			$dummySQLQuery = new CargoSQLQuery();
			$dummySQLQuery->mSearchTerms = array(
				$pageTextStr => $searchTerms,
				$fileTextStr => $searchTerms
			);
			$queryDisplayer->mSQLQuery = $dummySQLQuery;
			$fullTextFieldDescription = new CargoFieldDescription();
			$fullTextFieldDescription->mType = 'Searchtext';
			$fileFieldDescription = new CargoFieldDescription();
			$fileFieldDescription->mType = 'Page';
			$stringFieldDescription = new CargoFieldDescription();
			$stringFieldDescription->mType = 'String';
			$queryDisplayer->mFieldDescriptions[$pageTextStr] = $fullTextFieldDescription;
			$queryDisplayer->mFieldDescriptions[$fileNameStr] = $fileFieldDescription;
			$queryDisplayer->mFieldDescriptions[$fileTextStr] = $fullTextFieldDescription;
		}

		$queryDisplayer->mFormat = 'category';
		// Make display wider if we're also showing file information.
		if ( $this->searchableFiles && $this->fullTextSearchTerm != '' ) {
			$queryDisplayer->mDisplayParams['columns'] = 2;
		}
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
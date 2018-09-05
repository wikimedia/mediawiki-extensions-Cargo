<?php
/**
 * Displays an interface to let the user drill down through all Cargo data.
 *
 * Based in part on SD_BrowseData.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @author Nikhil Kumar
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

		$queryparts = explode( '/', $query, 1 );
		$mainTable = isset( $queryparts[0] ) ? $queryparts[0] : '';

		// If no table was specified, go with the first table,
		// alphabetically.
		if ( !$mainTable ) {
			$tableNames = CargoUtils::getTables();
			if ( count( $tableNames ) == 0 ) {
				// There are no tables - just exit now.
				return 0;
			}
			$mainTable = $tableNames[0];
		}
		$parentTables = array();
		$parentTables = CargoUtils::getParentTables( $mainTable );
		$drilldownTabsParams = CargoUtils::getDrilldownTabsParams( $mainTable );
		if ( $parentTables ) {
			$parentTablesNames =
				array_map( function ( $table ) {
					return $table['Name'];
				}, $parentTables );
		}

		if ( $request->getCheck( '_replacement' ) ) {
			$mainTable .= '__NEXT';
		}
		$mainTableAlias = CargoUtils::makeDifferentAlias( $mainTable );
		try {
			if ( $parentTables ) {
				$tableSchemas = CargoUtils::getTableSchemas( array_merge( array( $mainTable ),
					$parentTablesNames ) );
			} else {
				$tableSchemas = CargoUtils::getTableSchemas( array( $mainTable ) );
			}
		} catch ( MWException $e ) {
			$out->addHTML( Html::rawElement( 'div', array( 'class' => 'error' ),
				$this->msg( 'cargo-cargotables-tablenotfound', $mainTable )->parse() ) . "\n" );
			return;
		}
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
		$coordsFields = array();
		$dateFields = array();
		$calendarFields = array();
		$fileFields = array();
		$dependentFieldsArray = array();
		foreach ( $tableSchemas[$mainTable]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !$fieldDescription->mIsHidden && $fieldDescription->mType == 'File' && in_array( 'fullText', $wgCargoFileDataColumns ) ) {
				$searchableFiles = true;
			}
		}
		if ( $parentTables ) {
			$tableNames =
				array_merge( array( $mainTableAlias => array( 'Name' => $mainTable ) ),
					$parentTables );
		} else {
			$tableNames = array( $mainTableAlias => array( 'Name' => $mainTable ) );
		}
		foreach ( $tableNames as $tableAlias => $table ) {
			$tableName = $table['Name'];
			foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
				$dependentFields = array();
				foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName1 =>
						  $fieldDescription1 ) {
					$fieldDescriptionArray1 = $fieldDescription1->toDBArray();
					if ( array_key_exists( 'dependent on', $fieldDescriptionArray1 ) ) {
						if ( in_array( $fieldName, $fieldDescriptionArray1['dependent on'] ) ) {
							$dependentFields[] = $tableName . '.' . $fieldName1;
						}
					}
				}
				$dependentFieldsArray[ $tableName . '.' . $fieldName] = $dependentFields;
			}

			foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
				// Skip "hidden" fields.
				if ( $fieldDescription->mIsHidden ) {
					continue;
				}

				// Some field types shouldn't get a filter at all.
				if ( in_array( $fieldDescription->mType, array( 'Text', 'File', 'Coordinates', 'URL', 'Email', 'Wikitext', 'Searchtext' ) ) ) {
					if ( ( $tableName == $mainTable || $drilldownTabsParams ) && $fieldDescription->mType == 'Coordinates' ) {
						$coordsFields[$tableAlias] = $fieldName;
					}
					if ( ( $tableName == $mainTable || $drilldownTabsParams ) && $fieldDescription->mType == 'File' ) {
						$fileFields = array_merge( $fileFields, array( $fieldName => $fieldDescription ) );
					}
					continue;
				}

				if ( ( $tableName == $mainTable || $drilldownTabsParams ) && ( $fieldDescription->mType == 'Date' ||
						$fieldDescription->mType == 'Datetime' ) ) {
					$dateFields[] = $fieldName;
					$cdb = CargoUtils::getDB();
					// if no of events is more than 4 per month (i.e average days per event < 8),
					// then calendar format is displayed for that field's result.
					if ( $cdb->tableExists( $tableName ) ) {
						$res =
							$cdb->select( $tableName,
								"DATEDIFF(MAX($fieldName), MIN($fieldName))/ count(*) as avgDaysPerEvent" );
						$row = $cdb->fetchRow( $res );
						if ( $row['avgDaysPerEvent'] < 8 ) {
							$calendarFields[$fieldName] = '';
						}
					}
				}
				$all_filters[] =
					new CargoFilter( $fieldName, $tableAlias, $tableName, $fieldDescription,
						$searchablePages, $searchableFiles );
			}
		}
		if ( $searchableFiles ) {
			$numResultsPerPage = 100;
		} else {
			$numResultsPerPage = 250;
		}
		list( $limit, $offset ) = $request->getLimitOffset( $numResultsPerPage, 'limit' );

		$filter_used = array();
		foreach ( $all_filters as $i => $filter ) {
			$filter_used[] = false;
		}
		$applied_filters = array();
		$remaining_filters = array();
		foreach ( $all_filters as $i => $filter ) {
			if ( $parentTables && $filter->tableAlias != $mainTableAlias ) {
				$filter_name = str_replace( array( '_alias', ' ', "'" ), array( '', '_', "\'" ),
					ucfirst( $filter->tableAlias ) . '.' . $filter->name );
			} else {
				$filter_name = str_replace( array( ' ', "'" ), array( '_', "\'" ), $filter->name );
			}
			$search_terms = $request->getArray( '_search_' . $filter_name );
			$lower_date = $request->getArray( '_lower_' . $filter_name );
			$upper_date = $request->getArray( '_upper_' . $filter_name );
			if ( $vals_array = $request->getArray( $filter_name ) ) {
				foreach ( $vals_array as $j => $val ) {
					$vals_array[$j] = str_replace( '_', ' ', $val );
				}
				// If it has both search_terms and normal filter values
				if ( $search_terms != null ) {
					$applied_filters[] =
						CargoAppliedFilter::create( $filter, $vals_array, $search_terms );
					$filter_used[$i] = true;
				} else {
					$applied_filters[] = CargoAppliedFilter::create( $filter, $vals_array );
					$filter_used[$i] = true;
				}
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
				if ( !$filter_used[$i] ) {
					$remaining_filters[] = $filter;
				}
			}
		}
		$formatBy = $request->getVal( 'formatBy' );
		$format = $request->getVal( 'format' );
		if ( !$format || !$formatBy ) {
			$format = '';
			$formatBy = '';
		}
		$curTabName = $request->getVal( 'tab' );
		if ( $drilldownTabsParams ) {
			if ( !$curTabName ) {
				$curTabName = key( $drilldownTabsParams );
			}
		}

		$out->addHTML( "\n\t\t\t\t<div class=\"drilldown-results\">\n" );
		$rep =
			new CargoDrilldownPage( $mainTable, $parentTables, $drilldownTabsParams, $all_filters,
				$applied_filters, $remaining_filters, $fullTextSearchTerm, $coordsFields,
				$dateFields, $calendarFields, $fileFields, $searchablePages, $searchableFiles,
				$dependentFieldsArray, $offset, $limit, $format, $formatBy, $curTabName );
		$num = $rep->execute( $query );
		$out->addHTML( "\n\t\t\t</div> <!-- drilldown-results -->\n" );

		// This has to be set last, because otherwise the QueryPage
		// code will overwrite it.
		if ( !$mainTable ) {
			$tableTitle = $this->msg( 'drilldown' )->text();
		} else {
			$tableTitle = $this->msg( 'drilldown' )->text() . html_entity_decode(
				$this->msg( 'colon-separator' )->text() ) . $rep->displayTableName( $mainTable );
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
	public $tableAlias = "";
	public $parentTables = array();
	public $drilldownTabsParams = array();
	public $all_filters = array();
	public $applied_filters = array();
	public $remaining_filters = array();
	public $fullTextSearchTerm;
	public $coordsFields;
	public $dateFields;
	public $calendarFields;
	public $fileFields;
	public $sqlQuery;
	public $searchablePages;
	public $searchableFiles;
	public $dependentFieldsArray = array();
	public $format;
	public $formatBy;
	public $curTabName;
	private $showSingleTable = false;
	private $isReplacementTable = false;

	/**
	 * Initialize the variables of this page
	 *
	 * @param string $tableName
	 * @param array $applied_filters
	 * @param array $remaining_filters
	 * @param string $fullTextSearchTerm
	 * @param bool $searchablePages
	 * @param bool $searchableFiles
	 * @param int $offset
	 * @param int $limit
	 */
	function __construct( $tableName, $parentTables, $drilldownTabsParams, $all_filters, $applied_filters,
			$remaining_filters, $fullTextSearchTerm, $coordsFields, $dateFields, $calendarFields,
			$fileFields, $searchablePages, $searchableFiles, $dependentFieldsArray, $offset, $limit,
			$format, $formatBy, $curTabName ) {
		parent::__construct( 'Drilldown' );

		$this->tableName = $tableName;
		$this->tableAlias = CargoUtils::makeDifferentAlias( $tableName );
		$this->parentTables = (array)$parentTables;
		$this->drilldownTabsParams = (array)$drilldownTabsParams;
		$this->all_filters = $all_filters;
		$this->applied_filters = $applied_filters;
		$this->remaining_filters = $remaining_filters;
		$this->fullTextSearchTerm = $fullTextSearchTerm;
		$this->coordsFields = $coordsFields;
		$this->dateFields = $dateFields;
		$this->calendarFields = $calendarFields;
		$this->fileFields = $fileFields;
		$this->searchablePages = $searchablePages;
		$this->searchableFiles = $searchableFiles;
		$this->dependentFieldsArray = $dependentFieldsArray;
		$this->offset = $offset;
		$this->limit = $limit;
		$this->format = $format;
		$this->formatBy = $formatBy;
		$this->curTabName = $curTabName;
	}

	/**
	 *
	 * @param string $tableName
	 * @param array $applied_filters
	 * @param string $filter_to_remove
	 * @return string
	 */
	function makeBrowseURL( $tableName, $searchTerm = null, $applied_filters = array(),
			$filters_to_remove = array(), $attributes = array() ) {
		$dd = SpecialPage::getTitleFor( 'Drilldown' );
		$url = $dd->getLocalURL() . '/' . $tableName;
		if ( $this->showSingleTable ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= "_single";
		}
		if ( $this->isReplacementTable ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= "_replacement";
		}

		if ( $searchTerm != null ) {
			$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
			$url .= '_search=' . urlencode( $searchTerm );
		}

		foreach ( $applied_filters as $af ) {
			if ( in_array( $af->filter->name, $filters_to_remove ) ) {
				continue;
			}
			if ( count( $af->values ) == 0 ) {
				// do nothing
			} elseif ( count( $af->values ) == 1 ) {
				$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
				if ( $this->parentTables && $af->filter->tableName != $this->tableName ) {
					$url .= urlencode( str_replace( array( '_alias', ' ' ), array( '', '_' ),
						ucfirst( $af->filter->tableAlias ) . '.' . $af->filter->name ) ) . "=" .
						urlencode( str_replace( ' ', '_', $af->values[0]->text ) );
				} else {
					$url .= urlencode( str_replace( ' ', '_', $af->filter->name ) ) . "=" .
							urlencode( str_replace( ' ', '_', $af->values[0]->text ) );
				}
			} else {
				usort( $af->values, array( "CargoFilterValue", "compare" ) );
				foreach ( $af->values as $j => $fv ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
					if ( $this->parentTables && $af->filter->tableName != $this->tableName ) {
						$url .= urlencode( str_replace( array( '_alias', ' ' ), array( '', '_' ),
								ucfirst( $af->filter->tableAlias ) . '.' . $af->filter->name ) ) .
								"[$j]=" . urlencode( str_replace( ' ', '_', $fv->text ) );
					} else {
						$url .= urlencode( str_replace( ' ', '_', $af->filter->name ) ) . "[$j]=" .
								urlencode( str_replace( ' ', '_', $fv->text ) );
					}
				}
			}
			if ( $af->search_terms != null ) {
				foreach ( $af->search_terms as $j => $search_term ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
					if ( $this->parentTables != null && $af->filter->tableName != $this->tableName ) {
						$url .= '_search_' . urlencode( str_replace( array( '_alias', ' ' ), array( '', '_' ),
									ucfirst( $af->filter->tableAlias ) . '.' . $af->filter->name ) .
														'[' . $j . ']' ) . "=" .
								urlencode( str_replace( ' ', '_', $search_term ) );
					} else {
						$url .= '_search_' .
								urlencode( str_replace( ' ', '_', $af->filter->name ) . '[' . $j .
										   ']' ) . "=" .
								urlencode( str_replace( ' ', '_', $search_term ) );
					}
				}
			}
		}
		if ( $attributes ) {
			foreach ( $attributes as $attribute => $value ) {
				if ( $attribute == 'tab' ) {
					if ( $value == key( $this->drilldownTabsParams ) ) {
						continue;
					}
				}
				if ( $attribute == 'limit' ) {
					if ( $value == 250 ) {
						continue;
					}
				}
				if ( $attribute == 'offset' ) {
					if ( $value == 0 ) {
						continue;
					}
				}
				if ( $value !== '' ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
					$url .= $attribute . '=' . $value;
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
					<p id="tableTabsHeader">$chooseTableText</p>
					<ul id="$cats_list_class" class="drilldown-tabs">

END;
		} else {
			$text .= <<<END
					<ul id="$cats_list_class">
					<li id="tableTabsHeader">$chooseTableText</li>

END;
		}
		$cdb = CargoUtils::getDB();
		foreach ( $tables as $table ) {
			if ( $cdb->tableExists( $table ) == false ) {
				$text .= '<li class="tableName error">' . $table . "</li>";
				continue;
			}
			$res = $cdb->select( $table, 'COUNT(*) AS total' );
			$row = $cdb->fetchRow( $res );
			$tableRows = $row['total'];
			// FIXME: hardcoded ()
			$tableStr = $this->displayTableName( $table ) . " ($tableRows)";
			if ( $this->tableName == $table ) {
				$text .= '						<li class="tableName selected">';
			} else {
				$text .= '						<li class="tableName">';
			}
			$tableURL = $this->makeBrowseURL( $table );
			$text .= Html::rawElement( 'a', array( 'href' => $tableURL, 'title' => $chooseTableText ),
				$tableStr );
			$text .= "</li>\n";
		}
		$closeList = $this->closeList();
		$text .= <<<END
					</li>
				</ul>
				$closeList
			</div>

END;
		return $text;
	}

	function displayTableName( $tableName = null ) {
		if ( $tableName == null ) {
			$tableName = $this->tableName;
		}

		if ( $tableName == '_pageData' ) {
			return $this->msg( 'cargo-drilldown-allpages' )->escaped();
		} elseif ( $tableName == '_fileData' ) {
			return $this->msg( 'cargo-drilldown-allfiles' )->escaped();
		} else {
			return htmlspecialchars( str_replace( '_', ' ', $tableName ) );
		}
	}

	/**
	 * Create the full display of the filter line, once the text for
	 * the "results" (values) for this filter has been created.
	 */
	function printFilterLine( $filterName, $isApplied, $isNormalFilter, $resultsLine, $tableAlias = null ) {
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
	 *
	 * If $printAllFilterValues is false, then it prints the most popular filter values for a filter
	 * Used to print the most popular filter values before ComboBoxInput or TextInput
	 */
	function printAppliedFilterLine( $af, $printAllFilterValues = true ) {
		global $wgCargoDrilldownMinValuesForComboBox;
		if ( $af->filter->fieldDescription->mIsHierarchy ) {
			return $this->printAppliedFilterLineForHierarchy( $af );
		}
		$results_line = "";
		$current_filter_values = array();
		foreach ( $this->applied_filters as $af2 ) {
			if ( $af->filter->tableAlias == $af2->filter->tableAlias &&
					$af->filter->name == $af2->filter->name ) {
				$current_filter_values = $af2->values;
			}
		}
		if ( $af->filter->allowed_values != null ) {
			$or_values = $af->filter->allowed_values;
		} else {
			list( $tableNames, $joinConds, $mainTableName, $mainTableAlias ) = $this->getInitialQueryParts();
			$or_values = $af->filter->getAllValues( $this->fullTextSearchTerm,
				$this->applied_filters, true, $mainTableAlias, $tableNames, $joinConds );
			arsort( $or_values );
		}
		if ( $printAllFilterValues ) {
			if ( count( $or_values ) >= $wgCargoDrilldownMinValuesForComboBox ) {
				// Print filter values before ComboBoxInput or TextInput
				$results_line .= $this->printAppliedFilterLine( $af, false );
				// $printed_filter_values contains the filter values for a filter which have been
				// printed before ComboBoxInput or TextBoxInput
				$printed_filter_values = array_slice( $or_values, 0, 20, true );
				// $filter_values contains the remaining filter values(which have not been printed)
				$filter_values = array_splice( $or_values, 20 );
				ksort( $filter_values );
				if ( $af->search_terms != null ) {
					$instance_num = count( $af->search_terms );
				} else {
					$instance_num = count( $af->values );
				}
				if ( count( $or_values ) >= 250 ) {
					$results_line .= $this->printTextInput( $af->filter->name, $instance_num,
						false, null, true, $af->filter->fieldDescription->mIsList,
						$printed_filter_values, $af->filter );
				} else {
					$results_line .= $this->printComboBoxInput( $af->filter->name,
						$instance_num, $filter_values );
				}
				return $this->printFilterLine( $af->filter->name, true, true, $results_line,
					$af->filter->tableAlias );
			}
			// Add 'Other' and 'None', regardless of whether either has
			// any results - add 'Other' only if it's not a date field.
			$fieldType = $af->filter->fieldDescription->mType;
			if ( $fieldType != 'Date' && $fieldType != 'Datetime' ) {
				$or_values['_other'] = '';
			}
			$or_values['_none'] = '';
		}
		$num_printed_values = 0;
		foreach ( $or_values as $value => $num_instances ) {
			// print only 20 filter values in case of ComboBoxInput or TextInput
			if ( !$printAllFilterValues ) {
				if ( $num_printed_values >= 20 ) {
					break;
				}
			}
			if ( $num_printed_values++ > 0 ) {
				$results_line .= " &middot; ";
			}
			$filter_text = $this->printFilterValue( $af->filter, $value );
			$applied_filters = $this->applied_filters;
			foreach ( $applied_filters as $af2 ) {
				if ( $af->filter->tableAlias == $af2->filter->tableAlias &&
						$af->filter->name == $af2->filter->name ) {
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
			// Also check the applied search_terms for match
			if ( $af->search_terms != null ) {
				$current_search_terms = $af->search_terms;
				foreach ( $current_search_terms as $fv ) {
					if ( $value == $fv ) {
						$found_match = true;
						break;
					}
				}
			}
			if ( $found_match ) {
				$results_line .= "\n\t\t\t\t$filter_text";
			} else {
				$filter_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
					$applied_filters, array(),
					( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
					array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
				$results_line .= "\n\t\t\t\t\t\t" . Html::rawElement( 'a',
					array( 'href' => $filter_url,
					'title' => $this->msg( 'cargo-drilldown-filterbyvalue' )->text() ), $filter_text );
			}
			foreach ( $applied_filters as $af2 ) {
				if ( $af->filter->tableAlias == $af2->filter->tableAlias &&
						$af->filter->name == $af2->filter->name ) {
					$af2->values = $current_filter_values;
				}
			}
		}
		if ( $printAllFilterValues ) {
			return $this->printFilterLine( $af->filter->name, true, true, $results_line,
				$af->filter->tableAlias );
		} else {
			return $results_line;
		}
	}

	function printAppliedFilterLineForHierarchy( $af ) {
		$applied_filters = $this->applied_filters;
		$applied_filters_no_hierarchy = array();
		foreach ( $applied_filters as $key => $af2 ) {
			if ( !$af2->filter->fieldDescription->mIsHierarchy ) {
				$applied_filters_no_hierarchy[] = $af2;
			}
		}
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
			$applied_filters_no_hierarchy, array(),
			( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
			array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
		// Drilldown for hierarchy is designed for literal 'drilldown'
		// Therefore it has single filter value applied at anytime
		$filter_value = "";
		$isFilterValueNotWithin = false;
		if ( count( $af->values ) > 0 ) {
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
			for ( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
				$stack->push( $node->mChildren[$i] );
			}
		}
		list( $tableNames, $joinConds, $mainTableName, $mainTableAlias ) = $this->getInitialQueryParts();
		if ( $isFilterValueNotWithin === true ) {
			CargoDrilldownHierarchy::computeNodeCountForTreeByFilter( $node,
				$af->filter, null, $applied_filters, $mainTableAlias, $tableNames, $joinConds );
			$results_line = wfMessage( 'cargo-drilldown-hierarchy-only', $node->mRootValue )->parse() . " ($node->mExactRootMatchCount)";
		} else {
			$results_line = $this->printFilterValuesForHierarchy( $cur_url, $af->filter, null, $applied_filters, $drilldownHierarchyRoot );
		}
		return $this->printFilterLine( $af->filter->name, false, true, $results_line,
			$af->filter->tableName );
	}

	function printUnappliedFilterValues( $cur_url, $f, $filter_values ) {
		$results_line = "";
		// now print the values
		$num_printed_values = 0;
		foreach ( $filter_values as $value_str => $num_results ) {
			if ( $num_printed_values++ > 0 ) {
				$results_line .= " &middot; ";
			}
			if ( $this->parentTables && $f->tableName != $this->tableName ) {
				$filter_url =
					$cur_url . urlencode( str_replace( array( '_alias', ' ' ), array( '', '_' ),
						ucfirst( $f->tableAlias ) . '.' . $f->name ) ) . '=' .
					urlencode( str_replace( ' ', '_', $value_str ) );
			} else {
				$filter_url =
					$cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
					urlencode( str_replace( ' ', '_', $value_str ) );
			}
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
		$results_line = "";
		// compute counts
		list( $tableNames, $joinConds, $mainTableName, $mainTableAlias ) = $this->getInitialQueryParts();
		$filter_values = CargoDrilldownHierarchy::computeNodeCountForTreeByFilter( $drilldownHierarchyRoot,
			$f, $fullTextSearchTerm, $applied_filters, $mainTableAlias, $tableNames, $joinConds );
		$maxDepth = CargoDrilldownHierarchy::findMaxDrilldownDepth( $drilldownHierarchyRoot );
		$depth = 0;
		$num_printed_values_level = 0;
		$stack = new SplStack();
		// preorder traversal of the tree
		$stack->push( $drilldownHierarchyRoot );
		while ( !$stack->isEmpty() ) {
			$node = $stack->pop();
			if ( $node != ")" ) {
				if ( $node->mLeft !== 1 && $node->mWithinTreeMatchCount > 0 ) {
					// check if its not __pseudo_root__ node, then only print
					if ( $num_printed_values_level++ > 0 ) {
						$results_line .= " &middot; ";
					}
					// generate a url to encode WITHIN search information by a "~within_" prefix in value_str
					if ( $this->parentTables && $f->tableName != $this->tableName ) {
						$filter_url =
							$cur_url .
							urlencode( str_replace( ' ', '_', ucfirst( $f->tableAlias ) . '.' . $f->name ) ) .
							'=' .
							urlencode( str_replace( ' ', '_', "~within_" . $node->mRootValue ) );
					} else {
						$filter_url =
							$cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) . '=' .
							urlencode( str_replace( ' ', '_', "~within_" . $node->mRootValue ) );
					}
					// generate respective <a> tag with value and its count
					$results_line .= ( $node === $drilldownHierarchyRoot ) ? $node->mRootValue . " ($node->mWithinTreeMatchCount)" :
						$this->printFilterValueLink( $f, $node->mRootValue, $node->mWithinTreeMatchCount, $filter_url, $filter_values );
				}
				if ( count( $node->mChildren ) > 0 && $node->mWithinTreeMatchCount > 0 && $depth < $maxDepth ) {
					$depth++;
					if ( $node->mLeft !== 1 ) {
						$results_line .= " (";
						$num_printed_values_level = 0;
						if ( $node->mExactRootMatchCount > 0 ) {
							if ( $this->parentTables && $f->tableName != $this->tableName ) {
								$filter_url =
									$cur_url . urlencode( str_replace( ' ', '_',
										ucfirst( $f->tableAlias ) . '.' . $f->name ) ) . '=' .
									urlencode( str_replace( ' ', '_', $node->mRootValue ) );
							} else {
								$filter_url =
									$cur_url . urlencode( str_replace( ' ', '_', $f->name ) ) .
									'=' . urlencode( str_replace( ' ', '_', $node->mRootValue ) );
							}
							$results_line .= $this->printFilterValueLink( $f,
								wfMessage( 'cargo-drilldown-hierarchy-only', $node->mRootValue )->parse(),
								$node->mExactRootMatchCount, $filter_url, $filter_values );
							$num_printed_values_level++;
						}
						$stack->push( ")" );
					}
					for ( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
						$stack->push( $node->mChildren[$i] );
					}
				}
			} else {
				$results_line .= " ) ";
				$depth--;
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
				$font_size = round( ( ( log( $num_results ) - log( $lowest_num_results ) ) * $scale_factor ) +
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
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
			$this->applied_filters, array( $filter_name ),
			( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
			array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
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

	function printTextInput( $filter_name, $instance_num, $is_full_text_search = false,
			$cur_value = null, $has_remote_autocompletion = false, $filter_is_list = false,
			$filter_values = null, $f = null ) {
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
			$text .= <<< END
<div class="oo-ui-actionFieldLayout-input oo-ui-iconElement oo-ui-textInputWidget mw-widget-titleInputWidget" style="display: inline; float: left;" data-ooui>
	<input type="text" name="$inputName" value="$cur_value" />
	<span class='oo-ui-iconElement-icon oo-ui-icon-search'></span>
	<span class='oo-ui-indicatorElement-indicator'></span>
</div>

END;
		} else {
			$text .= '<div class="oo-ui-actionFieldLayout-input" style="display: inline; float: left;">';
			$inputAttrs = array();
			if ( $has_remote_autocompletion ) {
				$inputAttrs['class'] = "cargoDrilldownRemoteAutocomplete";
				$inputAttrs['data-cargo-table'] = $this->tableName . '=' . $this->tableAlias;
				$inputAttrs['data-cargo-field'] = $filter_name;
				if ( $filter_is_list ) {
					$inputAttrs['data-cargo-field-is-list'] = true;
				}
				$inputAttrs['size'] = 30;
				$inputAttrs['style'] = 'padding: 0.57142857em 0.57142857em 0.5em;'; // Copied from OOUI
				$whereSQL = '';
				// In the WHERE statement, first add all the filters which have been applied and
				// then remove all the filter values for this filter which have been printed
				// before the text box
				foreach ( $this->applied_filters as $i => $af ) {
					if ( $i > 0 ) {
						$whereSQL .= ' AND ';
					}
					if ( $af->filter->name == $filter_name ) {
						$whereSQL .= ' NOT ';
					}
					$whereSQL .= $af->checkSQL();
				}
				if ( $filter_values ) {
					if ( $this->applied_filters ) {
						$whereSQL .= ' AND ';
					}
					$whereSQL .= ' NOT ';
					$cur_filter_value = array();
					foreach ( $filter_values as $value => $num_instances ) {
						$cur_filter_value = array_merge( $cur_filter_value, array( $value ) );
					}
					$af = CargoAppliedFilter::create( $f, $cur_filter_value );
					$whereSQL .= $af->checkSQL();
				}
				$inputAttrs['data-cargo-where'] = $whereSQL;
			}
			$text .= "\n\t\t\t\t\t<span>" .
					 $this->msg( 'cargo-drilldown-othervalues' )->text() . " </span>";
			$text .= Html::input( $inputName, $cur_value, 'text', $inputAttrs ) . "\n";
			$text .= "</div>\n\n";
		}

		$text .= '<span class="oo-ui-actionFieldLayout-button">';
		$text .= Html::input( null, $this->msg( 'searchresultshead' )->text(), 'submit',
				array( 'class' => 'mw-ui-button mw-ui-progressive' ) ) . "\n";
		$text .= "</span>\n";
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
		$msg = $this->msg( 'cargo-drilldown-othervalues' )->text();
		$text .= <<< END
	<div class="oo-ui-actionFieldLayout-input ui-widget" style="display: inline; float: left;">
		<span>$msg</span>
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

		$text .= <<<END
		</select>
	</div>

END;

		$text .= '<span class="oo-ui-actionFieldLayout-button">';
		$text .= Html::input( null, $this->msg( 'searchresultshead' )->text(), 'submit',
				array( 'class' => 'mw-ui-button mw-ui-progressive' ) ) . "\n";
		$text .= "</form>\n";
		$text .= "</span>\n";
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
	 * Split the filter values into the set displayed on the screen and those
	 * that will simply be autocompleted on, if not all of them will be displayed.
	 */
	function splitIntoDisplayedAndUndisplayedFilterValues( $filterValues ) {
		$maxDisplayedValues = 20;
		if ( count( $filterValues ) <= $maxDisplayedValues ) {
			// We shouldn't be here - exit out.
			return array( $filterValues, array() );
		}
		$sortedValues = $filterValues;
		arsort( $sortedValues );
		// Make sure that all of the displayed values have a higher popularity
		// than any of the undisplayed values - avoid having a situation where
		// some values with, say, two instances each end up in one group and
		// other values with two instances each end up in the other.
		$numDisplayedValues = $maxDisplayedValues;
		$sortedValueInstances = array_values( $sortedValues );
		while ( $numDisplayedValues > 0 ) {
			if ( $sortedValueInstances[$numDisplayedValues - 1] != $sortedValueInstances[$numDisplayedValues] ) {
				break;
			}
			$numDisplayedValues--;
		}
		$displayedValues = array_slice( $sortedValues, 0, $numDisplayedValues, true );
		$undisplayedValues = array_diff_key( $filterValues, $displayedValues );
		return array( $displayedValues, $undisplayedValues );
	}

	/**
	 * Print the line showing 'AND' values for a filter that has not
	 * been applied to the drilldown
	 */
	function printUnappliedFilterLine( $f, $cur_url = null ) {
		global $wgCargoDrilldownMinValuesForComboBox;

		$fieldType = $f->fieldDescription->mType;
		$isHierarchy = $f->fieldDescription->mIsHierarchy;
		if ( $cur_url === null ) {
			// If $cur_url wasn't passed in, we have to create it.
			$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
				$this->applied_filters, array( $f->name ),
				( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
				array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
			$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';
		}

		list( $tableNames, $joinConds, $mainTableName, $mainTableAlias ) = $this->getInitialQueryParts();
		if ( $isHierarchy ) {
			$results_line = $this->printUnappliedFilterValuesForHierarchy( $cur_url, $f,
				$this->fullTextSearchTerm, $this->applied_filters );
			return $this->printFilterLine( $f->name, false, true, $results_line, $f->tableAlias );
		} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
			$filter_values = $f->getTimePeriodValues( $this->fullTextSearchTerm,
				$this->applied_filters, $mainTableAlias, $tableNames, $joinConds );
		} else {
			$filter_values = $f->getAllValues( $this->fullTextSearchTerm, $this->applied_filters,
				false, $mainTableAlias, $tableNames, $joinConds );
		}
		if ( !is_array( $filter_values ) ) {
			return $this->printFilterLine( $f->name, false, false, $filter_values, $f->tableAlias );
		}

		$filter_name = urlencode( str_replace( ' ', '_', $f->name ) );
		$normal_filter = true;
		if ( count( $filter_values ) == 0 ) {
			$results_line = '(' . $this->msg( 'cargo-drilldown-novalues' )->text() . ')';
		} elseif ( $fieldType == 'Integer' || $fieldType == 'Float' ) {
			$results_line = $this->printNumberRanges( $filter_name, $filter_values );
		} elseif ( count( $filter_values ) >= 250 ) {
			list( $displayedValues, $undisplayedValues ) = $this->splitIntoDisplayedAndUndisplayedFilterValues( $filter_values );
			$results_line = $this->printUnappliedFilterValues( $cur_url, $f, $displayedValues );
			// Lots of values - switch to remote autocompletion.
			$results_line .= $this->printTextInput( $filter_name, 0, false, null, true,
				$f->fieldDescription->mIsList, $displayedValues, $f );
			$normal_filter = false;
		} elseif ( count( $filter_values ) >= $wgCargoDrilldownMinValuesForComboBox ) {
			list( $displayedValues, $undisplayedValues ) = $this->splitIntoDisplayedAndUndisplayedFilterValues( $filter_values );
			$results_line = $this->printUnappliedFilterValues( $cur_url, $f, $displayedValues );
			$results_line .= $this->printComboBoxInput( $filter_name, 0, $undisplayedValues );
			$normal_filter = false;
		} else {
			$results_line = $this->printUnappliedFilterValues( $cur_url, $f, $filter_values );
		}

		$text = $this->printFilterLine( $f->name, false, $normal_filter, $results_line, $f->tableAlias );
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
		$this->isReplacementTable = $wgRequest->getCheck( '_replacement' );
		$this->showSingleTable = $this->isReplacementTable || $wgRequest->getCheck( '_single' );
		if ( !$this->showSingleTable ) {
			$header .= $this->printTablesList( $tables );
		}
		if ( $this->isReplacementTable ) {
			$this->tableName = str_replace( '__NEXT', '', $this->tableName );
			$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
			$ctURL = $ctPage->getTitle()->getFullText();
			$viewURL = "$ctURL/" . $this->tableName;
			$viewLink = "[[$viewURL|{$this->tableName}]]";
			$header .= Html::rawElement( 'div',
				array( 'class' => 'warningbox' ),
				$this->msg( 'cargo-cargotables-replacementtable', $viewLink )->parse()
			);
		}
		list( $tableNames, $joinConds, $mainTableName, $mainTableAlias ) = $this->getInitialQueryParts();
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
			$tableURL =
				$this->makeBrowseURL( $this->tableName, null, array(), array(),
					( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
					array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
			$appliedFiltersHTML .= '<a href="' . $tableURL . '" title="' .
								   $this->msg( 'cargo-drilldown-resetfilters' )->text() . '">' .
								   $this->displayTableName() . '</a>';
		} else {
			$appliedFiltersHTML .= $this->displayTableName();
		}

		if ( $this->fullTextSearchTerm != null ) {
			$appliedFiltersHTML .= " > ";
			$appliedFiltersHTML .= $this->msg( 'cargo-drilldown-fulltext' )->text() . ': ';

			$remove_filter_url = $this->makeBrowseURL( $this->tableName, null,
				$this->applied_filters, array(),
					( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
					array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
			$appliedFiltersHTML .= "\n\t" . '<span class="drilldown-header-value">~ \'' .
				$this->fullTextSearchTerm .
				'\'</span> <a href="' . $remove_filter_url . '" title="' .
				$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
				$cgScriptPath . '/drilldown/resources/filter-x.png" /></a> ';
		}

		foreach ( $this->applied_filters as $i => $af ) {
			$appliedFiltersHTML .= ( $i == 0 && $this->fullTextSearchTerm == null ) ? " > " :
				"\n\t\t\t\t\t<span class=\"drilldown-header-value\">&</span> ";
			if ( $af->filter->tableAlias == $mainTableAlias ) {
				$filter_label = str_replace( '_', ' ', $af->filter->name );
				$id = 'mainTable';
			} else {
				$id = array_search( $af->filter->tableAlias, array_keys( $this->parentTables ) );
				$filter_label =
					str_replace( array( '_alias', '_' ), array( '', ' ' ),
						ucfirst( $af->filter->tableAlias ) . " &rarr; " . $af->filter->name );
			}
			// Add an "x" to remove this filter, if it has more
			// than one value.
			if ( count( $this->applied_filters[$i]->values ) > 1 ) {
				$temp_filters_array = $this->applied_filters;
				array_splice( $temp_filters_array, $i, 1 );
				$remove_filter_url = $this->makeBrowseURL( $this->tableName,
					$this->fullTextSearchTerm, $temp_filters_array,
					$this->dependentFieldsArray[$af->filter->tableName . '.' . $af->filter->name],
					( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
					array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
				array_splice( $temp_filters_array, $i, 0 );
				if ( $af->filter->tableAlias == $this->tableAlias ) {
					$appliedFiltersHTML .= $filter_label . ' <a href="' . $remove_filter_url . '" title="' .
						$this->msg( 'cargo-drilldown-removefilter' )->text() .
						'"><img src="' . $cgScriptPath . '/drilldown/resources/filter-x.png" /></a> : ';
				} else {
					$appliedFiltersHTML .= "\n\t\t\t\t<span class=\"drilldown-parent-tables-value\"
						id=\"$id\"> $filter_label" . ' <a href="' . $remove_filter_url
										   . '" title="' .
						$this->msg( 'cargo-drilldown-removefilter' )->text() .
						'"><img src="' . $cgScriptPath . '/drilldown/resources/filter-x.png" /></a> : ';
				}
			} else {
				if ( $af->filter->tableAlias == $mainTableAlias ) {
					$appliedFiltersHTML .= "$filter_label: ";
				} else {
					$appliedFiltersHTML .= "\n\t\t\t\t<span class=\"drilldown-parent-tables-value\"
						id=\"$id\"> $filter_label: ";
				}
			}
			$num_applied_values = count( $af->values );
			foreach ( $af->values as $j => $fv ) {
				if ( $j > 0 ) {
					$appliedFiltersHTML .= " <span class=\"drilldown-or\">" .
						$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
				}
				$filter_text = $this->printFilterValue( $af->filter, $fv->text );
				$temp_filters_array = $this->applied_filters;
				$removed_values = array_splice( $temp_filters_array[$i]->values, $j, 1 );
				if ( $num_applied_values == 1 ) {
					$remove_filter_url = $this->makeBrowseURL( $this->tableName,
						$this->fullTextSearchTerm, $temp_filters_array,
						$this->dependentFieldsArray[$af->filter->tableName . '.' . $af->filter->name],
						( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
						array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
				} else {
					$remove_filter_url = $this->makeBrowseURL( $this->tableName,
						$this->fullTextSearchTerm, $temp_filters_array, array(),
						( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
						array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
				}
				array_splice( $temp_filters_array[$i]->values, $j, 0, $removed_values );
				$appliedFiltersHTML .= "\n	" . "<span class=\"drilldown-header-value\">" .
					$filter_text . '</span> <a href="' . $remove_filter_url . '" title="' .
					$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
					$cgScriptPath . '/drilldown/resources/filter-x.png" /></a>';
			}

			if ( $af->search_terms != null ) {
				if ( count( $af->values ) > 0 ) {
					$appliedFiltersHTML .= " <span class=\"drilldown-or\">" .
						$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
				}
				foreach ( $af->search_terms as $j => $search_term ) {
					if ( $j > 0 ) {
						$appliedFiltersHTML .= " <span class=\"drilldown-or\">" .
							$this->msg( 'cargo-drilldown-or' )->text() . '</span> ';
					}
					$temp_filters_array = $this->applied_filters;
					$removed_values = array_splice( $temp_filters_array[$i]->search_terms, $j, 1 );
					$remove_filter_url = $this->makeBrowseURL( $this->tableName,
						$this->fullTextSearchTerm, $temp_filters_array, array(),
						( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
						array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
					array_splice( $temp_filters_array[$i]->search_terms, $j, 0, $removed_values );
					$appliedFiltersHTML .= "\n\t" . "<span class=\"drilldown-header-value\">~ '" .
						$search_term . '\'</span> <a href="' . $remove_filter_url . '" title="' .
						$this->msg( 'cargo-drilldown-removefilter' )->text() . '"><img src="' .
						$cgScriptPath . '/drilldown/resources/filter-x.png" /> </a>';
				}
			} elseif ( $af->lower_date != null || $af->upper_date != null ) {
				$appliedFiltersHTML .= "\n\t" . Html::element( 'span',
					array( 'class' => 'drilldown-header-value' ),
					$af->lower_date_string . " - " . $af->upper_date_string );
			}
			if ( $af->filter->tableName != $this->tableName ) {
				$appliedFiltersHTML .= "</span>";
			}
		}

		$appliedFiltersHTML .= "</div>\n";
		$header .= $appliedFiltersHTML;
		$header .= "<div class='drilldown-filters-wrapper'>\n";
		$drilldown_description = $this->msg( 'cargo-drilldown-docu' )->text();
		$header .= "				<p>$drilldown_description</p>\n";

		// Display every filter, each on its own line; each line will
		// contain the possible values, and, in parentheses, the
		// number of pages that match that value.
		$filtersHTML = "				<div class=\"drilldown-filters\">\n";
		$cur_url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
			$this->applied_filters, array(),
			( $this->drilldownTabsParams ) ? array( 'tab' => $this->curTabName ) :
			array( 'format' => $this->format, 'formatBy' => $this->formatBy ) );
		$cur_url .= ( strpos( $cur_url, '?' ) ) ? '&' : '?';

		if ( $displaySearchInput ) {
			$fullTextSearchInput = $this->printTextInput( '_search', 0, true, $this->fullTextSearchTerm );
			$filtersHTML .= self::printFilterLine( $this->msg( 'cargo-drilldown-fulltext' )->text(), false, false, $fullTextSearchInput );
		}
		// For each filter check if it has been applied or not. If it hasn't been applied, then
		// don't show the filters which depends(i.e. "dependent fields" parameter values) on it.
		$fieldsNotToBeShown = array();
		foreach ( $this->all_filters as $f ) {
			$dependentFields = $this->dependentFieldsArray[$f->tableName . '.' . $f->name];
			$filterApplied = false;
			foreach ( $this->applied_filters as $af ) {
				if ( $af->filter->name == $f->name && $af->filter->tableAlias == $f->tableAlias ) {
					$filterApplied = true;
					break;
				}
			}
			if ( !$filterApplied ) {
				if ( $dependentFields ) {
					$fieldsNotToBeShown = array_merge( $fieldsNotToBeShown, $dependentFields );
				}
			}
		}
		$currentTable = $mainTableAlias;
		$i = 0;
		foreach ( $this->all_filters as $f ) {
			if ( $f->tableAlias != $currentTable ) {
				$id = array_search( $f->tableAlias, array_keys( $this->parentTables ) );
				$tableAlias = str_replace( array( '_alias', '_' ), array( '', ' ' ), ucfirst( $f->tableAlias ) );
				if ( $i++ > 0 ) {
					$filtersHTML .= "\t</fieldset>";
				}
				$filtersHTML .= <<<END
	<fieldset class="drilldown-parent-filters-wrapper" id="$id">
		<legend> $tableAlias </legend>
END;
				$currentTable = $f->tableAlias;
			}
			if ( !in_array( $f->tableName . '.' . $f->name, $fieldsNotToBeShown ) ) {
				foreach ( $this->applied_filters as $af ) {
					if ( $af->filter->tableAlias == $f->tableAlias && $af->filter->name == $f->name ) {
						$fieldType = $f->fieldDescription->mType;
						if ( in_array( $fieldType,
							array( 'Date', 'Datetime', 'Integer', 'Float' ) ) ) {
							$filtersHTML .= $this->printUnappliedFilterLine( $f );
						} else {
							$filtersHTML .= $this->printAppliedFilterLine( $af );
						}
					}
				}
				foreach ( $this->remaining_filters as $rf ) {
					if ( $rf->tableAlias == $f->tableAlias && $rf->name == $f->name ) {
						$filtersHTML .= $this->printUnappliedFilterLine( $rf, $cur_url );
					}
				}
			}
		}
		$filtersHTML .= "	</fieldset></div> \n";

		if ( count( $this->all_filters ) == 0 ) {
			return '';
		}

		$header .= $filtersHTML;
		$header .= "</div> <!-- drilldown-filters-wrapper -->\n";

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
		if ( $this->isReplacementTable ) {
			$params['_replacement'] = null;
		}
		if ( $this->fullTextSearchTerm != '' ) {
			$params['_search'] = $this->fullTextSearchTerm;
		}
		if ( !$this->drilldownTabsParams ) {
			if ( $this->format ) {
				$params['format'] = $this->format;
				$params['formatBy'] = $this->formatBy;
			}
		} else {
			if ( $this->curTabName != key( $this->drilldownTabsParams ) ) {
				$params['tab'] = $this->curTabName;
			}
		}
		foreach ( $this->applied_filters as $i => $af ) {
			if ( count( $af->values ) == 1 ) {
				if ( $af->filter->tableAlias == $this->tableAlias ) {
					$key_string = str_replace( ' ', '_', $af->filter->name );
				} else {
					$key_string =
						str_replace( ' ', '_', $af->filter->tableAlias . '.' . $af->filter->name );
				}
				$value_string = str_replace( ' ', '_', $af->values[0]->text );
				$params[$key_string] = $value_string;
			} else {
				// @HACK - QueryPage's pagination-URL code,
				// which uses wfArrayToCGI(), doesn't support
				// two-dimensional arrays, which is what we
				// need - instead, add the brackets directly
				// to the key string
				foreach ( $af->values as $i => $value ) {
					if ( $af->filter->tableAlias == $this->tableAlias ) {
						$key_string = str_replace( ' ', '_', $af->filter->name . "[$i]" );
					} else {
						$key_string = str_replace( ' ', '_',
							$af->filter->tableAlias . '.' . $af->filter->name . "[$i]" );
					}
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

	function getInitialQueryParts() {
		$cdb = CargoUtils::getDB();
		if ( $this->isReplacementTable ) {
			$mainTableName = $this->tableName . '__NEXT';
		} else {
			$mainTableName = $this->tableName;
		}
		$mainTableAlias = CargoUtils::makeDifferentAlias( $mainTableName );
		$mainTable = array( $mainTableAlias => $mainTableName );
		$tableNames = $mainTable;
		$joinConds = array();
		foreach ( $this->parentTables as $tableAlias => $extraParams ) {
			if ( array_key_exists( '_localField', $extraParams ) ) {
				$localFieldtableName = $mainTableName;
				$localFieldtableAlias = $mainTableAlias;
				foreach ( $this->all_filters as $f ) {
					if ( $f->tableName == $mainTableName &&
						 $f->name == $extraParams['_localField'] ) {
						if ( $f->fieldDescription->mIsList ) {
							$localFieldtableName = $this->tableName . "__$f->name";
							$localFieldtableAlias = $mainTableAlias . "__$f->name";
							if ( !array_key_exists( $localFieldtableAlias, $tableNames ) ) {
								$tableNames[$localFieldtableAlias] = $localFieldtableName;
							}
							$joinConds[$localFieldtableAlias] =
								CargoUtils::joinOfMainAndFieldTable( $cdb, array(
									$mainTableAlias => $mainTableName,
								), array( $localFieldtableAlias => $localFieldtableName ) );
						} elseif ( $f->fieldDescription->mIsHierarchy ) {
							$localFieldtableName = $this->tableName . '__' . $f->name . '__hierarchy';
							$localFieldtableAlias = $mainTableAlias . '__' . $f->name . '__hierarchy';
							if ( !array_key_exists( $localFieldtableAlias, $tableNames ) ) {
								$tableNames[$localFieldtableAlias] = $localFieldtableName;
							}
							$joinConds[$localFieldtableAlias] =
								CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
									array( $f->tableAlias => $f->tableName ), $f->name,
									array( $localFieldtableAlias => $localFieldtableName ) );
						}
						break;
					}
				}
			}
			if ( array_key_exists( '_remoteField', $extraParams ) ) {
				$tableName = $extraParams['Name'];
				$remoteFieldtableAlias = $tableAlias;
				$remoteFieldtableName = $tableName;
				foreach ( $this->all_filters as $f ) {
					if ( $f->tableAlias == $tableAlias && $f->name == $extraParams['_remoteField'] ) {
						if ( $f->fieldDescription->mIsList ) {
							$remoteFieldtableName = $tableName . "__$f->name";
							$remoteFieldtableAlias = $tableAlias . "__$f->name";
						} elseif ( $f->fieldDescription->mIsHierarchy ) {
							$remoteFieldtableName = $tableName . '__' . $f->name . '__hierarchy';
							$remoteFieldtableAlias = $tableAlias . '__' . $f->name . '__hierarchy';
						} else {
							$parentTableName = $tableName;
							if ( !array_key_exists( $tableAlias, $tableNames ) ) {
								$tableNames[$tableAlias] = $parentTableName;
							}
							if ( $localFieldtableName != $mainTableName ) {
								$joinConds[$tableAlias] =
									CargoUtils::joinOfMainAndParentTable( $cdb,
										array( $localFieldtableAlias => $localFieldtableName ),
										'_value', array( $tableAlias => $tableName ),
										$extraParams['_remoteField'] );
							} else {
								$joinConds[$tableAlias] =
									CargoUtils::joinOfMainAndParentTable( $cdb,
										array( $localFieldtableAlias => $localFieldtableName ),
										$extraParams['_localField'], array(
											$tableAlias => $tableName,
										), $extraParams['_remoteField'] );
							}
						}
						if ( $remoteFieldtableName != $tableName ) {
							if ( !array_key_exists( $remoteFieldtableAlias, $tableNames ) ) {
								$tableNames[$remoteFieldtableAlias] = $remoteFieldtableName;
							}
							if ( $localFieldtableName != $mainTableName ) {
								$joinConds[$remoteFieldtableAlias] =
									CargoUtils::joinOfMainAndParentTable( $cdb,
										array( $localFieldtableAlias => $localFieldtableName ),
										'_value',
										array( $remoteFieldtableAlias => $remoteFieldtableName ),
										'_value' );
							} else {
								$joinConds[$remoteFieldtableAlias] =
									CargoUtils::joinOfMainAndParentTable( $cdb,
										array( $localFieldtableAlias => $localFieldtableName ),
										$extraParams['_localField'],
										array( $remoteFieldtableAlias => $remoteFieldtableName ),
										'_value' );
							}
							if ( !array_key_exists( $tableAlias, $tableNames ) ) {
								$tableNames[$tableAlias] = $tableName;
							}
							if ( $f->fieldDescription->mIsList ) {
								$joinConds[$tableAlias] =
									CargoUtils::joinOfFieldAndMainTable( $cdb, array(
										$remoteFieldtableAlias => $remoteFieldtableName,
									), array( $tableAlias => $tableName ) );
							} else {
								$joinConds[$tableAlias] =
									CargoUtils::joinOfFieldAndMainTable( $cdb,
										array( $remoteFieldtableAlias => $remoteFieldtableName ),
										$tableName, true, $f->name );
							}
						}
						break;
					}
				}
				if ( $extraParams['_remoteField'] == '_pageName' ) {
					$parentTableName = $tableName;
					if ( !array_key_exists( $tableAlias, $tableNames ) ) {
						$tableNames[$tableAlias] = $parentTableName;
					}
					if ( $localFieldtableName != $mainTableName ) {
						$joinConds[$tableAlias] =
							CargoUtils::joinOfMainAndParentTable( $cdb,
								array( $localFieldtableAlias => $localFieldtableName ), '_value',
								array( $tableAlias => $tableName ), $extraParams['_remoteField'] );
					} else {
						$joinConds[$tableAlias] =
							CargoUtils::joinOfMainAndParentTable( $cdb,
								array( $localFieldtableAlias => $localFieldtableName ),
								$extraParams['_localField'], array( $tableAlias => $tableName ),
								$extraParams['_remoteField'] );
					}
				}
			}
		}

		return array( $tableNames, $joinConds, $mainTableName, $mainTableAlias );
	}

	function getQueryInfo() {
		$cdb = CargoUtils::getDB();

		list( $tableNames, $joinConds ) = $this->getInitialQueryParts();

		$conds = array();
		$queryOptions = array();
		$queryOptions['GROUP BY'] = array();
		// $fieldStr, $whereStr, $groupByStr are required for CargoSQLQuery object
		if ( !$this->drilldownTabsParams ) {
			$fieldsStr = array( "$this->tableAlias._pageName" );
		} else {
			$fieldsStr = array();
		}
		$whereStr = array();
		$groupByStr = array();
		if ( $this->fullTextSearchTerm != null ) {
			list( $curTableNames, $curConds, $curJoinConds, $whereConds ) =
				self::getFullTextSearchQueryParts( $this->fullTextSearchTerm, $this->tableName,
					$this->tableAlias, $this->searchablePages, $this->searchableFiles );
			$conds = array_merge( $conds, $curConds );
			foreach ( $curJoinConds as $tableAlias => $curJoinCond ) {
				if ( !array_key_exists( $tableAlias, $joinConds ) ) {
					$tableName = $curTableNames[$tableAlias];
					$joinConds = array_merge( $joinConds, array( $tableAlias => $curJoinCond ) );
					$tableNames[$tableAlias] = $tableName;
				}
			}
			$whereStr = array_merge( $whereStr, $whereConds );
		}

		foreach ( $this->applied_filters as $i => $af ) {
			list( $curTableNames, $curConds, $curJoinConds ) = $af->getQueryParts( $this->tableName );
			$conds = array_merge( $conds, $curConds );
			$whereStr = array_merge( $whereStr, $curConds );
			foreach ( $curJoinConds as $tableAlias => $curJoinCond ) {
				if ( !array_key_exists( $tableAlias, $joinConds ) ) {
					$tableName = $curTableNames[$tableAlias];
					$joinConds = array_merge( $joinConds, array( $tableAlias => $curJoinCond ) );
					$tableNames[$tableAlias] = $tableName;
				}
			}
			if ( $af->filter->fieldDescription->mIsHierarchy && $af->filter->fieldDescription->mIsList ) {
				$hierarchyFieldTable = $this->tableName . "__" . $af->filter->name;
				$hierarchyFieldAlias = $this->tableAlias . "__" . $af->filter->Alias;
				$queryOptions['GROUP BY'] = CargoUtils::escapedFieldName( $cdb,
					array( $hierarchyFieldAlias => $hierarchyFieldTable ), '_rowID' );
			}
		}

		if ( !$this->drilldownTabsParams ) {
			$aliasedFieldNames = array(
				'title' => CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ), '_pageName' ),
				'value' => CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ), '_pageName' ),
				'namespace' => CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ), '_pageNamespace' ),
				'ID' => CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ), '_pageID' )
			);
		} else {
			$aliasedFieldNames = array();
		}
		if ( $this->drilldownTabsParams ) {
			if ( array_key_exists( $this->curTabName, $this->drilldownTabsParams ) ) {
				$currentTabParams = $this->drilldownTabsParams[$this->curTabName];
				$this->format = strtolower( $currentTabParams['format'] );
				$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
				if ( array_key_exists( $this->format, $formatClasses ) ) {
					$formatClass = $formatClasses[$this->format];
				} else {
					$formatClass = $formatClasses['category'];
				}
				$isDeferred = $formatClass::isDeferred();
				$fields = $currentTabParams['fields'];
				$calendarFieldFound = false;
				$coordsFieldFound = false;
				$fileFieldFound = false;
				foreach ( $fields as $fieldAlias => $field ) {
					$fieldPartTableAlias = $this->tableAlias;
					$fieldPartTableName = $this->tableName;
					if ( strpos( $field, '.' ) ) {
						$fieldParts = explode( '.', $field );
						if ( count( $fieldParts ) == 2 ) {
							foreach ( $tableNames as $tableAlias => $tableName ) {
								if ( $fieldParts[0] == $tableAlias || $fieldParts[0] == $tableName ) {
									$fieldPartTableName = $tableName;
									$fieldPartTableAlias = $tableAlias;
									break;
								}
							}
							$field = $fieldParts[1];
						}
					}
					if ( $this->format == 'map' || $this->format == 'openlayers' || $this->format == 'googlemaps' ) {
						foreach ( $this->coordsFields as $tableAlias => $coordsField ) {
							if ( !$coordsFieldFound && $coordsField == $field && $tableAlias == $fieldPartTableAlias ) {
								$coordsFieldFound = true;
								$coordsFieldTableName = $fieldPartTableName;
								$coordsFieldTableAlias = $fieldPartTableAlias;
								$coordsFieldName = $field;
								$coordsFieldAlias = $fieldAlias;
							}
						}
					} elseif ( $this->format == 'gallery' ) {
						foreach ( $this->fileFields as $fieldName => $fieldDescription ) {
							if ( !$fileFieldFound && $field == $fieldName ) {
								$fileFieldFound = true;
								$fileFieldTableName = $fieldPartTableName;
								$fileFieldTableAlias = $fieldPartTableAlias;
								$fileFieldName = $field;
								$fileFieldAlias = $fieldAlias;
							}
						}
					} else {
						if ( is_string( $fieldAlias ) ) {
							$aliasedFieldNames[$fieldAlias] =
								CargoUtils::escapedFieldName( $cdb,
									array( $fieldPartTableAlias => $fieldPartTableName ), $field );
							$fieldsStr[] = $fieldPartTableAlias . '.' . $field . '=' . $fieldAlias;
						} else {
							$aliasedFieldNames[$field] =
								CargoUtils::escapedFieldName( $cdb,
									array( $fieldPartTableAlias => $fieldPartTableName ), $field );
							$fieldsStr[] = $fieldPartTableAlias . '.' . $field;
						}
					}
					if ( $this->format == 'calendar' ) {
						foreach ( $this->dateFields as $dateField ) {
							if ( !$calendarFieldFound && $dateField == $field ) {
								$calendarFieldFound = true;
								$calendarFieldName = $field;
								$calendarFieldTableAlias = $fieldPartTableAlias;
							}
						}
					}
				}
			}
		}
		if ( $this->format == 'map' || $this->format == 'openlayers' || $this->format == 'googlemaps' ) {
			if ( !$this->drilldownTabsParams ) {
				$coordsFieldTableName = $this->tableName;
				$coordsFieldTableAlias = $this->tableAlias;
				$coordsFieldName = $this->formatBy;
				$coordsFieldAlias = $coordsFieldName;
			}
			$aliasedFieldNames['coordinates'] =
				CargoUtils::escapedFieldName( $cdb,
					array( $coordsFieldTableAlias => $coordsFieldTableName ), $coordsFieldName . '__full' );
			$aliasedFieldNames['coordinates_lat'] =
				CargoUtils::escapedFieldName( $cdb,
					array( $coordsFieldTableAlias => $coordsFieldTableName ), $coordsFieldName . '__lat' );
			$aliasedFieldNames['coordinates_lon'] =
				CargoUtils::escapedFieldName( $cdb,
					array( $coordsFieldTableAlias => $coordsFieldTableName ), $coordsFieldName . '__lon' );
			if ( is_string( $coordsFieldAlias ) ) {
				$fieldsStr[] = $coordsFieldTableAlias . '.' . $coordsFieldName . '=' . $coordsFieldAlias;
			} else {
				$fieldsStr[] = $coordsFieldTableAlias . '.' . $coordsFieldName;
			}
		} elseif ( $this->format == 'gallery' ) {
			if ( !$this->drilldownTabsParams ) {
				$fileFieldTableName = $this->tableName;
				$fileFieldTableAlias = $this->tableAlias;
				$fileFieldName = $this->formatBy;
				$fileFieldAlias = null;
			}
			foreach ( $this->fileFields as $fieldName => $fieldDescription ) {
				if ( $fileFieldName != $fieldName ) {
					continue;
				}
				if ( $fieldDescription->mIsList ) {
					$fieldTableName = $fileFieldTableName . '__' . $fileFieldName;
					$fieldTableAlias = $fileFieldTableAlias . '__' . $fileFieldName;
					$curJoinConds[$fieldTableAlias] =
						CargoUtils::joinOfMainAndFieldTable( $cdb,
							array( $fileFieldTableAlias => $fileFieldTableName ),
							array( $fieldTableAlias => $fieldTableName ) );
					$tableNames[$fieldTableAlias] = $fieldTableName;
					$aliasedFieldNames['file'] =
						CargoUtils::escapedFieldName( $cdb,
							array( $fieldTableAlias => $fieldTableName ), '_value' );
					if ( is_string( $fileFieldAlias ) ) {
						$fieldsStr[] = $fieldTableAlias . '._value=' . $fileFieldAlias;
					} else {
						$fieldsStr[] = $fieldTableAlias . '._value';
					}
					$joinConds = array_merge( $joinConds, $curJoinConds );
				} else {
					$aliasedFieldNames['file'] =
						CargoUtils::escapedFieldName( $cdb,
							array( $fileFieldTableAlias => $fileFieldTableName ),
							$fileFieldName );
					if ( is_string( $fileFieldAlias ) ) {
						$fieldsStr[] = $fileFieldTableAlias . '.' . $fileFieldName . '=' . $fileFieldAlias;
					} else {
						$fieldsStr[] = $fileFieldTableAlias . '.' . $fileFieldName;
					}
				}
			}
		}
		if ( $this->format == 'calendar' ) {
			if ( !$this->drilldownTabsParams ) {
				$calendarFieldName = $this->formatBy;
				$calendarFieldTableAlias = $this->tableAlias;
			}
			$res =
				$cdb->select( $tableNames,
					"MAX( $calendarFieldTableAlias.$calendarFieldName ) as start_date", $conds,
					null, array(), $joinConds );
			$row = $cdb->fetchRow( $res );
			if ( $row['start_date'] ) {
				if ( $this->drilldownTabsParams ) {
					if ( !array_key_exists( 'start date', $currentTabParams ) ) {
						$currentTabParams['start_date'] = $row['start_date'];
						$this->drilldownTabsParams[$this->curTabName] = $currentTabParams;
					}
				} else {
					$this->calendarFields[$this->formatBy] = $row['start_date'];
				}
			}
		}
		if ( $this->fullTextSearchTerm != null ) {
			$fileDataTableName = '_fileData';
			$fileDataTableAlias = CargoUtils::makeDifferentAlias( $fileDataTableName );
			$pageDataTableName = '_pageData';
			$pageDataTableAlias = CargoUtils::makeDifferentAlias( $pageDataTableName );
			if ( $this->tableName == '_fileData' || !$this->searchablePages ) {
				$fileTextAlias = $this->msg( 'cargo-drilldown-filetext' )->text();
				$aliasedFieldNames[$fileTextAlias] = CargoUtils::escapedFieldName( $cdb, array(
					$fileDataTableAlias => $fileDataTableName ),
					'_fullText' );
				$aliasedFieldNames['foundFileMatch'] = '1';
				$fieldsStr[] = "$fileDataTableAlias._fullText=$fileTextAlias";
			} else {
				$pageTextAlias = $this->msg( 'cargo-drilldown-pagetext' )->text();
				$aliasedFieldNames[$pageTextAlias] = CargoUtils::escapedFieldName( $cdb, array(
					$pageDataTableAlias => $pageDataTableName ), '_fullText' );
				$fieldsStr[] = "$pageDataTableAlias._fullText=$pageTextAlias";
			}
			if ( $this->searchableFiles ) {
				$fileNameAlias = $this->msg( 'cargo-drilldown-filename' )->text();
				$aliasedFieldNames[$fileNameAlias] = CargoUtils::escapedFieldName( $cdb, array(
					$fileDataTableAlias => $fileDataTableName ), '_pageName' );
				$fieldsStr[] = "$fileDataTableAlias._pageName=$fileNameAlias";
				$fileTextAlias = $this->msg( 'cargo-drilldown-filetext' )->text();
				$aliasedFieldNames[$fileTextAlias] = CargoUtils::escapedFieldName( $cdb, array(
					$fileDataTableAlias => $fileDataTableName ), '_fullText' );
				$fieldsStr[] = "$fileDataTableAlias._fullText=$fileTextAlias";
				// @HACK - the result set may contain both
				// pages and files that match the search term.
				// So how do we know, for each result row,
				// whether it's for a page or a file? We add
				// the "match on file" clause as a (boolean)
				// query field. There may be a more efficient
				// way to do this, using SQL variables or
				// something.
				$aliasedFieldNames['foundFileMatch'] = CargoUtils::fullTextMatchSQL( $cdb, array( $fileDataTableAlias => $fileDataTableName ), '_fullText', $this->fullTextSearchTerm );
			}
		}
		$queryOptions['GROUP BY'] =
			array_merge( $queryOptions['GROUP BY'], array_values( $aliasedFieldNames ) );
		$tablesStr = '';
		$i = 0;
		foreach ( $tableNames as $tableAlias => $tableName ) {
			if ( $i ++ > 0 ) {
				$tablesStr .= ',';
			}
			if ( $tableAlias ) {
				$tablesStr .= $tableName . '=' . $tableAlias;
			} else {
				$tablesStr .= $tableName;
			}
		}
		if ( !$this->drilldownTabsParams ) {
			if ( $this->formatBy && $this->format != 'map' && $this->format != 'gallery' ) {
				$fieldsStr[] = $this->tableAlias . '.' . $this->formatBy;
			}
		}
		$fieldsStr = implode( ',', $fieldsStr );
		$whereStr = implode( ' AND ', $whereStr );
		$whereStr = str_replace( 'DAY', 'DAYOFMONTH', $whereStr );
		$joinOnStr = array();
		foreach ( $joinConds as $table => $joinCond ) {
			$joinCondStr = str_replace( '`', '', $joinCond[1] );
			$joinCondStr = str_replace( 'cargo__', '', $joinCondStr );
			$joinOnStr[] = $joinCondStr;
		}
		$joinOnStr = implode( ',', $joinOnStr );
		$groupByStr = $queryOptions['GROUP BY'];
		$groupByStr = implode( ',', $groupByStr );
		$orderByStr = $groupByStr;
		$havingStr = null;
		$limitStr = $this->limit;
		$offsetStr = $this->offset;
		$this->sqlQuery =
			CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );
		$queryInfo = array(
			'tables' => $tableNames,
			'fields' => $aliasedFieldNames,
			'conds' => $conds,
			'join_conds' => $joinConds,
			'options' => $queryOptions
		);

		return $queryInfo;
	}

	static function getFullTextSearchQueryParts( $searchTerm, $mainTableName, $mainTableAlias,
			$searchablePages, $searchableFiles ) {
		$cdb = CargoUtils::getDB();

		$tableNames = array();
		$conds = array();
		$joinConds = array();
		$whereConds = array();

		// Special quick handling for the "data" tables.
		if ( $mainTableName == '_fileData' || $mainTableName == '_pageData' ) {
			$conds[] = CargoUtils::fullTextMatchSQL( $cdb, array( $mainTableAlias => $mainTableName ), '_fullText', $searchTerm );
			$whereConds[] = "$mainTableAlias._fullText MATCHES '$searchTerm'";
			return array( $tableNames, $conds, $joinConds, $whereConds );
		}

		if ( $searchablePages ) {
			$pageDataTableName = '_pageData';
			$pageDataTableAlias = CargoUtils::makeDifferentAlias( $pageDataTableName );
			$tableNames[$pageDataTableAlias] = $pageDataTableName;
			$joinConds[$pageDataTableAlias] = array(
				'LEFT OUTER JOIN',
				CargoUtils::escapedFieldName( $cdb, array( $mainTableAlias => $mainTableName ), '_pageID' ) .
				' = ' .
				CargoUtils::escapedFieldName( $cdb, array( $pageDataTableAlias => $pageDataTableName ), '_pageID' )
			);
		}

		if ( $searchableFiles ) {
			$fileTableName = $mainTableName . '___files';
			$fileTableAlias = $mainTableAlias .'___files';
			$tableNames[$fileTableAlias] = $fileTableName;
			$joinConds[$fileTableAlias] = array(
				'LEFT OUTER JOIN',
				CargoUtils::escapedFieldName( $cdb, array( $mainTableAlias => $mainTableName ), '_pageID' ) . ' = ' .
				CargoUtils::escapedFieldName( $cdb, array( $fileTableAlias => $fileTableName ), '_pageID' ),
			);
			$fileDataTableName = '_fileData';
			$fileDataTableAlias = CargoUtils::makeDifferentAlias( $fileDataTableName );
			$tableNames[$fileDataTableAlias] = $fileDataTableName;
			$joinConds[$fileDataTableAlias] = array(
				'JOIN',
				CargoUtils::escapedFieldName( $cdb, array( $fileTableAlias => $fileTableName ), '_fileName' ) .
				' = ' .
				CargoUtils::escapedFieldName( $cdb, array( $fileDataTableAlias => $fileDataTableName ), '_pageTitle' )
			);
			if ( $searchablePages ) {
				$conds[] = CargoUtils::fullTextMatchSQL( $cdb, array( $fileDataTableAlias => $fileDataTableName ), '_fullText', $searchTerm ) . ' OR ' .
					CargoUtils::fullTextMatchSQL( $cdb, array( $pageDataTableAlias => $pageDataTableName ), '_fullText', $searchTerm );
				$whereConds[] = "$fileDataTableAlias._fullText MATCHES '$searchTerm' OR $pageDataTableAlias._fullText MATCHES '$searchTerm'";
			} else {
				$conds[] = CargoUtils::fullTextMatchSQL( $cdb, array( $fileDataTableAlias => $fileDataTableName ), '_fullText', $searchTerm );
				$whereConds[] = "$fileDataTableAlias._fullText MATCHES '$searchTerm'";
			}
		} else {
			$conds[] = CargoUtils::fullTextMatchSQL( $cdb, array( $pageDataTableAlias => $pageDataTableName ), '_fullText', $searchTerm );
			$whereConds[] = "$pageDataTableAlias._fullText MATCHES '$searchTerm'";
		}

		return array( $tableNames, $conds, $joinConds, $whereConds );
	}

	function getOrderFields() {
		$cdb = CargoUtils::getDB();
		return array( CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ),
		'_pageTitle' ) );
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
		$url = $this->makeBrowseURL( $this->tableName, $this->fullTextSearchTerm,
			$this->applied_filters, array(),
			array( 'limit' => $this->limit, 'offset' => $this->offset ) );
		// Add tabs for displaying results in map or timleine format
		if ( $this->drilldownTabsParams ) {
			$tabs = '';
			$i = 0;
			foreach ( $this->drilldownTabsParams as $drilldownTabName => $params ) {
				if ( $i++ == 1 ) {
					$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
				}
				if ( $i > 1 ) {
					$tabUrl = $url . 'tab=' . $drilldownTabName;
				} else {
					$tabUrl = $url;
				}
				$tabs .= Html::rawElement( 'li', array(
					'role' => 'presentation',
					'class' => ( $this->curTabName == $drilldownTabName )
						? 'selected' : null,
				), Html::rawElement( 'a', array(
					'role' => 'tab',
					'href' => $tabUrl,
				), ucfirst( str_replace( '_', ' ', $drilldownTabName ) ) ) );
			}
			$out->addHTML( Html::rawElement( 'div', array(
				'id' => 'drilldown-format-tabs-wrapper',
			), Html::rawElement( 'ul', array(
					'class' => 'drilldown-tabs',
					'id' => 'drilldown-format-tabs',
					'role' => 'tablist',
				), $tabs ) . $this->closeList() ) );
		} else {
			if ( count( $this->coordsFields ) > 0 || count( $this->dateFields ) > 0 ) {
				$tabs = Html::rawElement( 'li', array(
					'role' => 'presentation',
					'class' => ( $this->format == '' ) ? 'selected' : null,
				), Html::rawElement( 'a', array(
					'role' => 'tab',
					'href' => $url,
				), 'Main' ) );
				$url .= ( strpos( $url, '?' ) ) ? '&' : '?';
				foreach ( $this->coordsFields as $coordsField ) {
					$tabs .= Html::rawElement( 'li', array(
						'role' => 'presentation',
						'class' => ( $this->format == 'map' && $coordsField == $this->formatBy )
							? 'selected' : null,
					), Html::rawElement( 'a', array(
						'role' => 'tab',
						'href' => $url . 'format=map&formatBy=' . $coordsField,
					), $this->msg( 'cargo-drilldown-mapformat' )->text() . ': ' .
					   str_replace( '_', ' ', $coordsField ) ) );
				}
				foreach ( $this->dateFields as $i => $dateField ) {
					$tabs .= Html::rawElement( 'li', array(
						'role' => 'presentation',
						'class' => ( $this->format == 'timeline' && $dateField == $this->formatBy )
							? 'selected' : null,
					), Html::rawElement( 'a', array(
						'role' => 'tab',
						'href' => $url . 'format=timeline&formatBy=' . $dateField,
					), $this->msg( 'cargo-drilldown-timelineformat' )->text() . ': ' .
					   str_replace( '_', ' ', $dateField ) ) );
				}
				foreach ( $this->calendarFields as $calendarField => $startDate ) {
					$tabs .= Html::rawElement( 'li', array(
						'role' => 'presentation',
						'class' => ( $this->format == 'calendar' && $calendarField == $this->formatBy )
							? 'selected' : null,
					), Html::rawElement( 'a', array(
						'role' => 'tab',
						'href' => $url . 'format=calendar&formatBy=' . $calendarField,
					), $this->msg( 'cargo-drilldown-calendarformat' )->text() . ': ' .
					   str_replace( '_', ' ', $calendarField ) ) );
				}
				foreach ( $this->fileFields as $fileField => $fieldDescription ) {
					$tabs .= Html::rawElement( 'li', array(
						'role' => 'presentation',
						'class' => ( $this->format == 'gallery' && $fileField == $this->formatBy )
							? 'selected' : null,
					), Html::rawElement( 'a', array(
						'role' => 'tab',
						'href' => $url . 'format=gallery&formatBy=' . $fileField,
					), $this->msg( 'cargo-drilldown-galleryformat' )->text() . ': ' .
					   str_replace( '_', ' ', $fileField ) ) );
				}
				$out->addHTML( Html::rawElement( 'div', array(
					'id' => 'drilldown-format-tabs-wrapper',
				), Html::rawElement( 'ul', array(
						'class' => 'drilldown-tabs',
						'id' => 'drilldown-format-tabs',
						'role' => 'tablist',
					), $tabs ) . $this->closeList() ) );
			}
		}

		if ( array_key_exists( $this->curTabName, $this->drilldownTabsParams ) ) {
			$currentTabParams = $this->drilldownTabsParams[$this->curTabName];
			$this->format = strtolower( $currentTabParams['format'] );
		}
		$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
		if ( array_key_exists( $this->format, $formatClasses ) ) {
			$formatClass = $formatClasses[$this->format];
		} else {
			$formatClass = $formatClasses['category'];
		}
		$isDeferred = $formatClass::isDeferred();
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $this->sqlQuery );
		$queryDisplayer->mFieldDescriptions = $this->sqlQuery->mFieldDescriptions;
		if ( $this->format ) {
			$queryDisplayer->mFormat = $this->format;
		} else {
			$queryDisplayer->mFormat = 'category';
		}

		$formatter = $queryDisplayer->getFormatter( $out );
		if ( !$isDeferred ) {
			try {
				$queryResults = $this->sqlQuery->run();
			} catch ( Exception $e ) {
				return CargoUtils::formatError( $e->getMessage() );
			}
			if ( $this->drilldownTabsParams ) {
				foreach ( $currentTabParams as $parameter => $value ) {
					if ( is_string( $value ) ) {
						$value = stripslashes( $value );
					}
					$queryDisplayer->mDisplayParams[$parameter] = $value;
				}
			}
			if ( $this->format == 'gallery' ) {
				if ( !array_key_exists( 'mode', $currentTabParams ) ) {
					$queryDisplayer->mDisplayParams['mode'] = 'packed';
				}
				if ( !array_key_exists( 'show bytes', $currentTabParams ) ) {
					$queryDisplayer->mDisplayParams['show bytes'] = false;
				}
				if ( !array_key_exists( 'show dimensions', $currentTabParams ) ) {
					$queryDisplayer->mDisplayParams['show dimensions'] = false;
				}
			}
			$html = $queryDisplayer->displayQueryResults( $formatter, $queryResults );
			$out->addHTML( $html );

			return;
		} else {
			$displayParams = array();
			if ( $this->format == 'calendar' ) {
				if ( $this->drilldownTabsParams ) {
					$displayParams['start date'] = $currentTabParams['start_date'];
				} else {
					$displayParams['start date'] = $this->calendarFields[$this->formatBy];
				}
			}
			if ( $this->drilldownTabsParams ) {
				foreach ( $currentTabParams as $parameter => $value ) {
					if ( is_string( $value ) ) {
						$value = stripslashes( $value );
					}
					$displayParams[$parameter] = $value;
				}
			}
			$text = $formatter->queryAndDisplay( array( $this->sqlQuery ), $displayParams );
			$out->addHTML( $text );

			return;
		}
	}

	function openList( $offset ) {
	}

	function closeList() {
		return "\n\t\t\t<br style=\"clear: both\" />\n";
	}
}

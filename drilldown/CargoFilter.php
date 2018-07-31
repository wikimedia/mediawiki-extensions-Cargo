<?php
/**
 * Defines a class, CargoFilter, that holds the information in a filter.
 *
 * Based heavily on SD_Filter.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoFilter {
	public $name;
	public $tableAlias;
	public $tableName;
	public $fieldType;
	public $fieldDescription;
	public $searchableFiles;
	public $searchablePages;
	public $allowed_values;
	public $required_filters = array();
	public $possible_applied_filters = array();

	function __construct( $name, $tableAlias, $tableName, $fieldDescription, $searchablePages,
			$searchableFiles ) {
		$this->name = $name;
		$this->tableAlias = $tableAlias;
		$this->tableName = $tableName;
		$this->fieldDescription = $fieldDescription;
		$this->searchablePages = $searchablePages;
		$this->searchableFiles = $searchableFiles;
	}

	public function addRequiredFilter( $filterName ) {
		$this->required_filters[] = $filterName;
	}

	public function getTableName() {
		return $this->tableName;
	}

	function getDateParts( $dateFromDB ) {
		// Does this only happen for MS SQL Server DBs?
		if ( $dateFromDB instanceof DateTime ) {
			$year = $dateFromDB->format( 'Y' );
			$month = $dateFromDB->format( 'm' );
			$day = $dateFromDB->format( 'd' );
			return array( $year, $month, $day );
		}

		// It's a string.
		$dateParts = explode( '-', $dateFromDB );
		if ( count( $dateParts ) == 3 ) {
			return $dateParts;
		} else {
			// year, month, day
		return array( $dateParts[0], 0, 0 );
		}
	}

	/**
	 *
	 * @param array $appliedFilters
	 * @return string
	 */
	function getTimePeriod( $fullTextSearchTerm, $appliedFilters, $tableNames = array(),
			$joinConds = array() ) {
		// If it's not a date field, return null.
		if ( !in_array( $this->fieldDescription->mType, array( 'Date', 'Datetime' ) ) ) {
			return null;
		}

		$cdb = CargoUtils::getDB();
		$date_field = $this->tableAlias . '.' . $this->name;
		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm,
			$appliedFilters, $tableNames, $joinConds );
		$res = $cdb->select( $tableNames, array( "MIN($date_field) AS min_date", "MAX($date_field) AS max_date" ), $conds, null,
			null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$minDate = $row['min_date'];
		if ( is_null( $minDate ) ) {
			return null;
		}
		list( $minYear, $minMonth, $minDay ) = $this->getDateParts( $minDate );
		$maxDate = $row['max_date'];
		list( $maxYear, $maxMonth, $maxDay ) = $this->getDateParts( $maxDate );
		$yearDifference = $maxYear - $minYear;
		$monthDifference = ( 12 * $yearDifference ) + ( $maxMonth - $minMonth );
		if ( $yearDifference > 30 ) {
			return 'decade';
		} elseif ( $yearDifference > 2 ) {
			return 'year';
		} elseif ( $monthDifference > 1 ) {
			return 'month';
		} else {
			return 'day';
		}
	}

	/**
	 *
	 * @param array $appliedFilters
	 * @return array
	 */
	function getQueryParts( $fullTextSearchTerm, $appliedFilters, $tableNames = array(),
			$joinConds = array() ) {
		$cdb = CargoUtils::getDB();

		if ( !$tableNames ) {
			$tableNames = array( $this->tableName => $this->tableAlias );
		}
		$conds = array();

		if ( $fullTextSearchTerm != null ) {
			list( $curTableNames, $curConds, $curJoinConds ) =
				CargoDrilldownPage::getFullTextSearchQueryParts( $fullTextSearchTerm,
					$this->tableName, $this->tableAlias, $this->searchablePages,
					$this->searchableFiles );
			$conds = array_merge( $conds, $curConds );
			foreach ( $curJoinConds as $tableAlias => $curJoinCond ) {
				if ( !array_key_exists( $tableAlias, $joinConds ) ) {
					$tableName = $curTableNames[$tableAlias];
					$joinConds = array_merge( $joinConds, array( $tableAlias => $curJoinCond ) );
					$tableNames[$tableAlias] = $tableName;
				}
			}
		}

		foreach ( $appliedFilters as $af ) {
			$conds[] = $af->checkSQL();
			$fieldTableName = $af->filter->tableName;
			$fieldTableAlias = CargoUtils::makeDifferentAlias( $fieldTableName );
			$columnName = $af->filter->name;
			if ( $af->filter->fieldDescription->mIsList ) {
				$fieldTableName = $af->filter->tableName . '__' . $af->filter->name;
				$fieldTableAlias = $af->filter->tableAlias . '__' . $af->filter->name;
				if ( !array_key_exists( $fieldTableAlias, $joinConds ) ) {
					$tableNames[$fieldTableAlias] = $fieldTableName;
					$joinConds[$fieldTableAlias] =
						CargoUtils::joinOfMainAndFieldTable( $cdb,
							array( $af->filter->tableAlias => $af->filter->tableName ),
							array( $fieldTableAlias => $fieldTableName ) );
				}
				$columnName = '_value';
			}
			if ( $af->filter->fieldDescription->mIsHierarchy ) {
				$hierarchyTableName = $af->filter->tableName . '__' . $af->filter->name . '__hierarchy';
				$hierarchyTableAlias = $af->filter->tableAlias . '__' . $af->filter->name . '__hierarchy';
				if ( !array_key_exists( $hierarchyTableAlias, $joinConds ) ) {
					$tableNames[$hierarchyTableAlias] = $hierarchyTableName;
					$joinConds[$hierarchyTableAlias] =
						CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
							array( $fieldTableAlias => $fieldTableName ), $columnName, array(
								$hierarchyTableAlias => $hierarchyTableName,
							) );
				}
			}
		}
		return array( $tableNames, $conds, $joinConds );
	}

	/**
	 * Gets an array of the possible time period values (e.g., years,
	 * months) for this filter, and, for each one,
	 * the number of pages that match that time period.
	 *
	 * @param array $appliedFilters
	 * @return array
	 */
	function getTimePeriodValues( $fullTextSearchTerm, $appliedFilters, $mainTableAlias = null,
			$tableNames = array(), $joinConds = array() ) {
		$possible_dates = array();
		$date_field = $this->tableAlias . '.' . $this->name;
		list( $yearValue, $monthValue, $dayValue ) = CargoUtils::getDateFunctions( $date_field );
		$timePeriod = $this->getTimePeriod( $fullTextSearchTerm, $appliedFilters, $tableNames,
			$joinConds );

		$fields = array();
		$fields['year_field'] = $yearValue;
		if ( $timePeriod == 'month' || $timePeriod == 'day' ) {
			$fields['month_field'] = $monthValue;
		}
		if ( $timePeriod == 'day' ) {
			$fields['day_of_month_field'] = $dayValue;
		}

		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm,
			$appliedFilters, $tableNames, $joinConds );

		// Don't include imprecise date values in further filtering.
		if ( $timePeriod == 'month' ) {
			$conds[] = $date_field . '__precision <= ' . CargoStore::MONTH_ONLY;
		} elseif ( $timePeriod == 'day' ) {
			$conds[] = $date_field . '__precision <= ' . CargoStore::DATE_ONLY;
		}

		// We call array_values(), and not array_keys(), because
		// SQL Server can't group by aliases.
		$selectOptions = array( 'GROUP BY' => array_values( $fields ), 'ORDER BY' => array_values( $fields ) );
		$fields['total'] = "COUNT(DISTINCT {$mainTableAlias}._pageID)";

		$cdb = CargoUtils::getDB();

		$res = $cdb->select(
			$tableNames,
			$fields,
			$conds,
			__METHOD__,
			$selectOptions,
			$joinConds
		);

		while ( $row = $cdb->fetchRow( $res ) ) {
			$firstVal = current( $row ); // separate variable needed for PHP 5.3
			if ( empty( $firstVal ) ) {
				$possible_dates['_none'] = $row['total'];
			} elseif ( $timePeriod == 'day' ) {
				$date_string = CargoDrilldownUtils::monthToString( $row['month_field'] ) . ' ' . $row['day_of_month_field'] . ', ' . $row['year_field'];
				$possible_dates[$date_string] = $row['total'];
			} elseif ( $timePeriod == 'month' ) {
				$date_string = CargoDrilldownUtils::monthToString( $row['month_field'] ) . ' ' . $row['year_field'];
				$possible_dates[$date_string] = $row['total'];
			} elseif ( $timePeriod == 'year' ) {
				$date_string = $row['year_field'];
				$possible_dates[$date_string] = $row['total'];
			} else { // if ( $timePeriod == 'decade' )
				// Unfortunately, there's no SQL DECADE()
				// function - so we have to take these values,
				// which are grouped into year "buckets", and
				// re-group them into decade buckets.
				$year_string = $row['year_field'];
				$start_of_decade = $year_string - ( $year_string % 10 );
				$end_of_decade = $start_of_decade + 9;
				$decade_string = $start_of_decade . ' - ' . $end_of_decade;
				if ( !array_key_exists( $decade_string, $possible_dates ) ) {
					$possible_dates[$decade_string] = $row['total'];
				} else {
					$possible_dates[$decade_string] += $row['total'];
				}
			}
		}
		$cdb->freeResult( $res );
		return $possible_dates;
	}

	/**
	 * Gets an array of all values that this field has, and, for each
	 * one, the number of pages that match that value.
	 *
	 * @param array $appliedFilters
	 * @return array
	 */
	function getAllValues( $fullTextSearchTerm, $appliedFilters, $isApplied = false,
			$mainTableAlias = null, $tableNames = array(), $join_conds = array() ) {
		$cdb = CargoUtils::getDB();

		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm,
			$appliedFilters, $tableNames, $join_conds );
		if ( $this->fieldDescription->mIsList ) {
			$fieldTableName = $this->tableName . '__' . $this->name;
			$fieldTableAlias = $this->tableAlias . '__' . $this->name;
			if ( !array_key_exists( $fieldTableAlias, $joinConds ) ) {
				if ( !$isApplied ) {
					$tableNames[$fieldTableAlias] = $fieldTableName;
				}
				$joinConds[$fieldTableAlias] =
					CargoUtils::joinOfMainAndFieldTable( $cdb,
						array( $this->tableAlias => $this->tableName ), array(
							$fieldTableAlias => $fieldTableName,
						) );
			}
			$fieldName =
				CargoUtils::escapedFieldName( $cdb, array( $fieldTableAlias => $fieldTableName ),
					'_value' );
		} else {
			$fieldName =
				CargoUtils::escapedFieldName( $cdb, array( $this->tableAlias => $this->tableName ),
					$this->name );
		}
		if ( $isApplied ) {
			$conds = null;
		}
		$countClause = "COUNT(DISTINCT {$mainTableAlias}._pageID) AS total";
		$res = $cdb->select( $tableNames, array( "$fieldName AS value", $countClause ), $conds, null,
			array( 'GROUP BY' => $fieldName ), $joinConds );
		$possible_values = array();
		while ( $row = $cdb->fetchRow( $res ) ) {
			$value_string = $row['value'];
			if ( $value_string == '' ) {
				$value_string = ' none';
			}
			$possible_values[$value_string] = $row['total'];
		}
		$cdb->freeResult( $res );

		return $possible_values;
	}
}

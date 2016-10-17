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
	public $tableName;
	public $fieldType;
	public $fieldDescription;
	public $allowed_values;
	public $required_filters = array();
	public $possible_applied_filters = array();

	function __construct( $name, $tableName, $fieldDescription ) {
		$this->name = $name;
		$this->tableName = $tableName;
		$this->fieldDescription = $fieldDescription;
	}

	public function addRequiredFilter( $filterName ) {
		$this->required_filters[] = $filterName;
	}

	public function getTableName() {
		return $this->tableName;
	}

	/**
	 *
	 * @param array $appliedFilters
	 * @return string
	 */
	function getTimePeriod( $fullTextSearchTerm, $appliedFilters ) {
		// If it's not a date field, return null.
		if ( $this->fieldDescription->mType != 'Date' ) {
			return null;
		}

		$cdb = CargoUtils::getDB();
		$date_field = $this->name;
		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm, $appliedFilters );
		$res = $cdb->select( $tableNames, array( "MIN($date_field)", "MAX($date_field)" ), $conds, null,
			null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$minDate = $row[0];
		if ( is_null( $minDate ) ) {
			return null;
		}
		$minDateParts = explode( '-', $minDate );
		if ( count( $minDateParts ) == 3 ) {
			list( $minYear, $minMonth, $minDay ) = $minDateParts;
		} else {
			$minYear = $minDateParts[0];
			$minMonth = $minDay = 0;
		}
		$maxDate = $row[1];
		$maxDateParts = explode( '-', $maxDate );
		if ( count( $maxDateParts ) == 3 ) {
			list( $maxYear, $maxMonth, $maxDay ) = $maxDateParts;
		} else {
			$maxYear = $maxDateParts[0];
			$maxMonth = $maxDay = 0;
		}
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
	function getQueryParts( $fullTextSearchTerm, $appliedFilters ) {
		$cdb = CargoUtils::getDB();

		$tableNames = array( $this->tableName );
		$conds = array();
		$joinConds = array();
          
		if ( $fullTextSearchTerm != null ) {
			list( $curTableNames, $curConds, $curJoinConds ) =
				CargoDrilldownPage::getFullTextSearchQueryParts( $fullTextSearchTerm, $this->tableName, $this->searchableFiles );
			$tableNames = array_merge( $tableNames, $curTableNames );
			$conds = array_merge( $conds, $curConds );
			$joinConds = array_merge( $joinConds, $curJoinConds );
		}

		foreach ( $appliedFilters as $af ) {
			$conds[] = $af->checkSQL();
			if ( $af->filter->fieldDescription->mIsList ) {
				$fieldTableName = $this->tableName . '__' . $af->filter->name;
				$tableNames[] = $fieldTableName;
				$joinConds[$fieldTableName] = CargoUtils::joinOfMainAndFieldTable( $cdb, $this->tableName, $fieldTableName );
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
	function getTimePeriodValues( $fullTextSearchTerm, $appliedFilters ) {
		$possible_dates = array();
		$date_field = $this->name;
		$timePeriod = $this->getTimePeriod( $fullTextSearchTerm, $appliedFilters );

		if ( $timePeriod == 'day' ) {
			$fields = "YEAR($date_field), MONTH($date_field), DAYOFMONTH($date_field)";
		} elseif ( $timePeriod == 'month' ) {
			$fields = "YEAR($date_field), MONTH($date_field)";
		} elseif ( $timePeriod == 'year' ) {
			$fields = "YEAR($date_field)";
		} else { // if ( $timePeriod == 'decade' ) {
			$fields = "YEAR($date_field)";
		}

		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm, $appliedFilters );
		$selectOptions = array( 'GROUP BY' => $fields, 'ORDER BY' => $fields );
		$cdb = CargoUtils::getDB();
		$res = $cdb->select( $tableNames, array( $fields, 'COUNT(*)' ), $conds, null, $selectOptions,
			$joinConds );
		while ( $row = $cdb->fetchRow( $res ) ) {
			if ( $row[0] == null ) {
				$possible_dates['_none'] = $row['COUNT(*)'];
			} elseif ( $timePeriod == 'day' ) {
				$date_string = CargoDrilldownUtils::monthToString( $row[1] ) . ' ' . $row[2] . ', ' . $row[0];
				$possible_dates[$date_string] = $row[3];
			} elseif ( $timePeriod == 'month' ) {
				$date_string = CargoDrilldownUtils::monthToString( $row[1] ) . ' ' . $row[0];
				$possible_dates[$date_string] = $row[2];
			} elseif ( $timePeriod == 'year' ) {
				$date_string = $row[0];
				$possible_dates[$date_string] = $row[1];
			} else { // if ( $timePeriod == 'decade' )
				// Unfortunately, there's no SQL DECADE()
				// function - so we have to take these values,
				// which are grouped into year "buckets", and
				// re-group them into decade buckets.
				$year_string = $row[0];
				$start_of_decade = $year_string - ( $year_string % 10 );
				$end_of_decade = $start_of_decade + 9;
				$decade_string = $start_of_decade . ' - ' . $end_of_decade;
				if ( !array_key_exists( $decade_string, $possible_dates ) ) {
					$possible_dates[$decade_string] = $row[1];
				} else {
					$possible_dates[$decade_string] += $row[1];
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
	function getAllValues( $fullTextSearchTerm, $appliedFilters ) {
		$cdb = CargoUtils::getDB();

		list( $tableNames, $conds, $joinConds ) = $this->getQueryParts( $fullTextSearchTerm, $appliedFilters );
		if ( $this->fieldDescription->mIsList ) {
			$fieldTableName = $this->tableName . '__' . $this->name;
			$tableNames[] = $fieldTableName;
			$fieldName = CargoUtils::escapedFieldName( $cdb, $fieldTableName, '_value' );
			$joinConds[$fieldTableName] = CargoUtils::joinOfMainAndFieldTable( $cdb, $this->tableName, $fieldTableName );
		} else {
			$fieldName = $this->name;
		}

		$res = $cdb->select( $tableNames, array( $fieldName, 'COUNT(*)' ), $conds, null,
			array( 'GROUP BY' => $fieldName ), $joinConds );
		$possible_values = array();
		while ( $row = $cdb->fetchRow( $res ) ) {
			$value_string = $row[0];
			if ( $value_string == '' ) {
				$value_string = ' none';
			}
			$possible_values[$value_string] = $row[1];
		}
		$cdb->freeResult( $res );

		return $possible_values;
	}
}

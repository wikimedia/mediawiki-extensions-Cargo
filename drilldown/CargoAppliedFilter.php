<?php
/**
 * Defines a class, CargoAppliedFilter, that adds a value or a value range
 * onto a CargoFilter instance.
 *
 * Based heavily on SD_AppliedFilter.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoAppliedFilter {
	public $filter;
	public $values = array();
	public $search_terms;
	public $lower_date;
	public $upper_date;
	public $lower_date_string;
	public $upper_date_string;

	static function create( $filter, $values, $search_terms = null, $lower_date = null,
		$upper_date = null ) {
		$af = new CargoAppliedFilter();
		$af->filter = $filter;
		if ( $search_terms != null ) {
			$af->search_terms = array();
			foreach ( $search_terms as $search_term ) {
				$af->search_terms[] = htmlspecialchars( str_replace( '_', ' ', $search_term ) );
			}
		}
		if ( $lower_date != null ) {
			$af->lower_date = $lower_date;
			$af->lower_date_string = CargoDrilldownUtils::monthToString( $lower_date['month'] ) .
				" " . $lower_date['day'] . ", " . $lower_date['year'];
		}
		if ( $upper_date != null ) {
			$af->upper_date = $upper_date;
			$af->upper_date_string = CargoDrilldownUtils::monthToString( $upper_date['month'] ) .
				" " . $upper_date['day'] . ", " . $upper_date['year'];
		}
		if ( !is_array( $values ) ) {
			$values = array( $values );
		}
		foreach ( $values as $val ) {
			$filter_val = CargoFilterValue::create( $val, $filter );
			$af->values[] = $filter_val;
		}
		return $af;
	}

	/**
	 * Returns a string that adds a check for this filter/value
	 * combination to an SQL "WHERE" clause.
	 */
	function checkSQL() {
		$cdb = CargoUtils::getDB();

		if ( $this->filter->fieldDescription->mIsList ) {
			$fieldTableName = $this->filter->tableName . '__' . $this->filter->name;
			$value_field = $cdb->tableName( $fieldTableName ) . "._value";
		} else {
			$value_field = $this->filter->name;
		}
		$sql = "(";
		if ( $this->search_terms != null ) {
			$quoteReplace = ( $cdb->getType() == 'postgres' ? "''" : "\'");
			foreach ( $this->search_terms as $i => $search_term ) {
				$search_term = str_replace( "'", $quoteReplace, $search_term );
				if ( $i > 0 ) {
					$sql .= ' OR ';
				}
				if ( $this->filter->fieldType === 'page' ) {
					// FIXME: 'LIKE' is supposed to be
					// case-insensitive, but it's not acting
					// that way here.
					//$search_term = strtolower( $search_term );
					$search_term = str_replace( ' ', '\_', $search_term );
					$sql .= "$value_field LIKE '%{$search_term}%'";
				} else {
					//$search_term = strtolower( $search_term );
					$sql .= "$value_field LIKE '%{$search_term}%'";
				}
			}
		}
		if ( $this->lower_date != null ) {
			$date_string = $this->lower_date['year'] . "-" . $this->lower_date['month'] . "-" .
				$this->lower_date['day'];
			$sql .= "date($value_field) >= date('$date_string') ";
		}
		if ( $this->upper_date != null ) {
			if ( $this->lower_date != null ) {
				$sql .= " AND ";
			}
			$date_string = $this->upper_date['year'] . "-" . $this->upper_date['month'] . "-" .
				$this->upper_date['day'];
			$sql .= "date($value_field) <= date('$date_string') ";
		}
		foreach ( $this->values as $i => $fv ) {
			if ( $i > 0 ) {
				$sql .= " OR ";
			}
			if ( $fv->is_other ) {
				$checkNullOrEmptySql = "$value_field IS NULL " . ( $cdb->getType() == 'postgres' ? '' :
						"OR $value_field = '' ");
				$notOperatorSql = ( $cdb->getType() == 'postgres' ? "not" : "!" );
				$sql .= "($notOperatorSql ($checkNullOrEmptySql ";
				foreach ( $this->filter->possible_applied_filters as $paf ) {
					$sql .= " OR " . $paf->checkSQL();
				}
				$sql .= "))";
			} elseif ( $fv->is_none ) {
				$checkNullOrEmptySql = ( $cdb->getType() == 'postgres' ? '' : "$value_field = '' OR ") .
					"$value_field IS NULL";
				$sql .= "($checkNullOrEmptySql) ";
			} elseif ( $fv->is_numeric ) {
				if ( $fv->lower_limit && $fv->upper_limit ) {
					$sql .= "($value_field >= {$fv->lower_limit} AND $value_field <= {$fv->upper_limit}) ";
				} elseif ( $fv->lower_limit ) {
					$sql .= "$value_field > {$fv->lower_limit} ";
				} elseif ( $fv->upper_limit ) {
					$sql .= "$value_field < {$fv->upper_limit} ";
				}
			} elseif ( $this->filter->fieldDescription->mType == 'Date' ) {
				$date_field = $this->filter->name;
				if ( $fv->time_period == 'day' ) {
					$sql .= "YEAR($date_field) = {$fv->year} AND MONTH($date_field) = {$fv->month} "
						. "AND DAYOFMONTH($date_field) = {$fv->day} ";
				} elseif ( $fv->time_period == 'month' ) {
					$sql .= "YEAR($date_field) = {$fv->year} AND MONTH($date_field) = {$fv->month} ";
				} elseif ( $fv->time_period == 'year' ) {
					$sql .= "YEAR($date_field) = {$fv->year} ";
				} else { // if ( $fv->time_period == 'year range' ) {
					$sql .= "YEAR($date_field) >= {$fv->year} ";
					$sql .= "AND YEAR($date_field) <= {$fv->end_year} ";
				}
			} else {
				$value = $fv->text;
				$sql .= "$value_field = '{$cdb->strencode( $value )}'";
			}
		}
		$sql .= ")";
		return $sql;
	}

	/**
	 * Gets an array of all values that this filter has.
	 */
	function getAllOrValues() {
		$possible_values = array();
		if ( $this->filter->fieldDescription->mIsList ) {
			$tableName = $this->filter->tableName . '__' . $this->filter->name;
			$value_field = '_value';
		} else {
			$tableName = $this->filter->tableName;
			$value_field = $this->filter->name;
		}

		if ( $this->filter->fieldDescription->mType == 'Date' ) {
			// Is this necessary?
			$date_field = $value_field;
			if ( $this->filter->getTimePeriod() == 'month' ) {
				$value_field = "YEAR($date_field), MONTH($date_field)";
			} elseif ( $this->filter->getTimePeriod() == 'day' ) {
				$value_field = "YEAR($date_field), MONTH($date_field), DAYOFMONTH($date_field)";
			} elseif ( $this->filter->getTimePeriod() == 'year' ) {
				$value_field = "YEAR($date_field)";
			} else { // if ( $this->filter->getTimePeriod() == 'year range' ) {
				$value_field = "YEAR($date_field)";
			}
		}

		$cdb = CargoUtils::getDB();
		$res = $cdb->select( $tableName, "DISTINCT " . $value_field );
		while ( $row = $cdb->fetchRow( $res ) ) {
			if ( $this->filter->fieldDescription->mType == 'Date' &&
				$this->filter->getTimePeriod() == 'month' ) {
				$value_string = CargoDrilldownUtils::monthToString( $row[1] ) . " " . $row[0];
			} else {
				$value_string = $row[0];
			}
			$possible_values[] = $value_string;
		}
		$cdb->freeResult( $res );
		return $possible_values;
	}

}

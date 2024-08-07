<?php
/**
 * Defines a class, CargoFilterValue, representing a single value of an
 * applied filter (i.e., an instance of the CargoAppliedFilter class).
 *
 * Based heavily on the Semantic Drilldown extension's SDFilterValue class.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoFilterValue {
	public $text;
	public $is_none = false;
	public $is_other = false;
	public $is_numeric = false;
	public $lower_limit = null;
	public $upper_limit = null;
	public $year = null;
	public $month = null;
	public $day = null;
	public $end_year = null;
	public $time_period = null;

	/**
	 * @param string $actual_val
	 * @param CargoFilter|null $filter
	 * @return \CargoFilterValue
	 */
	public static function create( $actual_val, $filter = null ) {
		$fv = new CargoFilterValue();
		$fv->text = $actual_val;

		if ( $fv->text == '_none' ) {
			$fv->is_none = true;
		} elseif ( $fv->text == '_other' ) {
			$fv->is_other = true;
		}
		// Set other fields, if it's a date or number range.
		if ( $filter != null && $filter->fieldDescription->isDateOrDatetime() ) {
			// @TODO - this should ideally be handled via query
			// string arrays - and this code merged in with
			// date-range handling - instead of just doing string
			// parsing on one string.
			if ( strpos( $fv->text, ' - ' ) > 0 ) {
				// If there's a dash, assume it's a year range
				$years = explode( ' - ', $fv->text );
				$fv->year = $years[0];
				$fv->end_year = $years[1];
				$fv->time_period = 'year range';
			} else {
				$date_parts = explode( ' ', $fv->text );
				if ( count( $date_parts ) == 3 ) {
					[ $month_str, $day_str, $year ] = explode( ' ', $fv->text );
					$fv->month = CargoDrilldownUtils::stringToMonth( $month_str );
					$fv->day = str_replace( ',', '', $day_str );
					$fv->year = $year;
					$fv->time_period = 'day';
				} elseif ( count( $date_parts ) == 2 ) {
					[ $month_str, $year ] = explode( ' ', $fv->text );
					$fv->month = CargoDrilldownUtils::stringToMonth( $month_str );
					$fv->year = $year;
					$fv->time_period = 'month';
				} else {
					$fv->month = null;
					$fv->year = $fv->text;
					$fv->time_period = 'year';
				}
			}
		} else {
			$fvText = (string)$fv->text;
			if ( $fvText == '' ) {
				// do nothing
			} elseif ( $fvText[ 0 ] == '<' ) {
				$possible_number = str_replace( ',', '', trim( substr( $fvText, 1 ) ) );
				if ( is_numeric( $possible_number ) ) {
					$fv->upper_limit = $possible_number;
					$fv->is_numeric = true;
				}
			} elseif ( $fvText[ 0 ] == '>' ) {
				$possible_number = str_replace( ',', '', trim( substr( $fvText, 1 ) ) );
				if ( is_numeric( $possible_number ) ) {
					$fv->lower_limit = $possible_number;
					$fv->is_numeric = true;
				}
			} else {
				// We need special handling for the first
				// character, if it's a dash, to not mess up
				// the explode() call.
				$first_elem_is_negative = substr( $fvText, 0, 1 ) == '-';
				if ( $first_elem_is_negative ) {
					$fvText = substr( $fvText, 1 );
				}
				$elements = explode( '-', $fvText, 2 );
				if ( count( $elements ) == 2 ) {
					$first_elem = str_replace( ',', '', trim( $elements[0] ) );
					$second_elem = str_replace( ',', '', trim( $elements[1] ) );
					if ( is_numeric( $first_elem ) && is_numeric( $second_elem ) ) {
						if ( $first_elem_is_negative ) {
							$first_elem *= -1;
						}
						$fv->lower_limit = $first_elem;
						$fv->upper_limit = $second_elem;
						$fv->is_numeric = true;
					}
				}
			}
		}
		return $fv;
	}

	/**
	 * Used in sorting, when CargoSpecialDrilldown creates a new URL.
	 */
	public static function compare( $fv1, $fv2 ) {
		if ( $fv1->is_none ) {
			return 1;
		}
		if ( $fv2->is_none ) {
			return -1;
		}
		if ( $fv1->is_other ) {
			return 1;
		}
		if ( $fv2->is_other ) {
			return -1;
		}

		if ( $fv1->year != null && $fv2->year != null ) {
			if ( $fv1->year == $fv2->year ) {
				if ( $fv1->month == $fv2->month ) {
					return 0;
				}
				return ( $fv1->month > $fv2->month ) ? 1 : -1;
			}

			return ( $fv1->year > $fv2->year ) ? 1 : -1;
		}

		if ( $fv1->is_numeric ) {
			if ( $fv1->lower_limit == null ) {
				return -1;
			}
			return ( $fv1->lower_limit > $fv2->lower_limit ) ? 1 : -1;
		}

		if ( $fv1->text == $fv2->text ) {
			return 0;
		}

		return ( $fv1->text > $fv2->text ) ? 1 : -1;
	}

}

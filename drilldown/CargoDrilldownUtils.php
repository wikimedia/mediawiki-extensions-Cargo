<?php
/**
 * A class for static helper functions for the Cargo extension's
 * drill-down functionality.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDrilldownUtils {

	static function setGlobalJSVariables( &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";
		return true;
	}

	static function monthToString( $month ) {
		if ( $month == 1 ) {
			return wfMessage( 'january' )->text();
		} elseif ( $month == 2 ) {
			return wfMessage( 'february' )->text();
		} elseif ( $month == 3 ) {
			return wfMessage( 'march' )->text();
		} elseif ( $month == 4 ) {
			return wfMessage( 'april' )->text();
		} elseif ( $month == 5 ) {
			// Needed to avoid using 3-letter abbreviation
			return wfMessage( 'may_long' )->text();
		} elseif ( $month == 6 ) {
			return wfMessage( 'june' )->text();
		} elseif ( $month == 7 ) {
			return wfMessage( 'july' )->text();
		} elseif ( $month == 8 ) {
			return wfMessage( 'august' )->text();
		} elseif ( $month == 9 ) {
			return wfMessage( 'september' )->text();
		} elseif ( $month == 10 ) {
			return wfMessage( 'october' )->text();
		} elseif ( $month == 11 ) {
			return wfMessage( 'november' )->text();
		} else { // if ($month == 12) {
			return wfMessage( 'december' )->text();
		}
	}

	static function stringToMonth( $str ) {
		if ( $str == wfMessage( 'january' )->text() ) {
			return 1;
		} elseif ( $str == wfMessage( 'february' )->text() ) {
			return 2;
		} elseif ( $str == wfMessage( 'march' )->text() ) {
			return 3;
		} elseif ( $str == wfMessage( 'april' )->text() ) {
			return 4;
		} elseif ( $str == wfMessage( 'may_long' )->text() ) {
			return 5;
		} elseif ( $str == wfMessage( 'june' )->text() ) {
			return 6;
		} elseif ( $str == wfMessage( 'july' )->text() ) {
			return 7;
		} elseif ( $str == wfMessage( 'august' )->text() ) {
			return 8;
		} elseif ( $str == wfMessage( 'september' )->text() ) {
			return 9;
		} elseif ( $str == wfMessage( 'october' )->text() ) {
			return 10;
		} elseif ( $str == wfMessage( 'november' )->text() ) {
			return 11;
		} else { // if ($strmonth == wfMessage('december')->text()) {
			return 12;
		}
	}

}

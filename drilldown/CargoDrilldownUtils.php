<?php
/**
 * A class for static helper functions for the Cargo extension's
 * drill-down functionality.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDrilldownUtils {

	/**
	 * Appears to be unused
	 *
	 * @global string $cgScriptPath
	 * @param array $vars
	 * @return boolean
	 */
	static function setGlobalJSVariables( &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";
		return true;
	}

	/**
	 * Return the month represented by the given number.
	 *
	 * @global Language $wgLang
	 * @param int $month
	 * @return string Month name in user language
	 * @todo This function should be replaced with direct calls to Language::getMonthName()
	 */
	static function monthToString( $month ) {
		global $wgLang;
		if ( !is_int( $month ) || $month < 1 || $month > 12 ) {
			return false;
		}

		return $wgLang->getMonthName( $month );
	}

	/**
	 * Return the month number (1-12) which precisely matches the string sent in the user's language
	 *
	 * @global Language $wgLang
	 * @param string $str
	 * @param Language $language
	 * @return int|boolean
	 */
	static function stringToMonth( $str, Language $language = null ) {
		if ( $language === null ) {
			global $wgLang;
			$language = $wgLang;
		}

		return array_search( $str, $language->getMonthNamesArray() );
	}
}

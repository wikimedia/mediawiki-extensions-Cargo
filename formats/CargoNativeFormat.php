<?php
/**
 * @ingroup Cargo
 */

class CargoNativeFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array(  );
	}
	/**
	 *
	 * @param array $valuesTable
	 * @param array $formattedValuesTable Unused
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string
	 * @throws MWException
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		global $wgCargoQueryResults;
		$wgCargoQueryResults = $valuesTable;
		return '';
	}

}

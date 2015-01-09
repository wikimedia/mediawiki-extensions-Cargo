<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTemplateFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array( 'template' );
	}

	function displayRow( $templateName, $row, $fieldDescriptions ) {
		$wikiText = '{{' . $templateName;
		// We add the field number in to the template call to not
		// mess up values that contain '='.
		$fieldNum = 1;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( array_key_exists( $fieldName, $row ) ) {
				$wikiText .= '|' . $fieldNum . '=' . $row[$fieldName];
			}
			$fieldNum++;
		}
		$wikiText .= '}}' . "\n";
		return $wikiText;
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
		if ( !array_key_exists( 'template', $displayParams ) ) {
			throw new MWException( "Error: 'template' parameter must be set." );
		}

		$templateName = $displayParams['template'];
		$text = '';
		foreach ( $valuesTable as $row ) {
			$text .= $this->displayRow( $templateName, $row, $fieldDescriptions );
		}
		return CargoUtils::smartParse( $text, $this->mParser );
	}

}

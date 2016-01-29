<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTemplateFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array( 'template', 'named args' );
	}

	function displayRow( $templateName, $row, $fieldDescriptions, $namedArgs ) {
		$wikiText = '{{' . $templateName;
		// If we're not using named arguments, we add the field number
		// in to the template call, to not  mess up values that contain '='.
		$fieldNum = 1;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( array_key_exists( $fieldName, $row ) ) {
				$paramName = $namedArgs ? $fieldName : $fieldNum;
				$wikiText .= '|' . $paramName . '=' . $row[$fieldName];
				$fieldNum++;
			}
		}
		$wikiText .= '}}';
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
		$namedArgs = false;
		if ( array_key_exists( 'named args', $displayParams ) ) {
			$namedArgs = strtolower( $displayParams['named args'] ) == 'yes';
		}
		$text = '';
		foreach ( $valuesTable as $row ) {
			$text .= $this->displayRow( $templateName, $row, $fieldDescriptions, $namedArgs );
		}
		global $wgTitle;
		if ( $wgTitle != null && $wgTitle->isSpecialPage() ) {
			return CargoUtils::smartParse( $text, $this->mParser );
		} else {
			return $text;
		}
	}

}

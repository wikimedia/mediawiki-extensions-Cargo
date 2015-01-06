<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoEmbeddedFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array();
	}

	function displayRow( $row ) {
		$pageName = reset( $row );
		$wikiText =<<<END
<p style="font-size: small; text-align: right;">[[$pageName]]</p>
{{:$pageName}}


--------------

END;
		return $wikiText;
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$text = '';
		foreach ( $valuesTable as $row ) {
			$text .= $this->displayRow( $row );
		}
		return CargoUtils::smartParse( $text, $this->mParser );
	}

}

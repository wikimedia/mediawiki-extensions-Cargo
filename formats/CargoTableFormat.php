<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTableFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array();
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModuleStyles( 'ext.cargo.main' );

		$text = '<table class="cargoTable">';
		$text .= '<tr>';
		foreach( array_keys( $fieldDescriptions ) as $field ) {
			$text .= Html::rawElement( 'th', null, $field ) . "\n";
		}
		$text .= "</tr>\n";
		foreach ( $formattedValuesTable as $i => $row ) {
			$text .= "<tr>\n";
			foreach( array_keys( $fieldDescriptions ) as $field ) {
				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
				} else {
					$value = null;
				}
				$text .= Html::rawElement( 'td', null, $value ) . "\n";
			}
			$text .= "</tr>\n";
		}
		$text .= "</table>";
		return $text;
	}

}

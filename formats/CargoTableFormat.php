<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTableFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array();
	}

	/**
	 *
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams Unused
	 * @return string HTML
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModuleStyles( 'ext.cargo.main' );

		$text = '<table class="cargoTable sortable">';
		$text .= '<tr>';
		foreach ( array_keys( $fieldDescriptions ) as $field ) {
			if ( strpos( $field, 'Blank value ' ) === false ) {
				// We add a class to enable special CSS and/or
				// JS handling.
				$className = 'field_' . str_replace( ' ', '_', $field );
				$text .= Html::rawElement( 'th', array( 'class' => $className ), $field ) . "\n";
			}
		}
		$text .= "</tr>\n";
		foreach ( $formattedValuesTable as $row ) {
			$text .= "<tr>\n";
			foreach ( array_keys( $fieldDescriptions ) as $field ) {
				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
				} else {
					$value = null;
				}
				// We add a class to enable special CSS and/or
				// JS handling.
				$className = 'field_' . str_replace( ' ', '_', $field );
				$text .= Html::rawElement( 'td', array( 'class' => $className ), $value ) . "\n";
			}
			$text .= "</tr>\n";
		}
		$text .= "</table>";
		return $text;
	}

}

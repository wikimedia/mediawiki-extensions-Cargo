<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoListFormat extends CargoDisplayFormat {

	protected $undisplayedFields = array();

	function __construct( $output, $parser = null ) {
		parent::__construct( $output, $parser );
		$this->mOutput->addModules( 'ext.cargo.main' );
	}

	public static function allowedParameters() {
		return array(
			'delimiter' => array( 'type' => 'string', 'label' => wfMessage( 'cargo-viewdata-delimiterparam' ) )
		);
	}

	/**
	 *
	 * @param array $row
	 * @param array $fieldDescriptions
	 * @return string
	 */
	function displayRow( $row, $fieldDescriptions ) {
		$text = '';
		$startParenthesisAdded = false;
		$firstField = true;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !array_key_exists( $fieldName, $row ) ) {
				continue;
			}
			$fieldValue = $row[$fieldName];
			if ( trim( $fieldValue ) == '' ) {
				continue;
			}
			if ( $firstField ) {
				$text = $fieldValue;
				$firstField = false;
			} elseif ( in_array( $fieldName, $this->undisplayedFields ) ) {
				// Do nothing.
			} else {
				if ( !$startParenthesisAdded ) {
					$text .= ' (';
					$startParenthesisAdded = true;
				} else {
					$text .= ', ';
				}
				if ( empty( $fieldName ) || strpos( $fieldName, 'Blank value ' ) !== false ) {
					$text .= $fieldValue;
				} else {
					$text .= "<span class=\"cargoFieldName\">$fieldName:</span> $fieldValue";
				}
			}
		}
		if ( $startParenthesisAdded ) {
			$text .= ')';
		}
		return $text;
	}

	/**
	 *
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$text = '';
		$delimiter = ( array_key_exists( 'delimiter', $displayParams ) ) ?
			$displayParams['delimiter'] : wfMessage( 'comma-separator' )->text();
		foreach ( $formattedValuesTable as $i => $row ) {
			if ( $i > 0 ) {
				$text .= $delimiter . ' ';
			}
			$text .= $this->displayRow( $row, $fieldDescriptions );
		}
		return $text;
	}

}

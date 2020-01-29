<?php

/**
 * Represents a single row in the outline.
 */
class CargoOutlineRow {
	public $mOutlineFields;
	public $mDisplayFields;

	function __construct() {
		$this->mOutlineFields = [];
		$this->mDisplayFields = [];
	}

	function addOutlineFieldValues( $fieldName, $values, $formattedValues ) {
		$this->mOutlineFields[$fieldName] = [
			'unformatted' => $values,
			'formatted' => $formattedValues
		];
	}

	function addOutlineFieldValue( $fieldName, $value, $formattedValue ) {
		$this->mOutlineFields[$fieldName] = [
			'unformatted' => [ $value ],
			'formatted' => [ $formattedValue ]
		];
	}

	function addDisplayFieldValue( $fieldName, $value ) {
		$this->mDisplayFields[$fieldName] = $value;
	}

	function getOutlineFieldValues( $fieldName ) {
		if ( !array_key_exists( $fieldName, $this->mOutlineFields ) ) {
			throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $fieldName, "outline fields" )->parse() );
		}
		return $this->mOutlineFields[$fieldName]['unformatted'];
	}

	function getFormattedOutlineFieldValues( $fieldName ) {
		return $this->mOutlineFields[$fieldName]['formatted'];
	}
}

<?php

/**
 * CargoFieldDescription - holds the attributes of a single field as defined
 * in the #cargo_declare parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoFieldDescription {
	var $mType;
	var $mSize;
	var $mIsList = false;
	var $mDelimiter;
	var $mAllowedValues = null;
	var $mIsHidden = false;
	var $mOtherParams = array();

	/**
	 * Initializes from a string within the #cargo_declare function.
	 *
	 * @param string $fieldDescriptionStr
	 * @return \CargoFieldDescription
	 */
	static function newFromString( $fieldDescriptionStr ) {
		$fieldDescription = new CargoFieldDescription();

		if ( strpos( $fieldDescriptionStr, 'List' ) === 0 ) {
			$matches = array();
			$foundMatch = preg_match( '/List \((.*)\) of (.*)/', $fieldDescriptionStr, $matches );
			if ( !$foundMatch ) {
				// Return a true error message here?
				return null;
			}
			$fieldDescription->mIsList = true;
			$fieldDescription->mDelimiter = $matches[1];
			$fieldDescriptionStr = $matches[2];
		}

		// There may be additional parameters, in/ parentheses.
		$matches = array();
		$foundMatch2 = preg_match( '/(.*)\s*\((.*)\)/', $fieldDescriptionStr, $matches );
		if ( $foundMatch2 ) {
			$fieldDescriptionStr = trim( $matches[1] );
			$extraParamsString = $matches[2];
			$extraParams = explode( ';', $extraParamsString );
			foreach ( $extraParams as $extraParam ) {
				$extraParamParts = explode( '=', $extraParam, 2 );
				if ( count( $extraParamParts ) == 1 ) {
					$paramKey = trim( $extraParamParts[0] );
					$fieldDescription->mOtherParams[$paramKey] = true;
				} else {
					$paramKey = trim( $extraParamParts[0] );
					$paramValue = trim( $extraParamParts[1] );
					if ( $paramKey == 'allowed values' ) {
						$fieldDescription->mAllowedValues = array_map( 'trim', explode( ',', $paramValue ) );
					} elseif ( $paramKey == 'size' ) {
						$fieldDescription->mSize = $paramValue;
					} else {
						$fieldDescription->mOtherParams[$paramKey] = $paramValue;
					}
				}
			}
		}

		// What's left will be the type, hopefully.
		$fieldDescription->mType = $fieldDescriptionStr;

		return $fieldDescription;
	}

	/**
	 *
	 * @param array $descriptionData
	 * @return \CargoFieldDescription
	 */
	static function newFromDBArray( $descriptionData ) {
		$fieldDescription = new CargoFieldDescription();
		foreach ( $descriptionData as $param => $value ) {
			if ( $param == 'type' ) {
				$fieldDescription->mType = $value;
			} elseif ( $param == 'size' ) {
				$fieldDescription->mSize = $value;
			} elseif ( $param == 'isList' ) {
				$fieldDescription->mIsList = true;
			} elseif ( $param == 'delimiter' ) {
				$fieldDescription->mDelimiter = $value;
			} elseif ( $param == 'allowedValues' ) {
				$fieldDescription->mAllowedValues = $value;
			} elseif ( $param == 'hidden' ) {
				$fieldDescription->mIsHidden = true;
			}
		}
		return $fieldDescription;
	}

	/**
	 *
	 * @return array
	 */
	function toDBArray() {
		$descriptionData = array();
		$descriptionData['type'] = $this->mType;
		if ( $this->mSize != null ) {
			$descriptionData['size'] = $this->mSize;
		}
		if ( $this->mIsList ) {
			$descriptionData['isList'] = true;
		}
		if ( $this->mDelimiter != null ) {
			$descriptionData['delimiter'] = $this->mDelimiter;
		}
		if ( $this->mAllowedValues != null ) {
			$descriptionData['allowedValues'] = $this->mAllowedValues;
		}
		if ( $this->mIsHidden ) {
			$descriptionData['hidden'] = true;
		}
		foreach ( $this->mOtherParams as $otherParam => $value ) {
			$descriptionData[$otherParam] = $value;
		}

		return $descriptionData;
	}
}

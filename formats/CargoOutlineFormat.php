<?php

/**
 * A class to print query results in an outline format, along with some
 * helper classes to handle the aggregation
 *
 * Code is based heavily on the code for the 'outline' format in the
 * Semantic Result Formats extension.
 *
 * @author Yaron Koren
 */

/**
 * Represents a single row in the outline.
 */
class CargoOutlineRow {
	public $mOutlineFields;
	public $mDisplayFields;

	function __construct() {
		$this->mOutlineFields = array();
		$this->mDisplayFields = array();
	}

	function addOutlineFieldValues( $fieldName, $values, $formattedValues ) {
		$this->mOutlineFields[$fieldName] = array(
			'unformatted' => $values,
			'formatted' => $formattedValues
		);
	}

	function addOutlineFieldValue( $fieldName, $value, $formattedValue ) {
		$this->mOutlineFields[$fieldName] = array(
			'unformatted' => array( $value ),
			'formatted' => array( $formattedValue )
		);
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

/**
 * A tree structure for holding the outline data.
 */
class CargoOutlineTree {
	public $mTree;
	public $mUnsortedRows;
	public $mFormattedValue;

	function __construct( $rows = array(), $formattedValue = null ) {
		$this->mTree = array();
		$this->mUnsortedRows = $rows;
		$this->mFormattedValue = $formattedValue;
	}

	function addRow( $row ) {
		$this->mUnsortedRows[] = $row;
	}

	function categorizeRow( $vals, $row, $formattedVals ) {
		foreach ( $vals as $val ) {
			if ( array_key_exists( $val, $this->mTree ) ) {
				$this->mTree[$val]->mUnsortedRows[] = $row;
			} else {
				$formattedVal = reset( $formattedVals );
				$this->mTree[$val] = new CargoOutlineTree( array( $row ), $formattedVal );
			}
		}
	}

	function addField( $field ) {
		if ( count( $this->mUnsortedRows ) > 0 ) {
			foreach ( $this->mUnsortedRows as $row ) {
				$fieldValues = $row->getOutlineFieldValues( $field );
				$formattedFieldValues = $row->getFormattedOutlineFieldValues( $field );
				$this->categorizeRow( $fieldValues, $row, $formattedFieldValues );
			}
			$this->mUnsortedRows = array();
		} else {
			foreach ( $this->mTree as $i => $node ) {
				$this->mTree[$i]->addField( $field );
			}
		}
	}
}

class CargoOutlineFormat extends CargoListFormat {
	protected $mOutlineFields = array();
	public $mFieldDescriptions;

	function allowedParameters() {
		return array( 'outline fields' );
	}

	function printTree( $outlineTree, $level = 0 ) {
		$text = "";
		if ( !is_null( $outlineTree->mUnsortedRows ) ) {
			$text .= "<ul>\n";
			foreach ( $outlineTree->mUnsortedRows as $row ) {
				$text .= Html::rawElement( 'li', null,
					$this->displayRow( $row->mDisplayFields, $this->mFieldDescriptions ) ) . "\n";
			}
			$text .= "</ul>\n";
		}
		if ( $level > 0 ) {
			$text .= "<ul>\n";
		}
		$numLevels = count( $this->mOutlineFields );
		// Set font size and weight depending on level we're at.
		$fontLevel = $level;
		if ( $numLevels < 4 ) {
			$fontLevel += ( 4 - $numLevels );
		}
		if ( $fontLevel == 0 ) {
			$fontSize = 'x-large';
		} elseif ( $fontLevel == 1 ) {
			$fontSize = 'large';
		} elseif ( $fontLevel == 2 ) {
			$fontSize = 'medium';
		} else {
			$fontSize = 'small';
		}
		if ( $fontLevel == 3 ) {
			$fontWeight = 'bold';
		} else {
			$fontWeight = 'regular';
		}
		foreach ( $outlineTree->mTree as $node ) {
			$text .= Html::rawElement( 'p',
				array( 'style' =>
				"font-size: $fontSize; font-weight: $fontWeight;" ), $node->mFormattedValue ) . "\n";
			$text .= $this->printTree( $node, $level + 1 );
		}
		if ( $level > 0 ) {
			$text .= "</ul>\n";
		}
		return $text;
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( !array_key_exists( 'outline fields', $displayParams ) ) {
			throw new MWException( wfMessage( "cargo-query-missingparam", "outline fields", "outline" )->parse() );
		}
		$outlineFields = explode( ',', str_replace( '_', ' ', $displayParams['outline fields'] ) );
		$this->mOutlineFields = array_map( 'trim', $outlineFields );
		$this->mFieldDescriptions = $fieldDescriptions;

		// For each result row, create an array of the row itself
		// and all its sorted-on fields, and add it to the initial
		// 'tree'.
		$outlineTree = new CargoOutlineTree();
		foreach ( $valuesTable as $rowNum => $queryResultsRow ) {
			$coRow = new CargoOutlineRow();
			foreach ( $queryResultsRow as $fieldName => $value ) {
				$formattedValue = $formattedValuesTable[$rowNum][$fieldName];
				if ( in_array( $fieldName, $this->mOutlineFields ) ) {
					if ( array_key_exists( 'isList', $fieldDescriptions[$fieldName] ) ) {
						$delimiter = $fieldDescriptions[$fieldName]['delimiter'];
						$coRow->addOutlineFieldValues( $fieldName, array_map( 'trim', explode( $delimiter, $value ) ),
							array_map( 'trim', explode( $delimiter, $formattedValue ) ) );
					} else {
						$coRow->addOutlineFieldValue( $fieldName, $value, $formattedValue );
					}
				} else {
					$coRow->addDisplayFieldValue( $fieldName, $formattedValue );
				}
			}
			$outlineTree->addRow( $coRow );
		}

		// Now, cycle through the outline fields, creating the tree.
		foreach ( $this->mOutlineFields as $outlineField ) {
			$outlineTree->addField( $outlineField );
		}
		$result = $this->printTree( $outlineTree );

		return $result;
	}

}

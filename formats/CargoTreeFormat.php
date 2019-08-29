<?php

/**
 * Classes to print query results in a "tree" display, using a field that
 * defines a "parent" relationship between rows.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTreeFormatNode {

	private $mParent;
	private $mChildren = array();
	private $mValues = array();

	public function getParent() {
		return $this->mParent;
	}

	public function setParent( $parent ) {
		$this->mParent = $parent;
	}

	public function getChildren() {
		return $this->mChildren;
	}

	public function addChild( $child ) {
		$this->mChildren[] = $child;
	}

	public function getValues() {
		return $this->mValues;
	}

	public function setValues( $values ) {
		$this->mValues = $values;
	}
}

class CargoTreeFormatTree {

	private $mNodes = array();

	public function getNodes() {
		return $this->mNodes;
	}

	public function getNode( $nodeName ) {
		return $this->mNodes[$nodeName];
	}

	/**
	 *
	 * @param string $nodeName
	 * @param string $parentName
	 * @param array $nodeValues
	 * @throws MWException
	 */
	function addNode( $nodeName, $parentName, $nodeValues ) {
		// Add node for child, if it's not already there.
		if ( array_key_exists( $nodeName, $this->mNodes ) ) {
			// Make sure it doesn't have more than one parent.
			$existingParent = $this->mNodes[$nodeName]->getParent();
			if ( $existingParent != null && $existingParent != $parentName ) {
				throw new MWException( "The value \"$nodeName\" cannot have more than one parent "
				. "defined for it" );
			}
		} else {
			$this->mNodes[$nodeName] = new CargoTreeFormatNode();
		}
		$this->mNodes[$nodeName]->setParent( $parentName );
		$this->mNodes[$nodeName]->setValues( $nodeValues );

		// Add node for parent, if it's not already there
		if ( !array_key_exists( $parentName, $this->mNodes ) ) {
			$this->mNodes[$parentName] = new CargoTreeFormatNode();
		}
		$this->mNodes[$parentName]->addChild( $nodeName );
	}

}

class CargoTreeFormat extends CargoListFormat {
	protected $mParentField = null;
	public $mFieldDescriptions;

	public static function allowedParameters() {
		return array( 'parent field' => array( 'type' => 'string' ) );
	}

	/**
	 *
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 * @throws MWException
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( !array_key_exists( 'parent field', $displayParams ) ) {
			throw new MWException( wfMessage( "cargo-query-missingparam", "parent field", "tree" )->parse() );
		}
		$this->mParentField = str_replace( '_', ' ', trim( $displayParams['parent field'] ) );
		$this->mFieldDescriptions = $fieldDescriptions;

		// Early error-checking.
		if ( !array_key_exists( $this->mParentField, $fieldDescriptions ) ) {
			throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $this->mParentField, "parent field" )->parse() );
		}
		if ( array_key_exists( 'isList', $fieldDescriptions[$this->mParentField] ) ) {
			throw new MWException( "Error: 'parent field' is declared to hold a list of values; "
			. "only one parent value is allowed for the 'tree' format." );
		}

		// For each result row, get the main name, the parent value,
		// and all additional display values, and add it to the tree.
		$tree = new CargoTreeFormatTree();
		foreach ( $formattedValuesTable as $queryResultsRow ) {
			$name = null;
			$parentName = null;
			$values = array();
			foreach ( $queryResultsRow as $fieldName => $value ) {
				if ( $name == null ) {
					$name = $value;
				}
				if ( $fieldName == $this->mParentField ) {
					$parentName = $value;
				} else {
					$values[$fieldName] = $value;
				}
			}
			$tree->addNode( $name, $parentName, $values );
		}

		$result = self::printTree( $tree );
		return $result;
	}

	function printNode( $tree, $nodeName, $level ) {
		$node = $tree->getNode( $nodeName );
		$text = str_repeat( '*', $level );
		if ( $level == 1 ) {
			$text .= "$nodeName\n";
		} else {
			$text .= $this->displayRow( $node->getValues(), $this->mFieldDescriptions ) . "\n";
		}
		foreach ( $node->getChildren() as $childName ) {
			$text .= $this->printNode( $tree, $childName, $level + 1 );
		}
		return $text;
	}

	function printTree( $tree ) {
		// Print subtree for each top-level node.
		$text = '';
		foreach ( $tree->getNodes() as $nodeName => $node ) {
			if ( $node->getParent() == null ) {
				$text .= $this->printNode( $tree, $nodeName, 1 );
			}
		}
		return $text;
	}
}

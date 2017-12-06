<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTableFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array( 'merge similar cells' );
	}

	/**
	 * Creates helper data structures that make merging cells
	 * easier, if it's going to be done.
	 */
	function getHelperDataForMerging( $formattedValuesTable ) {
		$duplicateValuesInTable = array();
		$blankedCells = array();
		$numRows = count( $formattedValuesTable );
		foreach ( $formattedValuesTable as $rowNum => $row ) {
			foreach ( $row as $columnNum => $value ) {
				if ( strpos( $columnNum, '__' ) !== false ) {
					continue;
				}
				if ( array_key_exists( $rowNum, $blankedCells ) && in_array( $columnNum, $blankedCells[$rowNum] ) ) {
					continue;
				}
				$numMatches = 0;
				$nextRowNum = $rowNum;
				while (
					( ++$nextRowNum < $numRows ) &&
					( $formattedValuesTable[$nextRowNum][$columnNum] == $value )
				) {
					$numMatches++;
					if ( !array_key_exists( $nextRowNum, $blankedCells ) ) {
						$blankedCells[$nextRowNum] = array();
					}
					$blankedCells[$nextRowNum][] = $columnNum;
				}
				if ( $numMatches > 0 ) {
					if ( !array_key_exists( $rowNum, $duplicateValuesInTable ) ) {
						$duplicateValuesInTable[$rowNum] = array();
					}
					$duplicateValuesInTable[$rowNum][$columnNum] = $numMatches + 1;
				}
			}
		}

		return array( $duplicateValuesInTable, $blankedCells );
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
		$this->mOutput->addModules( 'ext.cargo.main' );

		$mergeSimilarCells = false;
		if ( array_key_exists( 'merge similar cells', $displayParams ) ) {
			$mergeSimilarCells = strtolower( $displayParams['merge similar cells'] ) == 'yes';
		}

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

		if ( $mergeSimilarCells ) {
			list( $duplicateValuesInTable, $blankedCells ) = $this->getHelperDataForMerging( $formattedValuesTable );
		}

		$columnIsOdd = array();

		foreach ( $formattedValuesTable as $rowNum => $row ) {
			$text .= "<tr>\n";
			foreach ( array_keys( $fieldDescriptions ) as $field ) {
				if (
					$mergeSimilarCells &&
					array_key_exists( $rowNum, $blankedCells ) &&
					in_array( $field, $blankedCells[$rowNum] )
				) {
					continue;
				}

				if ( !array_key_exists( $field, $columnIsOdd ) ) {
					$columnIsOdd[$field] = true;
				}

				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
				} else {
					$value = null;
				}

				// We add a unique class to enable special CSS and/or
				// JS handling, as well as a class to indicate whether
				// this is an odd or even row - unfortunately, the
				// possible presence of merged cells means that we
				// can't use the standard "nth-child" CSS approach.
				$className = 'field_' . str_replace( ' ', '_', $field );
				if ( $columnIsOdd[$field] ) {
					$className .= ' odd';
					$columnIsOdd[$field] = false;
				} else {
					$className .= ' even';
					$columnIsOdd[$field] = true;
				}
				$attrs = array( 'class' => $className );
				if (
					$mergeSimilarCells &&
					array_key_exists( $rowNum, $duplicateValuesInTable ) &&
					array_key_exists( $field, $duplicateValuesInTable[$rowNum] )
				) {
					$attrs['rowspan'] = $duplicateValuesInTable[$rowNum][$field];
				}
				$text .= Html::rawElement( 'td', $attrs, $value ) . "\n";
			}
			$text .= "</tr>\n";
		}
		$text .= "</table>";
		return $text;
	}

}

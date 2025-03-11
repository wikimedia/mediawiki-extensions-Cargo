<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class CargoTableFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [
			'merge similar cells' => [ 'type' => 'boolean' ],
			'edit link' => [ 'type' => 'boolean' ],
		];
	}

	/**
	 * Creates helper data structures that make merging cells
	 * easier, if it's going to be done.
	 */
	private function getHelperDataForMerging( $formattedValuesTable ) {
		$duplicateValuesInTable = [];
		$blankedCells = [];
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
						$blankedCells[$nextRowNum] = [];
					}
					$blankedCells[$nextRowNum][] = $columnNum;
				}
				if ( $numMatches > 0 ) {
					if ( !array_key_exists( $rowNum, $duplicateValuesInTable ) ) {
						$duplicateValuesInTable[$rowNum] = [];
					}
					$duplicateValuesInTable[$rowNum][$columnNum] = $numMatches + 1;
				}
			}
		}

		return [ $duplicateValuesInTable, $blankedCells ];
	}

	/**
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams Unused
	 * @return string HTML
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModules( [ 'ext.cargo.main', 'jquery.tablesorter' ] );
		$this->mOutput->addModuleStyles( [ 'jquery.tablesorter.styles' ] );

		$mergeSimilarCells = false;
		if ( array_key_exists( 'merge similar cells', $displayParams ) ) {
			$mergeSimilarCells = strtolower( $displayParams['merge similar cells'] ) == 'yes';
		}
		$editLink = false;
		$linkRenderer = null;
		if ( array_key_exists( 'edit link', $displayParams ) ) {
			$editLink = strtolower( $displayParams['edit link'] ) == 'yes';
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		}

		$tableClass = 'cargoTable';
		if ( $mergeSimilarCells ) {
			$tableClass .= ' mergeSimilarCells';
		} else {
			$tableClass .= ' noMerge sortable';
		}

		$text = "<table class=\"$tableClass\">";
		$text .= '<thead><tr>';
		foreach ( array_keys( $fieldDescriptions ) as $field ) {
			if ( strpos( $field, 'Blank value ' ) === false ) {
				// We add a class to enable special CSS and/or
				// JS handling.
				$className = 'field_' . str_replace( ' ', '_', $field );
				$text .= Html::rawElement( 'th', [ 'class' => $className ], $field ) . "\n";
			}
		}
		$text .= "</tr></thead>\n<tbody>";

		if ( $mergeSimilarCells ) {
			[ $duplicateValuesInTable, $blankedCells ] = $this->getHelperDataForMerging( $formattedValuesTable );
		}

		$columnIsOdd = [];

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

				// Add a unique class to enable special CSS
				// and/or JS handling.
				$className = 'field_' . str_replace( ' ', '_', $field );

				if ( $mergeSimilarCells ) {
					// If there are merged cells, we can't
					// use the standard "nth-child" CSS
					// approach, so add a class to indicate
					// whether this is an odd or even row.
					if ( $columnIsOdd[$field] ) {
						$className .= ' odd';
						$columnIsOdd[$field] = false;
					} else {
						$className .= ' even';
						$columnIsOdd[$field] = true;
					}
				}

				$attrs = [ 'class' => $className ];
				if (
					$mergeSimilarCells &&
					array_key_exists( $rowNum, $duplicateValuesInTable ) &&
					array_key_exists( $field, $duplicateValuesInTable[$rowNum] )
				) {
					$attrs['rowspan'] = $duplicateValuesInTable[$rowNum][$field];
				}

				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
					if ( $fieldDescriptions[$field]->isDateOrDatetime() ) {
						$attrs['data-sort-value'] = $valuesTable[$rowNum][$field];
					}
				} else {
					$value = null;
				}

				$valueText = $value == null ? '' : $value;

				// Cargo reuses the "Page" system message of the main namespace
				$pageMsg = wfMessage( "nstab-main" )->text();
				if ( ( $field == "Page" || $field == $pageMsg || $field == '_pageName' ) &&
				$editLink ) {
					$rawValue = $valuesTable[$rowNum][$field];
					$title = Title::newFromText( $rawValue );
					if ( $title === null ) {
						continue;
					}
					$link = CargoUtils::makeLink(
						$linkRenderer, $title, wfMessage( 'editold' ), [], [ 'action' => 'edit', ]
					);
					$valueText .= ' (' . $link . ')';
				}

				$text .= Html::rawElement( 'td', $attrs, $valueText ) . "\n";
			}
			$text .= "</tr>\n";
		}
		$text .= "</tbody></table>";
		return $text;
	}

}

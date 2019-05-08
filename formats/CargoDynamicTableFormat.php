<?php
/**
 * Defines a "dynamic table" format, that displays query results in a
 * JavaScript-based table that has sorting, pagination and searching, using
 * the DataTables JS library.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDynamicTableFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array( 'rows per page', 'hidden fields' );
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
		$this->mOutput->addModules( 'ext.cargo.datatables' );

		$detailsFields = array();
		$detailsFieldsString = '';
		if ( array_key_exists( 'details fields', $displayParams ) ) {
			$detailsFields = array_map( 'trim', explode( ',', $displayParams['details fields'] ) );
			$detailsFieldsString = "data-details-fields=1";
		}
		// Special handlng for ordering.
		$dataOrderString = null;
		if ( array_key_exists( 'order by', $displayParams ) ) {
			$dataTableOrderByParams = array();
			$orderByClauses = explode( ',', $displayParams['order by'] );
			foreach ( $orderByClauses as $orderByClause ) {
				$orderByClause = strtolower( trim( $orderByClause ) );
				$sortAscending = true;
				if ( substr( $orderByClause, -4 ) === ' asc' ) {
					$orderByClause = trim( substr( $orderByClause, 0, -3 ) );
				} elseif ( substr( $orderByClause, -5 ) === ' desc' ) {
					$sortAscending = false;
					$orderByClause = trim( substr( $orderByClause, 0, -4 ) );
				}
				if ( $detailsFields ) {
					$i = 1;
				} else {
					$i = 0;
				}
				foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
					if ( in_array( $fieldName, $detailsFields ) ) {
						continue;
					}
					$fieldName = strtolower( str_replace( ' ', '_', $fieldName ) );
					if ( $orderByClause == $fieldName ) {
						$dataTableOrderByParams[] = array( $i, $sortAscending ? 'asc' : 'desc' );
					}
					$i++;
				}
			}
			if ( count( $dataTableOrderByParams ) > 0 ) {
				// We have to set the text in this awkward way,
				// instead of using the Html class, because it
				// has to be displayed in a very specific way -
				// single quotes outside, double quotes inside -
				// for the jQuery part to work, and the Html
				// class won't do it that way.
				$dataOrderString = "data-order='" . json_encode( $dataTableOrderByParams ) . "'";
			}
		}

		if ( array_key_exists( 'rows per page', $displayParams ) ) {
			// See $dataOrderString above for why it's done this way.
			$pageLengthString = 'data-page-length="' . $displayParams['rows per page'] . '"';
		} else {
			$pageLengthString = '';
		}
		$text = '';
		if ( array_key_exists( 'hidden fields', $displayParams ) ) {
			$hiddenFields = array_map( 'trim', explode( ',', $displayParams['hidden fields'] ) );
			$text .= wfMessage( 'cargo-dynamictables-togglecolumns' )->text() . ' ';
			$matchFound = 0;
			foreach ( $hiddenFields as $hiddenField ) {
				if ( $detailsFields ) {
					$fieldNum = 1;
				} else {
					$fieldNum = 0;
				}
				foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
					if ( in_array( $fieldName, $detailsFields ) ) {
						continue;
					}
					if ( $hiddenField == $fieldName ) {
						if ( $matchFound++ > 0 ) {
							$text .= ' - ';
						}
						$text .= Html::element( 'a', array(
							'class' => 'toggle-vis',
							'data-column' => $fieldNum,
						), $hiddenField );
						break;
					}
					$fieldNum++;
				}
			}
		}
		$searchableColumns = false;
		if ( array_key_exists( 'searchable columns', $displayParams ) ) {
			$searchableColumns = strtolower( $displayParams['searchable columns'] ) == 'yes';
		}

		$text .= <<<END
	<table class="cargoDynamicTable display" cellspacing="0" width="100%" $detailsFieldsString $dataOrderString $pageLengthString>
		<thead>
			<tr>

END;
		if ( $detailsFields ) {
			$text .= Html::rawElement( 'th', array( 'class' => 'details-control' ), null );
		}
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( in_array( $fieldName, $detailsFields ) ) {
				continue;
			}
			if ( strpos( $fieldName, 'Blank value ' ) === false ) {
				$text .= "\t\t\t\t" . Html::element( 'th', null, $fieldName );
			} else {
				$text .= "\t\t\t\t" . Html::element( 'th', null, null );
			}
		}

		$text .= <<<END
			</tr>
		</thead>

		<tfoot>
			<tr>

END;

		if ( $detailsFields ) {
			$text .= Html::rawElement( 'th', array( 'class' => 'details-control' ), null );
		}
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( in_array( $fieldName, $detailsFields ) ) {
				continue;
			}
			if ( $searchableColumns ) {
				$placeholder = wfMessage( 'cargo-dynamictables-searchcolumn', $fieldName )->parse();
				$attribs = array( 'data-placeholder' => $placeholder );
			} else {
				$attribs = null;
			}
			if ( strpos( $fieldName, 'Blank value ' ) === false ) {
				$text .= "\t\t\t\t" . Html::element( 'th', $attribs, $fieldName );
			} else {
				$text .= "\t\t\t\t" . Html::element( 'th', $attribs, null );
			}
		}

		$text .= <<<END
			</tr>
		</tfoot>

		<tbody>

END;

		foreach ( $formattedValuesTable as $rowNum => $row ) {
			if ( $detailsFields ) {
				$tableData = Html::rawElement( 'td', array( 'class' => 'details-control' ), null );
			} else {
				$tableData = '';
			}
			$details = '';
			foreach ( $fieldDescriptions as $field => $fieldDescription ) {
				$attribs = null;
				$value = null;

				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
					if ( $fieldDescription->isDateOrDatetime() ) {
						$attribs = array( 'data-order' => $valuesTable[$rowNum][$field] );
					}
				}

				if ( in_array( $field, $detailsFields ) ) {
					$detailsText = "\t\t\t\t" . Html::rawElement( 'td', $attribs, "<strong>$field: </strong>" );
					$detailsText .= "\t\t\t\t" . Html::rawElement( 'td', $attribs, $value );
					$details .= "\t\t\t" . Html::rawElement( 'tr', $attribs,  $detailsText );
				} else {
					$tableData .= "\t\t\t\t" . Html::rawElement( 'td', $attribs, $value );
				}
			}
			$detailsTable =
				Html::rawElement( 'table', array( 'border' => '0', 'cellspacing' => '0' ),
					Html::rawElement( 'tbody', null, $details ) );

			$text .= Html::rawElement( 'tr', array( 'data-details' => $detailsTable ),
				$tableData );
		}

		$text .= <<<END
		</tbody>
	</table>

END;

		return $text;
	}

}

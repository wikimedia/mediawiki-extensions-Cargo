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
		$this->mOutput->addModules( 'ext.cargo.datatables' );

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
				foreach ( array_keys( $fieldDescriptions ) as $i => $fieldName ) {
					$fieldName = strtolower( str_replace( ' ', '_', $fieldName ) );
					if ( $orderByClause == $fieldName ) {
						$dataTableOrderByParams[] = array( $i, $sortAscending ? 'asc' : 'desc' );
					}
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

		$text = <<<END
	<table class="cargoDynamicTable display" cellspacing="0" width="100%" $dataOrderString>
		<thead>
			<tr>

END;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( strpos( $fieldName, 'Blank value ' ) === false ) {
				$text .= "\t\t\t\t" . Html::element( 'th', null, $fieldName );
			}
			else {
				$text .= "\t\t\t\t" . Html::element( 'th', null, null );
			}
		}

		$text .=<<<END
			</tr>
		</thead>

		<tfoot>
			<tr>

END;

		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( strpos( $fieldName, 'Blank value ' ) === false ) {
				$text .= "\t\t\t\t" . Html::element( 'th', null, $fieldName );
			}
			else {
				$text .= "\t\t\t\t" . Html::element( 'th', null, null );
			}
		}

		$text .=<<<END
			</tr>
		</tfoot>

		<tbody>

END;

		foreach ( $formattedValuesTable as $row ) {
			$text .= "\t\t\t<tr>\n";
			foreach ( array_keys( $fieldDescriptions ) as $field ) {
				if ( array_key_exists( $field, $row ) ) {
					$value = $row[$field];
				} else {
					$value = null;
				}
				$text .= "\t\t\t\t" . Html::rawElement( 'td', null, $value ); 
			}
			$text .= "\t\t\t</tr>\n";
		}

		$text .=<<<END
		</tbody>
	</table>

END;

		return $text;
	}

}

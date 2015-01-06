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

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModules( 'ext.cargo.datatables' );

		$text =<<<END
	<table class="cargoDynamicTable display" cellspacing="0" width="100%">
		<thead>
			<tr>

END;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$text .= "\t\t\t\t" . Html::element( 'th', null, $fieldName );
		}

		$text .=<<<END
			</tr>
		</thead>

		<tfoot>
			<tr>

END;

		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$text .= "\t\t\t\t" . Html::element( 'th', null, $fieldName );
		}

		$text .=<<<END
			</tr>
		</tfoot>

		<tbody>

END;

		foreach ( $formattedValuesTable as $row ) {
			$text .= "\t\t\t<tr>\n";
			foreach ( $row as $value ) {
				$text .= "\t\t\t\t<td>$value</td>\n";
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

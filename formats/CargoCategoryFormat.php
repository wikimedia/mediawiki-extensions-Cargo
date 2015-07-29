<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCategoryFormat extends CargoListFormat {

	function allowedParameters() {
		return array( 'columns' );
	}

	/**
	 *
	 * @global Language $wgContLang
	 * @param array $valuesTable
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		global $wgContLang;

		if ( array_key_exists( 'columns', $displayParams ) ) {
			$numColumns = max( $displayParams['columns'], 1 );
		} else {
			$numColumns = 3;
		}

		$result = '';
		$num = count( $valuesTable );

		$prev_first_char = "";
		$rows_per_column = ceil( $num / $numColumns );
		// column width is a percentage
		$column_width = floor( 100 / $numColumns );

		// Print all result rows:
		$rowindex = 0;

		foreach ( $formattedValuesTable as $i => $row ) {
//print_r($row);die;
			$content = reset( $valuesTable[$i] );

			$cur_first_char = $wgContLang->firstChar( $content );

			if ( $rowindex % $rows_per_column == 0 ) {
				$result .= "\n			<div style=\"float: left; width: $column_width%;\">\n";
				if ( $cur_first_char == $prev_first_char ) {
					$result .= "				<h3>$cur_first_char " .
						wfMessage( 'listingcontinuesabbrev' )->text() . "</h3>\n				<ul>\n";
				}
			}

			// if we're at a new first letter, end
			// the last list and start a new one
			if ( $cur_first_char != $prev_first_char ) {
				if ( $rowindex % $rows_per_column > 0 ) {
					$result .= "				</ul>\n";
				}
				$result .= "				<h3>$cur_first_char</h3>\n				<ul>\n";
			}
			$prev_first_char = $cur_first_char;

			$result .= '<li>' . self::displayRow( $row, $fieldDescriptions ) . "</li>\n";

			// end list if we're at the end of the column
			// or the page
			if ( ( $rowindex + 1 ) % $rows_per_column == 0 && ( $rowindex + 1 ) < $num ) {
				$result .= "				</ul>\n			</div> <!-- end column -->";
			}

			$rowindex++;
		}

		$result .= "</ul>\n</div> <!-- end column -->";
		// clear all the CSS floats
		$result .= "\n" . '<br style="clear: both;"/>';

		// <H3> will generate TOC entries otherwise. Probably need another way
		// to accomplish this -- user might still want TOC for other page content.
		//$result .= '__NOTOC__';
		return $result;
	}

}

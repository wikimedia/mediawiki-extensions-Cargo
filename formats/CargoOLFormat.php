<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoOLFormat extends CargoListFormat {

	function allowedParameters() {
		return array( 'columns' );
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( array_key_exists( 'columns', $displayParams ) ) {
			$numColumns = max( $displayParams['columns'], 1 );
		} else {
			$numColumns = 1;
		}
		if ( array_key_exists( 'offset', $displayParams ) ) {
			$offset = $displayParams['offset'];
		} else {
			$offset = 0;
		}
		$text = '';
		foreach ( $formattedValuesTable as $i => $row ) {
			$text .= Html::rawElement( 'li', null, $this->displayRow( $row, $fieldDescriptions ) ) . "\n";
		}
		$olAttribs = array( 'start' => $offset + 1 );
		if ( $numColumns > 1 ) {
			$olAttribs['style'] = "margin-top: 0;";
		}
		$text = Html::rawElement( 'ol', $olAttribs, $text );
		if ( $numColumns > 1 ) {
			$text = Html::rawElement( 'div', array( 'style' => "-webkit-column-count: 3; -moz-column-count: 3; column-count: 3;" ), $text );
		}
		return $text;
	}

}

<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoULFormat extends CargoListFormat {

	public static function allowedParameters() {
		return array( 'columns' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-columnsparam' )->parse() ) );
	}

	/**
	 *
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 */
	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( array_key_exists( 'columns', $displayParams ) ) {
			$numColumns = max( $displayParams['columns'], 1 );
		} else {
			$numColumns = 1;
		}

		$text = '';
		foreach ( $formattedValuesTable as $i => $row ) {
			$text .= Html::rawElement( 'li', null, $this->displayRow( $row, $fieldDescriptions ) ) . "\n";
		}
		$ulAttribs = array();
		if ( $numColumns > 1 ) {
			$ulAttribs['style'] = "margin-top: 0;";
		}
		$text = Html::rawElement( 'ul', $ulAttribs, $text );
		if ( $numColumns > 1 ) {
			$text = Html::rawElement( 'div',
					array( 'style' =>
					"-webkit-column-count: $numColumns; -moz-column-count: $numColumns; column-count: $numColumns;" ), $text );
		}
		return $text;
	}

}

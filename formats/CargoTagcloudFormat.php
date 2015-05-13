<?php
/**
 * @author Cindy Cicalese
 * @ingroup Cargo
 */

class CargoTagcloudFormat extends CargoDisplayFormat {

	function allowedParameters() {
		return array(
			'template',
			'min size', // size of the smallest tags in percent (default 80)
			'max size' // size of the biggest tags in percent (default 200)
		);
	}

	/**
	 *
	 * @param array $valuesTagcloud Unused
	 * @param array $formattedValuesTagcloud
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 */
	function display( $valuesTagcloud, $formattedValuesTagcloud, $fieldDescriptions, $displayParams ) {

		$this->mOutput->addModuleStyles( 'ext.cargo.main' );

		if ( count( $fieldDescriptions ) < 2 ) {
			return '';
		}

		$fieldNames = array_keys( $fieldDescriptions );
		$tagFieldName = $fieldNames[0];
		$countFieldName = $fieldNames[1];

		if ( $fieldDescriptions[$countFieldName]->mType != 'Integer' ) {
			return '';
		}

		$tags = array();

		foreach ( $formattedValuesTagcloud as $row ) {

			$tag = $row[$tagFieldName];
			$count = $row[$countFieldName];

			if ( strlen( $tag ) > 0 && is_numeric( $count ) && $count > 0 ) {

				$tags[$tag] = $count;

			}

		}

		if ( $tags == array() ) {
			return '';
		}

		if ( isset( $displayParams['max size'] ) ) {
			$maxSize = $displayParams['max size'];
		} else {
			$maxSize = 200;
		}

		if ( isset( $displayParams['min size'] ) ) {
			$minSize = $displayParams['min size'];
		} else {
			$minSize = 80;
		}

		$maxSizeIncrease = $maxSize - $minSize;

		$minCount = min( $tags );
		$maxCount = max( $tags );

		if ( $maxCount == $minCount ) {
			$size = $minSize + $maxSizeIncrease / 2;
		} else {
			$denominator = log( $maxCount ) - log( $minCount );
			$sizes = array();
		}

		$attrs = array (
			'class' => 'cargoTagcloud',
			'align' => 'justify'
		);

		$text = Html::openElement( 'div', $attrs );

		foreach ( $tags as $tag => $count ) {
			if ( isset( $displayParams['template'] ) ) {
				$tagstring = '{{' . $displayParams['template'] .
					'|' . $tag . '|' . $count . '}}';
				$tagstring = CargoUtils::smartParse( $tagstring, null );
			} else {
				$tagstring = $tag;
			}
			if ( $maxCount != $minCount ) {
				$countstr = strval( $count );
				if ( !isset( $sizes[$countstr] ) ) {
					$sizes[$countstr] =
						$minSize + $maxSizeIncrease *
						( log( $count ) - log( $minCount ) ) /
						$denominator;
				}
				$size = $sizes[$countstr];
			}
			$text .= Html::rawElement( 'span',
				array (
					'style' => 'font-size:' . $size .'%;white-space:nowrap;'
				),
				$tagstring );
			$text .= ' ';
		}

		$text .= Html::closeElement( 'div' );

		return $text;
	}

}

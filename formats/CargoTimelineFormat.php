<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTimelineFormat extends CargoDeferredFormat {
	function allowedParameters() {
		return array( 'width', 'height', );
	}

	/**
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array $querySpecificParams Unused
	 * @return type
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.timeline' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'timeline';
		// $queryParams['color'] = array();
		/* foreach ( $sqlQueries as $i => $sqlQuery ) {
		  if ( $querySpecificParams != null ) {
		  // Add any handling here.
		  }
		  } */

		if ( array_key_exists( 'height', $displayParams ) ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
		} else {
			$height = "350px";
		}
		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "100%";
		}

		$attrs = array(
			'class' => 'cargoTimeline',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "height: $height; width: $width; border: 1px solid #aaa;"
		);
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

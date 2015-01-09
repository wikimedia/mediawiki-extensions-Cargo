<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoBarChartFormat extends CargoDeferredFormat {
	function allowedParameters() {
		return array( 'width', 'height' );
	}

	/**
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array $querySpecificParams Unused
	 * @return string
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.nvd3' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'nvd3chart';

		$svgAttrs = array();
		if ( array_key_exists( 'width', $displayParams ) ) {
			$svgAttrs['width '] = $displayParams['width'];
		} else {
			$svgAttrs['width'] = "100%";
		}
		if ( array_key_exists( 'height', $displayParams ) ) {
			$svgAttrs['height'] = $displayParams['height'];
		} else {
			// Stub value, so that we know to replace it.
			$svgAttrs['height'] = '1px';
		}

		$svgText = Html::element( 'svg', $svgAttrs, '' );

		$divAttrs = array(
			'class' => 'cargoBarChart',
			'dataurl' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'div', $divAttrs, $svgText );

		return $text;
	}

}

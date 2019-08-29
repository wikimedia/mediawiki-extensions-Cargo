<?php
/**
 * @author Kris Field
 * @ingroup Cargo
 */

class CargoPieChartFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return array(
			'height' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ),
			'width' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() )
		);
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
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
			$svgAttrs['width'] = $width;
		} else {
			$svgAttrs['width'] = "100%";
		}
		if ( array_key_exists( 'height', $displayParams ) ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
			$svgAttrs['height'] = $height;
		} else {
			// Stub value, so that we know to replace it.
			$svgAttrs['height'] = '1px';
		}

		$svgText = Html::element( 'svg', $svgAttrs, '' );

		$divAttrs = array(
			'class' => 'cargoPieChart',
			'dataurl' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'div', $divAttrs, $svgText );

		return $text;
	}

}

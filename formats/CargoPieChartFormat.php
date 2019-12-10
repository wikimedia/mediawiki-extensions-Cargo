<?php
/**
 * @author Kris Field
 * @ingroup Cargo
 */

class CargoPieChartFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return array(
			'height' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ),
			'width' => array( 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ),
			'colors' => array( 'type' => 'string', 'label' => wfMessage( 'cargo-viewdata-colorsparam' )->parse() )
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
		if ( array_key_exists( 'width', $displayParams ) && $displayParams['width'] != '' ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
			$svgAttrs['width'] = $width;
		} else {
			$svgAttrs['width'] = '700px';
		}
		if ( array_key_exists( 'height', $displayParams ) && $displayParams['height'] != '' ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
			$svgAttrs['height'] = $height;
		} else {
			$svgAttrs['height'] = '400px';
		}

		$svgText = Html::element( 'svg', $svgAttrs, '' );

		$divAttrs = array(
			'class' => 'cargoPieChart',
			'dataurl' => $ce->getFullURL( $queryParams ),
		);
		if ( array_key_exists( 'colors', $displayParams ) && $displayParams['colors'] != '' ) {
			$divAttrs['data-colors'] = json_encode( explode( ',', $displayParams['colors'] ) );
		}
		$text = Html::rawElement( 'div', $divAttrs, $svgText );

		return $text;
	}

}

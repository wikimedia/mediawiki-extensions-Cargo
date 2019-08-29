<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoExcelFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return array(
			'filename' => array( 'type' => 'string' ),
			'link text' => array( 'type' => 'string' ),
			'parse values' => array( 'type' => 'boolean' )
		);
	}

	/**
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams Unused
	 * @param array $querySpecificParams Unused
	 * @return string
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'excel';
		if ( array_key_exists( 'filename', $displayParams ) ) {
			$queryParams['filename'] = $displayParams['filename'];
		}
		if ( array_key_exists( 'parse values', $displayParams ) ) {
			$queryParams['parse values'] = $displayParams['parse values'];
		}
		if ( array_key_exists( 'link text', $displayParams ) ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-viewxls' )->text();
		}
		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, $linkText );

		return $text;
	}

}

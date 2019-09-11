<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCSVFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return array(
			'delimiter' => array( 'type' => 'string', 'label' => wfMessage( 'cargo-viewdata-delimiterparam' )->parse() ),
			'link text' => array( 'type' => 'string' ),
			'filename' => array( 'type' => 'string' ),
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
		$queryParams['format'] = 'csv';
		if ( array_key_exists( 'delimiter', $displayParams ) && $displayParams['delimiter'] != '' ) {
			$queryParams['delimiter'] = $displayParams['delimiter'];
		}
		if ( array_key_exists( 'filename', $displayParams ) && $displayParams['filename'] != '' ) {
			$queryParams['filename'] = $displayParams['filename'];
		}
		if ( array_key_exists( 'parse values', $displayParams ) && $displayParams['parse values'] != '' ) {
			$queryParams['parse values'] = $displayParams['parse values'];
		}
		if ( array_key_exists( 'link text', $displayParams ) && $displayParams['link text'] != '' ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-viewcsv' )->text();
		}
		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, $linkText );

		return $text;
	}

}

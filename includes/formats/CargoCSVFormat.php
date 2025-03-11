<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;

class CargoCSVFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'delimiter' => [ 'type' => 'string', 'label' => wfMessage( 'cargo-viewdata-delimiterparam' )->parse() ],
			'link text' => [ 'type' => 'string' ],
			'filename' => [ 'type' => 'string' ],
			'parse values' => [ 'type' => 'boolean' ]
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams Unused
	 * @param array|null $querySpecificParams Unused
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
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
		$linkAttrs = [
			'href' => $ce->getFullURL( $queryParams ),
		];
		return Html::element( 'a', $linkAttrs, $linkText );
	}

}

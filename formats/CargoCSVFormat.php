<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCSVFormat extends CargoDeferredFormat {

        function allowedParameters() {
                return array( 'delimiter' );
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
		if ( array_key_exists( 'delimiter', $displayParams ) ) {
			$queryParams['delimiter'] = $displayParams['delimiter'];
		}
		if ( array_key_exists( 'filename', $displayParams ) ) {
			$queryParams['filename'] = $displayParams['filename'];
		}
		if ( array_key_exists( 'link text', $displayParams ) ) {
			$linktext = $displayParams['link text'];
		} else { 
			$linktext = wfMessage( 'cargo-viewcsv' )->text();
		}
		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, $linktext );

		return $text; 
	}

}

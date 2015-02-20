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

		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, wfMessage( 'cargo-viewcsv' )->text() );

		return $text;
	}

}

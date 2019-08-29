<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoJSONFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return array( 'parse values' => array( 'type' => 'boolean' ) );
	}

	/**
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams Unused
	 * @param array $querySpecificParams Unused
	 * @return text HTML
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'json';
		if ( array_key_exists( 'parse values', $displayParams ) ) {
			$queryParams['parse values'] = $displayParams['parse values'];
		}

		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, wfMessage( 'cargo-viewjson' )->text() );

		return $text;
	}

}

<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoJSONFormat extends CargoDeferredFormat {

	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'json';

		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, wfMessage( 'cargo-viewjson' )->text() );

		return $text;
	}

}

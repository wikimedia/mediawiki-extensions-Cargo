<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;

class CargoTimelineFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ]
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams Unused
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( [ 'ext.cargo.timeline' ] );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'timeline';
		// $queryParams['color'] = array();
		/* foreach ( $sqlQueries as $i => $sqlQuery ) {
		  if ( $querySpecificParams != null ) {
		  // Add any handling here.
		  }
		  } */

		$height = CargoUtils::getCSSSize( $displayParams, 'height', '350px' );
		$width = CargoUtils::getCSSSize( $displayParams, 'width', '100%' );

		$attrs = [
			'class' => 'cargoTimeline',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "height: $height; width: $width; border: 1px solid #aaa;"
		];
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

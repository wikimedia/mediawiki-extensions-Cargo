<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;

class CargoBPMNFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ],
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( [ 'ext.cargo.bpmn' ] );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'bpmn';
		$height = CargoUtils::getCSSSize( $displayParams, 'height', '350px' );
		$width = CargoUtils::getCSSSize( $displayParams, 'width', '100%' );

		$attrs = [
			'id' => 'canvas',
			'class' => 'cargoBPMN',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "height: $height; width: $width; border: 1px solid #aaa;"
		];

		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

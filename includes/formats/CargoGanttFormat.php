<?php

class CargoGanttFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ],
			'columns' => [ 'type' => 'string', 'label' => wfMessage( 'cargo-gantt-columns' )->parse() ],
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( [ 'ext.cargo.gantt' ] );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'gantt';

		if ( array_key_exists( 'height', $displayParams ) && $displayParams['height'] != '' ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
		} else {
			$height = "350px";
		}
		if ( array_key_exists( 'width', $displayParams ) && $displayParams['width'] != '' ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "100%";
		}

		$attrs = [
			'id' => 'ganttid',
			'class' => 'cargoGantt',
			'style' => "height: $height; width: $width; border: 1px solid #aaa;"
		];

		if ( array_key_exists( 'columns', $displayParams ) ) {
			$attrs['data-columns'] = $displayParams['columns'];
		}

		if ( array_key_exists( 'inline', $displayParams ) ) {
			// Make this a non-"deferred" display.
			$attrs['datafull'] = CargoExport::getGanttJSONData( $sqlQueries );
		} else {
			$attrs['dataurl'] = $ce->getFullURL( $queryParams );
		}

		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

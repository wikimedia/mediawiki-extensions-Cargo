<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCalendarFormat extends CargoDeferredFormat {
	function allowedParameters() {
		return array( 'width', 'start date', 'color' );
	}

	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.calendar' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'fullcalendar';
		$queryParams['color'] = array();
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			if ( $querySpecificParams != null ) {
				if ( array_key_exists( 'color', $querySpecificParams[$i] ) ) {
					$queryParams['color'][] = $querySpecificParams[$i]['color'];
				} else {
					// Stick an empty value in there, to
					// preserve the order for the queries
					// that do contain a color.
					$queryParams['color'][] = null;
				}
			}
		}

		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
		} else {
			$width = "100%";
		}

		$attrs = array(
			'class' => 'cargoCalendar',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "width: $width"
		);
		if ( array_key_exists( 'start date', $displayParams ) ) {
			$attrs['startdate'] = $displayParams['start date'];
		}
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

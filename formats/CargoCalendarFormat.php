<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCalendarFormat extends CargoDeferredFormat {
	function allowedParameters() {
		return array( 'width', 'start date', 'color', 'text color', 'height' );
	}

	/**
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array $querySpecificParams
	 * @return string
	 */
	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		global $wgVersion, $wgUsejQueryThree;

		// This check will probably be necessary as long as MW <= 1.29 is supported.
		if ( version_compare( $wgVersion, '1.30', '<' ) || $wgUsejQueryThree === false ) {
			$this->mOutput->addModules( 'ext.cargo.calendar.jquery1' );
		} else {
			$this->mOutput->addModules( 'ext.cargo.calendar.jquery3' );
		}
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'fullcalendar';
		$queryParams['color'] = array();
		$queryParams['text color'] = array();
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
				if ( array_key_exists( 'text color', $querySpecificParams[$i] ) ) {
					$queryParams['text color'][] = $querySpecificParams[$i]['text color'];
				} else {
					// Stick an empty value in there, to
					// preserve the order for the queries
					// that do contain a color.
					$queryParams['text color'][] = null;
				}
			}
		}

		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "100%";
		}

		if ( array_key_exists( 'height', $displayParams ) ) {
			$height = $displayParams['height'];
			// The height should be either a number or "auto".
			if ( !is_numeric( $height ) ) {
				if ( $height != "auto" ) {
					$height = null;
				}
			}
		} else {
			$height = null;
		}

		$attrs = array(
			'class' => 'cargoCalendar',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "width: $width",
			'height' => $height,
		);
		if ( array_key_exists( 'view', $displayParams ) ) {
			$view = $displayParams['view'];
			// Enable simpler view names.
			if ( $view == 'day' ) {
				$view = 'agendaDay';
			} elseif ( $view == 'week' ) {
				$view = 'agendaWeek';
			}
			$attrs['startview'] = $view;
		} else {
			$attrs['startview'] = 'month';
		}
		if ( array_key_exists( 'start date', $displayParams ) ) {
			$attrs['startdate'] = $displayParams['start date'];
		}
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}

<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoGoogleMapsFormat extends CargoMapsFormat {

	function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "Google Maps";
	}

	public function getScripts() {
		return array(
			"https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"
		);
	}

}

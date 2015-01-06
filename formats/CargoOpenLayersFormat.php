<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoOpenLayersFormat extends CargoMapsFormat {

	function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "OpenLayers";
	}

	public function getScripts() {
		return array( "http://www.openlayers.org/api/OpenLayers.js" );
	}

}

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

	public static function getScripts() {
		return array( "//openlayers.org/api/OpenLayers.js" );
	}

}

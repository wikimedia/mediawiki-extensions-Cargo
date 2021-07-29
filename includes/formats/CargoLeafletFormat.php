<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoLeafletFormat extends CargoMapsFormat {

	public function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "Leaflet";
	}

	public static function allowedParameters() {
		$allowedParams = parent::allowedParameters();
		$allowedParams['image'] = [ 'type' => 'string' ];
		return $allowedParams;
	}

	public static function getScripts() {
		return [
			"https://unpkg.com/leaflet@1.7.1/dist/leaflet.js",
			"https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"
		];
	}

	public static function getStyles() {
		return [
			"https://unpkg.com/leaflet@1.7.1/dist/leaflet.css",
			"https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css",
			"https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css"
		];
	}

}

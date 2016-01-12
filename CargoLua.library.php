<?php

class CargoLuaLibrary extends Scribunto_LuaLibraryBase {

	function register() {
		$lib = array(
			'get' => array( $this, 'cargoGet' ),
		);
		return $this->getEngine()->registerInterface( __DIR__ . '/cargo.lua', $lib, array() );
	}

	function cargoGet() {
		global $wgCargoQueryResults;

		$val = $wgCargoQueryResults;
		if ( $val == null ) {
			return array( null );
		}

		array_unshift( $val, null );
		$wgCargoQueryResults = null;
		return array( $val );
	}
}

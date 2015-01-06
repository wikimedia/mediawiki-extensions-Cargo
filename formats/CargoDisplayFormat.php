<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDisplayFormat {

	function __construct( $output, $parser = null ) {
		$this->mOutput = $output;
		$this->mParser = $parser;
	}

	function allowedParameters() {
		return array();
	}

	function isDeferred() {
		return false;
	}

}

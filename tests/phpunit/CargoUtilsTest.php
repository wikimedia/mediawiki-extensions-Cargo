<?php

class CargoUtilsTest extends MediaWikiTestCase {

	public function testSmartSplit() {
		$this->assertEquals( array(), CargoUtils::smartSplit( ',', '' ) );
		$this->assertEquals( array( 'one', 'two' ), CargoUtils::smartSplit( ',', 'one,two' ) );
		$this->assertEquals( array( 'one', '0' ), CargoUtils::smartSplit( '|', 'one||0|' ) );
	}

}

<?php

class CargoUtilsTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers CargoUtils::smartSplit
	 */
	public function testSmartSplit() {
		$this->assertEquals( [], CargoUtils::smartSplit( ',', '' ) );
		$this->assertEquals( [ 'one', 'two' ], CargoUtils::smartSplit( ',', 'one,two' ) );
		$this->assertEquals( [ 'one', '0' ], CargoUtils::smartSplit( '|', 'one||0|' ) );
	}

}

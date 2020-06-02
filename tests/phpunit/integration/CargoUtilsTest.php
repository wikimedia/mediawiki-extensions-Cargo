<?php

class CargoUtilsTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers CargoUtils::smartSplit
	 * @covers CargoUtils::findQuotedStringEnd
	 * @dataProvider provideSmartSplitData
	 */
	public function testSmartSplit( $delimiter, $string, array $expected ) {
		$actual = CargoUtils::smartSplit( $delimiter, $string );
		$this->assertSame( $expected, $actual );
	}

	/** @return array */
	public function provideSmartSplitData() {
		return [
			[ '', '', [] ],
			[ ',', 'one,two', [ 'one', 'two' ] ],
			[ '|', 'one||0|', [ 'one', '0' ] ],
		];
	}

	/**
	 * @covers CargoUtils::formatError
	 */
	public function testFormatError() {
		$expected = '<div class="error">cargo error string here</div>';
		$actual = CargoUtils::formatError( 'cargo error string here' );

		$this->assertSame( $expected, $actual );
	}

}

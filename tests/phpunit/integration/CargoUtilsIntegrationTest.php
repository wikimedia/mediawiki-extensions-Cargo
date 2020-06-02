<?php

class CargoUtilsIntegrationTest extends MediaWikiIntegrationTestCase {
	public function setUp() : void {
		$this->setMwGlobals(
			[
				'wgDisableInternalSearch' => true,
				'wgDummyLanguageCodes' => true
			]
		);
	}

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
	 * @covers CargoUtils::getSpecialPage
	 */
	public function testGetValidSpecialPage() {
		$actual = CargoUtils::getSpecialPage( 'Block' );

		$this->assertInstanceOf( SpecialPage::class, $actual );
	}

	/**
	 * @covers CargoUtils::getSpecialPage
	 */
	public function testGetInvalidSpecialPage() {
		$actual = CargoUtils::getSpecialPage( 'NotValidPage' );

		$this->assertNull( $actual );
	}

	/**
	 * @covers CargoUtils::getContentLang
	 */
	public function testGetContentLang() {
		$actual = CargoUtils::getContentLang();

		$this->assertInstanceOf( Language::class, $actual );
	}
}

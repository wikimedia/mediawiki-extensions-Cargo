<?php

use MediaWiki\MediaWikiServices;

class CargoUtilsIntegrationTest extends MediaWikiIntegrationTestCase {
	public function setUp(): void {
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
			[ '', 'one', [ 'one' ] ],
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

	/**
	 * @covers CargoUtils::makeLink
	 * @dataProvider provideMakeLinkData
	 */
	public function testMakeLink(
		$linkRenderer,
		$title,
		$msg,
		$attr,
		$params,
		$expected
	) {
		$actual = CargoUtils::makeLink( $linkRenderer, $title, $msg, $attr, $params );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public function provideMakeLinkData() {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$title = Title::newFromText( 'Test' );
		return [
			[ $linkRenderer, null, null, [], [], null ],
			[ $linkRenderer, null, '', [], [], null ],
		];
	}

	/**
	 * @covers CargoUtils::getPageProp
	 * @covers CargoUtils::getAllPageProps
	 * @dataProvider provideGetPagePropData
	 */
	public function testGetPageProp( $pageID, $pageProp, $expected ) {
		$actual = CargoUtils::getPageProp( $pageID, $pageProp );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return bool|string
	 */
	public function provideGetPagePropData() {
		return null ? ( $this->testGetPageProp() === null ) : 'test';
	}
}

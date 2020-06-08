<?php

class CargoPageValuesActionTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers CargoPageValuesAction::getName
	 * @dataProvider provideTitle
	 */
	public function testGetName( $title ) {
		$article = new Article( $title );
		$cargoPageValuesAction = new CargoPageValuesAction(
			$article, RequestContext::getMain()
		);

		$actual = $cargoPageValuesAction->getName();
		$this->assertSame( 'pagevalues', $actual );
	}

	/**
	 * Mock BaseTemplate for use in ::addLink test.
	 * @param Title $title
	 * @return BaseTemplate
	 */
	private function getBaseTemplate( Title $title ) {
		$skin = $this->getMockBuilder( Skin::class )
			->onlyMethods( [ 'getTitle' ] )
			->getMockForAbstractClass();
		$skin->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $title );

		$baseTemplate = $this->getMockBuilder( BaseTemplate::class )
			->onlyMethods( [ 'getSkin' ] )
			->getMockForAbstractClass();
		$baseTemplate->expects( $this->once() )
			->method( 'getSkin' )
			->willReturn( $skin );
		return $baseTemplate;
	}

	/**
	 * @covers CargoPageValuesAction::addLink
	 * @dataProvider provideTitle
	 */
	public function testAddLink( $title ) {
		$toolbox = [];
		$actual = CargoPageValuesAction::addLink(
			$this->getBaseTemplate( $title ),
			$toolbox
		);

		$this->assertTrue( $actual );
	}

	/** @return array */
	public function provideTitle() {
		return [
			// Test any page that is not a special page
			[ Title::newFromText( 'Test', NS_MAIN ) ],
			// Test special pages too, as this is needed
			[ Title::newFromText( 'Test', NS_SPECIAL ) ],
		];
	}

}

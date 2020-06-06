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
	private function getSkin( Title $title ) {
		$skin = $this->getMockBuilder( Skin::class )
			->onlyMethods( [ 'getTitle' ] )
			->getMockForAbstractClass();
		$skin->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $title );

		return $skin;
	}

	/**
	 * @covers CargoPageValuesAction::addLink
	 * @dataProvider provideTitle
	 */
	public function testAddLink( $title ) {
		$sidebar = [];
		$actual = CargoPageValuesAction::addLink(
			$this->getSkin( $title ),
			$sidebar
		);

		// @TODO - need a good check here
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

<?php

class CargoSearchMySQLIntegrationTest extends MediaWikiIntegrationTestCase {
	/** @var CargoSearchMySQL */
	private $cargoSearchMysql;

	public function setUp(): void {
		$this->setMwGlobals(
			[ 'wgMainStash' => true ]
		);
		$this->cargoSearchMysql = new CargoSearchMySQL();
	}

	/**
	 * @covers CargoSearchMySQL::regexTerm
	 * @dataProvider provideRegexTermData
	 */
	public function testRegexTerm( $string, $wildcard, $expected ) {
		$actual = $this->cargoSearchMysql->regexTerm( $string, $wildcard );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public static function provideRegexTermData() {
		return [
			# '' as wildcard is considered as false (implicitly)
			[ 'Cargo example', '', '\bCargo example\b' ],
			[ 'Cargo example', false, '\bCargo example\b' ],
			[ 'Cargo example', true, '\bCargo example' ],
			[ '英漢字典', false, '\b英漢字典\b' ],
			[ '英漢字典', true, '\b英漢字典' ],
			[ 'Cargo/example', false, '\bCargo\/example\b' ],
			[ 'Cargo/example', true, '\bCargo\/example' ],
		];
	}

	/**
	 * @covers CargoSearchMySQL::getIndexField
	 * @dataProvider provideGetIndexFieldData
	 */
	public function testGetIndexField( $fulltext, $expected ) {
		$actual = $this->cargoSearchMysql->getIndexField( $fulltext );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public static function provideGetIndexFieldData() {
		return [
			[ true, 'si_text' ],
			[ false, 'si_title' ]
		];
	}
}

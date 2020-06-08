<?php

class CargoFieldDescriptionTest extends MediaWikiIntegrationTestCase {
	private $cargoFieldDescription;

	public function setUp() : void {
		$this->cargoFieldDescription = new CargoFieldDescription();
	}

	/**
	 * @covers CargoFieldDescription::newFromDBArray
	 * @dataProvider provideDescriptionData
	 */
	public function testNewFromDBArray( $fieldDescData ) {
		$actual = CargoFieldDescription::newFromDBArray( $fieldDescData );

		$this->assertInstanceOf( CargoFieldDescription::class, $actual );
		$this->assertSame( 'String', $actual->mType );
		$this->assertSame( 100, $actual->mSize );
		$this->assertTrue( $actual->mIsUnique );
		$this->assertSame( 'Nothing', $actual->mOtherParams['extra'] );
	}

	/** @return array */
	public function provideDescriptionData() {
		return [
			[
				[
					'type' => 'String',
					'size' => 100,
					'unique' => '',
					'extra' => 'Nothing'
				]
			],
		];
	}

	/**
	 * @covers CargoFieldDescription::setDelimiter
	 * @covers CargoFieldDescription::getDelimiter
	 */
	public function testGetDelimiter() {
		$this->cargoFieldDescription->setDelimiter(
			'A delimiter\n\nto test'
		);

		$this->assertSame(
			"A delimiter\n\nto test",
			$this->cargoFieldDescription->getDelimiter()
		);
	}

	/**
	 * @covers CargoFieldDescription::isDateOrDatetime
	 * @dataProvider provideDateTimeFormat
	 */
	public function testIsDateOrDatetimeFormat( $format ) {
		$this->cargoFieldDescription->mType = $format;

		$this->assertTrue( $this->cargoFieldDescription->isDateOrDatetime() );
	}

	/** @return array */
	public function provideDateTimeFormat() {
		return [
			[ 'Date' ],
			[ 'Start date' ],
			[ 'End date' ],
			[ 'Datetime' ],
			[ 'Start datetime' ],
			[ 'End datetime' ],
		];
	}

	/**
	 * @covers CargoFieldDescription::isDateOrDatetime
	 * @dataProvider provideNotDateTimeFormat
	 */
	public function testIsNotDateOrDatetimeFormat( $format ) {
		$this->cargoFieldDescription->mType = $format;

		$this->assertFalse( $this->cargoFieldDescription->isDateOrDatetime() );
	}

	/** @return array */
	public function provideNotDateTimeFormat() {
		return [
			[ 'Da-te' ],
			[ 'Start-date' ],
			[ 'End-date' ],
			[ 'Date-time' ],
			[ 'Start-datetime' ],
			[ 'End-datetime' ],
		];
	}

	/**
	 * @covers CargoFieldDescription::getFieldSize
	 * @dataProvider provideFieldSize
	 */
	public function testGetFieldSize( $type, $size, $expected ) {
		$this->cargoFieldDescription->mType = $type;
		$this->cargoFieldDescription->mSize = $size;

		$actual = $this->cargoFieldDescription->getFieldSize();
		$this->assertSame( $expected, $actual );
	}

	/** @return array */
	public function provideFieldSize() {
		return [
			[ 'Date', 100, null ],
			[ 'Integer', 200, null ],
			[ 'String', 400, 400 ],
		];
	}

	/**
	 * @covers CargoFieldDescription::getFieldSize
	 */
	public function testGetFieldSizeWithCargoDefaultStringBytes() {
		$this->setMwGlobals( [
			"wgCargoDefaultStringBytes" => 500
		] );

		$actual = $this->cargoFieldDescription->getFieldSize();
		$this->assertSame( 500, $actual );
	}
}

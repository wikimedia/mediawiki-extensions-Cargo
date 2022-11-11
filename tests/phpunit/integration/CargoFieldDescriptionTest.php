<?php

class CargoFieldDescriptionTest extends MediaWikiIntegrationTestCase {
	private $cargoFieldDescription;

	public function setUp(): void {
		$this->cargoFieldDescription = new CargoFieldDescription();
	}

	/**
	 * @covers CargoFieldDescription::newFromString
	 */
	public function testNewFromStringReturnNull() {
		$actual = CargoFieldDescription::newFromString( 'list' );
		$this->assertNull( $actual );
	}

	/**
	 * @covers CargoFieldDescription::newFromString
	 * @dataProvider provideValidDescriptionString
	 */
	public function testNewFromString( $fieldDescStr ) {
		$actual = CargoFieldDescription::newFromString( $fieldDescStr );
		$this->assertInstanceOf(
			CargoFieldDescription::class, $actual
		);
	}

	/**
	 * @return array
	 */
	public function provideValidDescriptionString() {
		return [
			[ 'URL' ],
			[ 'list (;) of String (size=10;dependent on=size;allowed values=*,)' ]
		];
	}

	/**
	 * @covers CargoFieldDescription::newFromString
	 * @dataProvider provideInValidDescriptionString
	 */
	public function testExceptionNewFromString( $fieldDescStr ) {
		$this->expectException( MWException::class );
		$actual = CargoFieldDescription::newFromString( $fieldDescStr );
	}

	/**
	 * @return array
	 */
	public function provideInValidDescriptionString() {
		return [
			[ 'list (;) of BOOLEAN' ],
			[ 'TEXT (unique;)' ],
			[ 'String (size=(10))' ],
			[ 'String (allowed values)=monday,tuesday,wednesday,thursday,friday)' ],
			[ 'String (' ],
			[ 'String )' ],
		];
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
		$this->assertIsArray( $actual->mDependentOn );
		$this->assertTrue( $actual->mIsList );
		$this->assertSame(
			"Something\nto delimit",
			$actual->getDelimiter()
		);
		$this->assertIsArray( $actual->mAllowedValues );
		$this->assertTrue( $actual->mIsMandatory );
		$this->assertTrue( $actual->mIsUnique );
		$this->assertSame( 'regex', $actual->mRegex );
		$this->assertTrue( $actual->mIsHidden );
		$this->assertTrue( $actual->mIsHierarchy );
		$this->assertIsArray( $actual->mHierarchyStructure );
		$this->assertSame( 'Nothing', $actual->mOtherParams['extra'] );
	}

	/** @return array */
	public function provideDescriptionData() {
		return [
			[
				[
					'type' => 'String',
					'size' => 100,
					'dependent on' => [],
					'isList' => true,
					'delimiter' => 'Something\nto delimit',
					'allowedValues' => [],
					'mandatory' => true,
					'unique' => true,
					'regex' => 'regex',
					'hidden' => true,
					'hierarchy' => true,
					'hierarchyStructure' => [],
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

	/**
	 * @covers CargoFieldDescription::toDBArray
	 */
	public function testToDBArray() {
		$this->cargoFieldDescription->mType = 'String';
		$this->cargoFieldDescription->mSize = 40;
		$this->cargoFieldDescription->mDependentOn = [ 'nothing' ];
		$this->cargoFieldDescription->mIsList = true;
		$this->cargoFieldDescription->setDelimiter( '\n' );
		$this->cargoFieldDescription->mAllowedValues = [ 'nothing' ];
		$this->cargoFieldDescription->mIsMandatory = true;
		$this->cargoFieldDescription->mIsUnique = true;
		$this->cargoFieldDescription->mRegex = 'regex';
		$this->cargoFieldDescription->mIsHidden = true;
		$this->cargoFieldDescription->mIsHierarchy = true;
		$this->cargoFieldDescription->mOtherParams['extra'] = 'nothing';

		$fieldDescArray = $this->cargoFieldDescription->toDBArray();
		$this->assertIsArray( $fieldDescArray );
		$this->assertArrayHasKey( 'type', $fieldDescArray );
		$this->assertArrayHasKey( 'size', $fieldDescArray );
		$this->assertArrayHasKey( 'dependent on', $fieldDescArray );
		$this->assertArrayHasKey( 'isList', $fieldDescArray );
		$this->assertArrayHasKey( 'delimiter', $fieldDescArray );
		$this->assertArrayHasKey( 'allowedValues', $fieldDescArray );
		$this->assertArrayHasKey( 'mandatory', $fieldDescArray );
		$this->assertArrayHasKey( 'unique', $fieldDescArray );
		$this->assertArrayHasKey( 'regex', $fieldDescArray );
		$this->assertArrayHasKey( 'hidden', $fieldDescArray );
		$this->assertArrayHasKey( 'hierarchy', $fieldDescArray );
		$this->assertArrayHasKey( 'hierarchyStructure', $fieldDescArray );
		$this->assertArrayHasKey( 'extra', $fieldDescArray );
	}

	/**
	 * @covers CargoFieldDescription::prepareAndValidateValue
	 * @covers CargoFieldDescription::newFromString
	 * @dataProvider provideFieldValueData
	 */
	public function testPrepareAndValidateValue( $desc, $fieldValue, $expected ) {
		$fieldDesc = CargoFieldDescription::newFromString( $desc );
		$actual = $fieldDesc->prepareAndValidateValue( $fieldValue );
		$this->assertArrayEquals( $actual, $expected );
	}

	public function provideFieldValueData() {
		return [
			[ '', '', [ 'value' => '' ] ],
			[ 'Date', '', [ 'value' => null ] ],

			[
				"list (;) of String (allowed values=monday,tuesday,wednesday,thursday,friday)",
				"monday;friday",
				[ 'value' => "monday;friday" ]
			],
			[
				"String (allowed values=monday,tuesday,wednesday,thursday,friday)",
				'tuesday',
				[ 'value' => 'tuesday' ]
			],
			[
				"String (ALLOWED VALUES=Group A (female), Group B (male)",
				"Group B (male",
				[ 'value' => "Group B (male" ]
			],
			[
				"String (allowed values=Group A (female), Group B (male)",
				"Group B (male",
				[ 'value' => "Group B (male" ]
			],
			[
				"String (allowed values=Group A (female), Group B (male))",
				"Group B (male)",
				[ 'value' => "Group B (male)" ]
			],
			[
				"String (size=100; allowed values=Group A (female), Group B (male))",
				"Group B (male)",
				[ 'value' => "Group B (male)" ]
			],

			[
				"list (;) of Date",
				"2019;06-06-2021;",
				[
					'value' => "2019-01-01;2021-06-06",
					'precision' => 1
				]
			],
			[ 'Date', '2020', [ 'value' => "2020-01-01", 'precision' => 3 ] ],

			[
				"list (;) of Integer",
				"10;100;1,000;109.95",
				[ 'value' => "10;100;1000;110" ]
			],
			// [ 'Integer', '20 000', [ 'value' => 20.0 ] ],
			[ 'Integer', '2,000', [ 'value' => 2000.0 ] ],
			[ 'Integer', '200', [ 'value' => 200.0 ] ],
			[ 'Integer', '20.4', [ 'value' => 20.0 ] ],

			[ 'Float', '1,500', [ 'value' => 1500 ] ],

			[ 'Rating', '3.5', [ 'value' => 3.5 ] ],

			[ 'Boolean', 0, [ 'value' => '0' ] ],
			[ 'Boolean', '0', [ 'value' => '0' ] ],
			[ 'Boolean', 'no', [ 'value' => '0' ] ],
			[ 'Boolean', 'No', [ 'value' => '0' ] ],
			[ 'Boolean', 1, [ 'value' => '1' ] ],
			[ 'Boolean', '1', [ 'value' => '1' ] ],
			[ 'Boolean', 'yes', [ 'value' => '1' ] ],
			[ 'Boolean', 'Yes', [ 'value' => '1' ] ],
		];
	}
}

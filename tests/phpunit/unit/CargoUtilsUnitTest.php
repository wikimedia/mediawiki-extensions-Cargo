<?php

class CargoUtilsUnitTest extends MediaWikiUnitTestCase {
	/**
	 * @covers CargoUtils::formatError
	 */
	public function testFormatError() {
		$expected = '<div class="error">cargo error string here</div>';
		$actual = CargoUtils::formatError( 'cargo error string here' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers CargoUtils::removeQuotedStrings
	 * @dataProvider provideRemoveQuotedStringsValidTestCases
	 */
	public function testRemoveQuotedStringsValid( ?string $input, string $expected ): void {
		$actual = CargoUtils::removeQuotedStrings( $input );

		$this->assertSame( $expected, $actual );
	}

	public static function provideRemoveQuotedStringsValidTestCases(): iterable {
		yield 'null value' => [ null, '' ];
		yield 'empty string' => [ '', '' ];
		yield 'SQL fragment with single quotes' => [ 'Title=\'Test\'', 'Title=' ];
		yield 'SQL fragment with double quotes' => [ 'Title="Test"', 'Title=' ];
		yield 'SQL fragment with multiple quoted substrings' => [
			'Title="Test" OR Text=\'Foo\'', 'Title= OR Text='
		];
	}

	/**
	 * @covers CargoUtils::removeQuotedStrings
	 * @dataProvider provideRemoveQuotedStringsInvalidTestCases
	 */
	public function testRemoveQuotedStringsInvalid( ?string $input ): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Error: unclosed string literal.' );

		CargoUtils::removeQuotedStrings( $input );
	}

	public static function provideRemoveQuotedStringsInvalidTestCases(): iterable {
		yield 'SQL fragment with unterminated single quote substring' => [
			'Title=\'Test'
		];
		yield 'SQL fragment with unterminated double quote substring' => [
			'Title="Test'
		];
		yield 'SQL fragment with multiple unterminated quoted substrings' => [
			'Title="Test OR Text=\'Foo\'', 'Title= OR Text='
		];
		yield 'SQL fragment with stray quote' => [
			'Title=Test\''
		];
	}
}

<?php

/**
 * Integration test cases for the full Cargo parser function stack.
 *
 * @group Database
 *
 * @covers CargoQuery
 * @covers CargoDeclare
 * @covers CargoStore
 * @covers CargoQueryDisplayer
 */
class CargoQueryIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Prepare some basic fixture data that is likely to be used by several test cases.
		$fixtureTemplate = Title::makeTitle( NS_TEMPLATE, 'Books' );
		if ( !$fixtureTemplate->exists() ) {
			$this->createTable(
				'Books',
				[ 'Authors' => 'List (,) of String', 'Genres' => 'List (,) of String' ],
			);
			$this->storeData(
				'Lorem Ipsum',
				'Books',
				[ 'Authors' => 'John Doe,Jane Miller', 'Genres' => 'Fantasy' ]
			);
			$this->storeData(
				'A Test',
				'Books',
				[ 'Authors' => 'Jane Miller', 'Genres' => 'Crime' ]
			);
		}
	}

	public function testSimpleQuery(): void {
		$query = <<<TEXT
{{#cargo_query:
tables=Books
|fields=_pageName=Book
|where=Authors HOLDS 'Jane Miller'
}}
TEXT;

		$output = $this->getQueryOutput( $query );

		$this->assertXmlStringEqualsXmlString(
			'<p><a href="/wiki/A_Test" title="A Test">A Test</a>,  '
			. '<a href="/wiki/Lorem_Ipsum" title="Lorem Ipsum">Lorem Ipsum</a></p>',
			$output
		);
	}

	public function testQueryNoResults(): void {
		$query = <<<TEXT
{{#cargo_query:
tables=Books
|fields=_pageName=Book
|where=Authors HOLDS 'Not An Author'
}}
TEXT;

		$output = $this->getQueryOutput( $query );

		$this->assertXmlStringEqualsXmlString(
			'<p><em>No results</em></p>',
			$output
		);
	}

	public function testSimpleCompoundQuery(): void {
		$query = <<<TEXT
{{#cargo_compound_query:
tables=Books;where=Authors HOLDS 'John Doe';fields=Genres
}}
TEXT;

		$output = $this->getQueryOutput( $query );

		$this->assertXmlStringEqualsXmlString(
			"<p>Fantasy\n</p>",
			$output
		);
	}

	public function testShouldNotAllowSqlInjectionViaEncodedQuotes(): void {
		$query = <<<TEXT
{{#cargo_query:
tables=Books
|fields='a'=user_name,'b'=user_email,'c'=user_password
|where=1='1&#039; UNION ALL select user_name, user_email,user_password from user WHERE 1=&#039;1'
|order by=user_name ASC
}}
TEXT;
		$output = $this->getQueryOutput( $query );

		$this->assertXmlStringEqualsXmlString(
			'<div class="error">Error: the string "SELECT" cannot be used within #cargo_query.</div>',
			$output
		);
	}

	public function testQueryReferencingPageNameOnPageWithSpecialCharactersInTitleShouldNotFail(): void {
		$query = <<<TEXT
{{#cargo_query:
tables=Books
|where=Authors HOLDS "{{PAGENAME}}"
|fields=Genres
}}
TEXT;
		$title = Title::makeTitle( NS_MAIN, "Fool's Errand" );
		$output = $this->getQueryOutput( $query, $title );

		$this->assertXmlStringEqualsXmlString(
			'<p><em>No results</em></p>',
			$output
		);
	}

	/**
	 * Convenience function to get the formatted HTML output of a given Cargo query.
	 * @param string $query {{#cargo_query:}} call to fetch output for
	 * @param Title|null $title Title of the page to place the query on
	 * @return string
	 */
	private function getQueryOutput( string $query, ?Title $title = null ): string {
		$queryPage = $this->getNonexistingTestPage( $title );
		$this->editPage( $queryPage, $query );

		$html = $queryPage->getParserOutput()->getText();
		$doc = new DOMDocument();
		$doc->loadHTML( $html );

		// Strip the "mw-parser-output" wrapper from the output to reduce clutter.
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		$wrapper = $body->firstChild;
		return $doc->saveHTML( $wrapper->firstChild );
	}

	/**
	 * Convenience function to declare a Cargo table and create it in one go.
	 *
	 * @param string $tableName Name of the table to declare
	 * @param string[] $schema Associative array mapping field names to field types
	 */
	private function createTable( string $tableName, array $schema ): void {
		// Use a template that is named the same as the table for convenience.
		// Also ensure we use the same test page for a given table across tests.
		$title = Title::makeTitle( NS_TEMPLATE, $tableName );
		$page = $this->getExistingTestPage( $title );

		$table = CargoIntegrationTestUtils::getCargoTableDeclaration( $tableName, $schema );

		$this->editPage( $page, $table );

		CargoUtils::recreateDBTablesForTemplate(
			$page->getId(),
			false, // createReplacement
			$this->getTestUser()->getUser(),
			$tableName
		);
	}

	/**
	 * Store data in a given Cargo table from the context of a particular page.
	 * via a template invocation.
	 *
	 * @param string $pageName Page to store data from
	 * @param string $tableName Name of the Cargo table to store data in
	 * @param array $row Associative array mapping field names to values
	 */
	private function storeData( string $pageName, string $tableName, array $row ): void {
		$page = $this->getNonexistingTestPage( $pageName );

		$invocation = "{{{$tableName}\n";

		foreach ( $row as $field => $value ) {
			$invocation .= "|$field=$value\n";
		}

		$invocation .= '}}';

		$this->editPage( $page, $invocation );
	}
}

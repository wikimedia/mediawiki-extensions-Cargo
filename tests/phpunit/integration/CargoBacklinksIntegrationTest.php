<?php

/**
 * @group Database
 * @covers CargoBackLinks
 * @covers CargoLinksUpdateHandler
 */
class CargoBacklinksIntegrationTest extends MediaWikiIntegrationTestCase {
	private const BACKLINKS_TEST_TABLE = 'BacklinksTest';

	private const TEST_QUERY_PAGE = 'BacklinksTestQueryPage';
	private const TEST_OTHER_QUERY_PAGE = 'BacklinksTestOtherQueryPage';

	public function addDBDataOnce(): void {
		$title = Title::makeTitle( NS_TEMPLATE, self::BACKLINKS_TEST_TABLE );
		$page = $this->getExistingTestPage( $title );

		$schema = [ 'TestField' => 'String' ];
		$table = CargoIntegrationTestUtils::getCargoTableDeclaration( self::BACKLINKS_TEST_TABLE, $schema );

		$this->editPage( $page, $table );

		CargoUtils::recreateDBTablesForTemplate(
			$page->getId(),
			false, // createReplacement
			$this->getTestUser()->getUser(),
			self::BACKLINKS_TEST_TABLE
		);

		$this->storeTestData( 'TestPageWithValue', 'TestValue1' );
		$this->storeTestData( 'TestPageWithSameValue', 'TestValue1' );

		$this->storeTestData( 'TestPageWithOtherValue', 'TestValue2' );

		$queryPage = $this->getNonexistingTestPage( self::TEST_QUERY_PAGE );
		$this->editPage( $queryPage, $this->getTestQuery( 'TestValue1' ) );

		$otherQueryPage = $this->getNonexistingTestPage( self::TEST_OTHER_QUERY_PAGE );
		$this->editPage( $otherQueryPage, $this->getTestQuery( 'TestValue1' ) );

		// Run deferred updates explicitly to allow LinksUpdate to populate Cargo backlinks.
		DeferredUpdates::doUpdates();
	}

	/**
	 * Get wikitext for a Cargo query querying for pages in the backlinks test table
	 * with the given test field value.
	 * @param string $testFieldValue Test value to query for.
	 * @return string Wikitext for the query.
	 */
	private function getTestQuery( string $testFieldValue ): string {
		$query = "{{#cargo_query:\n";
		$query .= "|tables=" . self::BACKLINKS_TEST_TABLE . "\n";
		$query .= "|fields=_pageName=Title\n";
		$query .= "|where=TestField='$testFieldValue'\n";
		$query .= "}}";

		return $query;
	}

	/**
	 * Store test data for a given page.
	 * @param Title|string $pageName Title of the page to store test data for.
	 * @param string $testFieldValue Test value to store.
	 * @return void
	 * @throws MWException
	 */
	private function storeTestData( $pageName, string $testFieldValue ): void {
		$page = $this->getNonexistingTestPage( $pageName );

		$invocation = "{{" . self::BACKLINKS_TEST_TABLE . "\n";
		$invocation .= "|TestField=$testFieldValue\n";
		$invocation .= '}}';

		$this->editPage( $page, $invocation );
	}

	/**
	 * Reset the touched timestamp of a set of pages to the given timestamp.
	 * @param Title[] $titles The pages to reset.
	 * @param int $timestamp The UNIX touched timestamp to set.
	 */
	private function resetTouchedTimestamp( $titles, int $timestamp ): void {
		$dbw = $this->getDb();

		$dbw->update(
			'page',
			// SET
			[ 'page_touched' => wfTimestamp( TS_MW, $timestamp ) ],
			// WHERE
			[ 'page_id' => array_map( fn ( Title $title ) => $title->getArticleID(), $titles ) ],
			__METHOD__
		);
	}

	/**
	 * Convenience method to get the list of backlink IDs for a given query page.
	 * @param int $queryPageId
	 * @return int[]
	 */
	private function getCargoBacklinks( int $queryPageId ): array {
		$dbr = $this->getDb();

		return $dbr->selectFieldValues(
			'cargo_backlinks',
			'cbl_result_page_id',
			[ 'cbl_query_page_id' => $queryPageId ],
			__METHOD__
		);
	}

	public function testShouldPurgePagesThatQueryAGivenPage(): void {
		$oldTouchedTimestamp = wfTimestamp( TS_MW, wfTimestamp() - 60 );

		$queryPage = $this->getExistingTestPage( self::TEST_QUERY_PAGE )->getTitle();
		$otherQueryPage = $this->getExistingTestPage( self::TEST_OTHER_QUERY_PAGE )->getTitle();

		$this->resetTouchedTimestamp( [ $queryPage, $otherQueryPage ], $oldTouchedTimestamp );

		$testPage = $this->getExistingTestPage( 'TestPageWithValue' );
		$content = $testPage->getContent()->serialize();

		$this->editPage( $testPage, $content . "\nupdated" );

		$this->assertGreaterThan(
			$oldTouchedTimestamp,
			$this->getExistingTestPage( self::TEST_QUERY_PAGE )->getTitle()->getTouched()
		);
		$this->assertGreaterThan(
			$oldTouchedTimestamp,
			$this->getExistingTestPage( self::TEST_OTHER_QUERY_PAGE )->getTitle()->getTouched()
		);
	}

	public function testShouldUpdateBacklinksOnPageDeletion(): void {
		$queryPage = $this->getNonexistingTestPage( 'TestQueryPageToBeDeleted' );
		$this->editPage( $queryPage, $this->getTestQuery( 'TestValue1' ) );
		$queryPageId = $queryPage->getId();

		DeferredUpdates::doUpdates();

		$this->assertCount( 2, $this->getCargoBacklinks( $queryPageId ) );

		$this->deletePage( $queryPage );
		DeferredUpdates::doUpdates();
		$this->assertCount( 0, $this->getCargoBacklinks( $queryPageId ) );
	}

	public function testShouldMergeBacklinksFromMultipleQueriesOnPage(): void {
		$page = $this->getNonexistingTestPage( 'TestPageMultipleQueries' );
		$content = $this->getTestQuery( 'TestValue1' ) . $this->getTestQuery( 'TestValue2' );

		$this->editPage( $page, $content );
		DeferredUpdates::doUpdates();

		$this->assertCount( 3, $this->getCargoBacklinks( $page->getTitle()->getArticleID() ) );
	}

	public function testShouldHandleBacklinksFromQueryWithAliasedTables(): void {
		$page = $this->getNonexistingTestPage( 'TestPageWithAliasedTables' );
		$content = "{{#cargo_query:\n";
		$content .= "|tables=" . self::BACKLINKS_TEST_TABLE . "=testAlias\n";
		$content .= "|fields=_pageName=Title\n";
		$content .= "|where=TestField='TestValue1'\n";
		$content .= "}}";

		$this->editPage( $page, $content );
		DeferredUpdates::doUpdates();

		$this->assertCount( 2, $this->getCargoBacklinks( $page->getTitle()->getArticleID() ) );
	}
}

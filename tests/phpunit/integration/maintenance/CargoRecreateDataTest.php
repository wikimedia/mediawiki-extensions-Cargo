<?php

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers CargoRecreateData
 */
class CargoRecreateDataTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return CargoRecreateData::class;
	}

	/**
	 * Create a template that declares a Cargo table, without creating the table itself.
	 * @param string $tableName Name of the table to declare.
	 * @return int The page ID of the template.
	 */
	private function declareTable( string $tableName ): int {
		$title = Title::makeTitle( NS_TEMPLATE, $tableName );
		$page = $this->getExistingTestPage( $title );
		$declaration = CargoIntegrationTestUtils::getCargoTableDeclaration(
			$tableName, [ 'TestField' => 'String' ] );
		$this->editPage( $page, $declaration );

		return $page->getId();
	}

	/**
	 * Store one row of data in a table, via a page that calls its template.
	 * @param string $tableName Name of the table to store data in.
	 * @param string $pageName Name of the page to store the data from.
	 * @return void
	 */
	private function storeTestData( string $tableName, string $pageName ): void {
		$page = $this->getNonexistingTestPage( $pageName );
		$this->editPage( $page, "{{" . $tableName . "|TestField=TestValue}}" );
		DeferredUpdates::doUpdates();
	}

	/**
	 * @param string $tableName Name of the table to count the rows of.
	 * @return int
	 */
	private function getRowCount( string $tableName ): int {
		return CargoUtils::getDB()->selectRowCount( $tableName, '*', '', __METHOD__ );
	}

	public function testCreateMissingTablesOnlyCreatesDeclaredTable() {
		$tableName = 'RecreateDataMissingTest';
		$this->declareTable( $tableName );
		$this->assertFalse( CargoUtils::tableFullyExists( $tableName ),
			'Declaring a table in a template should not create it' );

		$this->maintenance->setOption( 'create-missing-tables-only', 1 );
		$this->maintenance->execute();

		$this->assertTrue( CargoUtils::tableFullyExists( $tableName ),
			'The table and its metadata should have been created' );
		$this->assertSame( 0, $this->getRowCount( $tableName ),
			'No data should have been stored in the table' );
	}

	public function testCreateMissingTablesOnlyLeavesExistingTableAlone() {
		$tableName = 'RecreateDataExistingTest';
		$templatePageID = $this->declareTable( $tableName );
		CargoUtils::recreateDBTablesForTemplate(
			$templatePageID,
			false,
			$this->getTestUser()->getUser(),
			$tableName
		);
		$this->storeTestData( $tableName, $tableName . 'Page' );
		$this->assertSame( 1, $this->getRowCount( $tableName ) );

		$this->expectOutputRegex( '/already exists/' );

		$this->maintenance->setOption( 'table', $tableName );
		$this->maintenance->setOption( 'create-missing-tables-only', 1 );
		$this->maintenance->execute();

		$this->assertSame( 1, $this->getRowCount( $tableName ),
			'The data in an existing table should have been left alone' );
	}

	public function testCreateMissingTablesOnlyCannotBeCombinedWithReplacement() {
		$this->maintenance->setOption( 'create-missing-tables-only', 1 );
		$this->maintenance->setOption( 'replacement', 1 );

		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public function testRecreatesDataForTable() {
		$tableName = 'RecreateDataPopulateTest';
		$this->declareTable( $tableName );
		$this->storeTestData( $tableName, $tableName . 'PageOne' );
		$this->storeTestData( $tableName, $tableName . 'PageTwo' );

		$this->maintenance->setOption( 'quiet', 1 );
		$this->maintenance->setOption( 'table', $tableName );
		$this->maintenance->execute();

		$this->assertTrue( CargoUtils::tableFullyExists( $tableName ) );
		$this->assertSame( 2, $this->getRowCount( $tableName ),
			'The data for both pages that call the template should have been stored' );
	}

	public function testReplacementPutsDataInReplacementTable() {
		$tableName = 'RecreateDataReplacementTest';
		$templatePageID = $this->declareTable( $tableName );
		CargoUtils::recreateDBTablesForTemplate(
			$templatePageID,
			false,
			$this->getTestUser()->getUser(),
			$tableName
		);
		$this->storeTestData( $tableName, $tableName . 'Page' );
		$this->assertSame( 1, $this->getRowCount( $tableName ) );

		$this->maintenance->setOption( 'quiet', 1 );
		$this->maintenance->setOption( 'table', $tableName );
		$this->maintenance->setOption( 'replacement', 1 );
		$this->maintenance->execute();

		$this->assertSame( 1, $this->getRowCount( $tableName . '__NEXT' ),
			'The data should have been stored in the replacement table' );
		$this->assertSame( 1, $this->getRowCount( $tableName ),
			'The original table should still hold its data' );
	}

	public function testTableNotDeclaredInAnyTemplateIsSkipped() {
		$this->expectOutputRegex( '/is not declared in any template/' );

		$this->maintenance->setOption( 'table', 'RecreateDataUndeclaredTest' );
		$this->maintenance->execute();
	}

}

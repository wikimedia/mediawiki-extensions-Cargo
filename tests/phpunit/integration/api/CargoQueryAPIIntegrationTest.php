<?php

/**
 * @group Database
 * @group API
 * @covers CargoQueryAPI
 */
class CargoQueryAPIIntegrationTest extends ApiTestCase {

	private const TEST_TABLE_TEMPLATE = 'CargoAPITable';

	public function addDBDataOnce() {
		$template = Title::makeTitle( NS_TEMPLATE, self::TEST_TABLE_TEMPLATE );
		$tableName = self::TEST_TABLE_TEMPLATE;
		$declare = "{{#cargo_declare:_table=$tableName\n";
		$declare .= "|Test=String\n";
		$declare .= '}}';

		$store = "{{#cargo_store:_table=$tableName}}";

		$table = "<noinclude>$declare</noinclude>\n<includeonly>$store</includeonly>";

		$this->editPage( $template, $table );

		CargoUtils::recreateDBTablesForTemplate(
			$template->getId(),
			false,
			$this->getTestUser()->getUser(),
			$tableName
		);
	}

	public function testShouldRejectQueryWithAliasedFieldNamesStartingWithUnderscores(): void {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'Error: Field alias "_pageName" starts with an underscore (_). This is not allowed in Cargo API queries.'
		);

		$this->doApiRequest( [
			'action' => 'cargoquery',
			'tables' => self::TEST_TABLE_TEMPLATE,
			'fields' => '_pageName',
		] );
	}
}

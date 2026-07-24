import { createApiClient } from 'wdio-mediawiki/Api';
import CargoTablesPage from '../pageobjects/cargotables.page.js';
import CargoTestUtils from '../cargo-utils.js';

describe( 'Special:CargoTables', () => {
	before( async () => {
		const apiClient = await createApiClient();

		const tableName = 'CargoTablesTest';
		await CargoTestUtils.createTable( apiClient, tableName, {
			example: 'String'
		} );

		await CargoTestUtils.createPageWithData( apiClient, 'CargoTablesTest1', tableName, {
			example: 'foo'
		} );
		await CargoTestUtils.createPageWithData( apiClient, 'CargoTablesTest2', tableName, {
			example: 'bar'
		} );
	} );

	it( 'displays table information', async () => {
		await CargoTablesPage.open();

		expect( await CargoTablesPage.getRowCount( 'CargoTablesTest' ) ).toBe( '2' );
		expect( await CargoTablesPage.getColumnCount( 'CargoTablesTest' ) ).toBe( '1' );
		expect( await CargoTablesPage.getTemplateName( 'CargoTablesTest' ) ).toBe( 'Template:CargoTablesTest' );
	} );
} );

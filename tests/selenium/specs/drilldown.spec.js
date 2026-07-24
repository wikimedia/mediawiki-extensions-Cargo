import { createApiClient } from 'wdio-mediawiki/Api';
import DrilldownPage from '../pageobjects/drilldown.page.js';
import CargoTestUtils from '../cargo-utils.js';

describe( 'Special:Drilldown', () => {
	before( async () => {
		const apiClient = await createApiClient();

		const tableName = 'CargoDrilldownTest';
		await CargoTestUtils.createTable( apiClient, tableName, {
			example: 'String',
			other: 'String'
		} );

		await CargoTestUtils.createPageWithData( apiClient, 'Test1', tableName, {
			example: 'Test1 Example Value',
			other: 'Test1 Other value'
		} );
		await CargoTestUtils.createPageWithData( apiClient, 'Test2', tableName, {
			example: 'Shared Example Value',
			other: 'Test2 Other value'
		} );
		await CargoTestUtils.createPageWithData( apiClient, 'Test3', tableName, {
			example: 'Shared Example Value',
			other: 'Test3 Other value'
		} );
	} );

	it( 'displays table name with proper page count', async () => {
		await DrilldownPage.open();

		const tableNames = await DrilldownPage.getTableNames();

		expect( tableNames ).toContain( 'CargoDrilldownTest (3)' );
	} );

	it( 'displays proper data when drilling down', async () => {
		await DrilldownPage.open();
		await DrilldownPage.selectTable( 'CargoDrilldownTest' );

		const pageNames = await DrilldownPage.getPageNames();
		expect( pageNames ).toEqual( [ 'Test1', 'Test2', 'Test3' ] );

		await DrilldownPage.applyValueFilter( 'Shared Example Value' );

		const pageNamesFiltered = await DrilldownPage.getPageNames();
		expect( pageNamesFiltered ).toEqual( [ 'Test2', 'Test3' ] );
	} );
} );

import assert from 'assert';
import Api from 'wdio-mediawiki/Api';
import CargoTablesPage from '../pageobjects/cargotables.page.js';
import CargoTestUtils from '../cargo-utils.js';

describe( 'Special:CargoTables', () => {
	before( async () => {
		const bot = await Api.bot();

		const tableName = 'CargoTablesTest';
		await CargoTestUtils.createTable( bot, tableName, {
			example: 'String'
		} );

		await CargoTestUtils.createPageWithData( bot, 'CargoTablesTest1', tableName, {
			example: 'foo'
		} );
		await CargoTestUtils.createPageWithData( bot, 'CargoTablesTest2', tableName, {
			example: 'bar'
		} );
	} );

	it( 'displays table information', async () => {
		await CargoTablesPage.open();

		assert.deepEqual( await CargoTablesPage.getRowCount( 'CargoTablesTest' ), '2' );
		assert.deepEqual( await CargoTablesPage.getColumnCount( 'CargoTablesTest' ), '1' );
		assert.deepEqual( await CargoTablesPage.getTemplateName( 'CargoTablesTest' ), 'Template:CargoTablesTest' );
	} );
} );

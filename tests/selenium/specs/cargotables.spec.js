'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	CargoTablesPage = require( '../pageobjects/cargotables.page' ),
	CargoTestUtils = require( '../cargo-utils' );

describe( 'Special:CargoTables', function () {
	before( async function () {
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

	it( 'displays table information', async function () {
		await CargoTablesPage.open();

		assert.deepEqual( await CargoTablesPage.getRowCount( 'CargoTablesTest' ), '2' );
		assert.deepEqual( await CargoTablesPage.getColumnCount( 'CargoTablesTest' ), '1' );
		assert.deepEqual( await CargoTablesPage.getTemplateName( 'CargoTablesTest' ), 'Template:CargoTablesTest' );
	} );
} );

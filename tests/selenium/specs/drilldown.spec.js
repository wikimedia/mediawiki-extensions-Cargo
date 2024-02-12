'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	DrilldownPage = require( '../pageobjects/drilldown.page' ),
	CargoTestUtils = require( '../cargo-utils' );

describe( 'Special:Drilldown', function () {
	before( async function () {
		const bot = await Api.bot();

		const tableName = 'CargoDrilldownTest';
		await CargoTestUtils.createTable( bot, tableName, {
			example: 'String',
			other: 'String'
		} );

		await CargoTestUtils.createPageWithData( bot, 'Test1', tableName, {
			example: 'Test1 Example Value',
			other: 'Test1 Other value'
		} );
		await CargoTestUtils.createPageWithData( bot, 'Test2', tableName, {
			example: 'Shared Example Value',
			other: 'Test2 Other value'
		} );
		await CargoTestUtils.createPageWithData( bot, 'Test3', tableName, {
			example: 'Shared Example Value',
			other: 'Test3 Other value'
		} );
	} );

	it( 'displays table name with proper page count', async function () {
		await DrilldownPage.open();

		const tableNames = await DrilldownPage.getTableNames();

		assert.ok( tableNames.includes( 'CargoDrilldownTest (3)' ) );
	} );

	it( 'displays proper data when drilling down', async function () {
		await DrilldownPage.open();
		await DrilldownPage.selectTable( 'CargoDrilldownTest' );

		const pageNames = await DrilldownPage.getPageNames();
		assert.deepEqual( pageNames, [ 'Test1', 'Test2', 'Test3' ] );

		await DrilldownPage.applyValueFilter( 'Shared Example Value' );

		const pageNamesFiltered = await DrilldownPage.getPageNames();
		assert.deepEqual( pageNamesFiltered, [ 'Test2', 'Test3' ] );
	} );
} );

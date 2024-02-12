'use strict';

const Page = require( 'wdio-mediawiki/Page' );

/**
 * WDIO page object for Special:CargoTables.
 */
class CargoTablesPage extends Page {

	get table() {
		return $( '.cargo-tablelist' );
	}

	/**
	 * Get the displayed row count of the given Cargo table.
	 *
	 * @param {string} tableName
	 * @return {Promise<string>}
	 */
	async getRowCount( tableName ) {
		return this.table.$( `*=${ tableName }` )
			.$( '..' )
			.$( '..' )
			.$( '.cargo-tablelist-numrows' )
			.getText();
	}

	/**
	 * Get the displayed column count of the given Cargo table.
	 *
	 * @param {string} tableName
	 * @return {Promise<string>}
	 */
	async getColumnCount( tableName ) {
		return this.table.$( `*=${ tableName }` )
			.$( '..' )
			.$( '..' )
			.$( '.cargo-tablelist-numcolumns' )
			.getText();
	}

	/**
	 * Get the name of the template declaring the given Cargo table.
	 *
	 * @param {string} tableName
	 * @return {Promise<string>}
	 */
	async getTemplateName( tableName ) {
		return this.table.$( `*=${ tableName }` )
			.$( '..' )
			.$( '..' )
			.$( '.cargo-tablelist-template-declaring' )
			.getText();
	}

	async open() {
		super.openTitle( 'Special:CargoTables' );
	}
}

module.exports = new CargoTablesPage();

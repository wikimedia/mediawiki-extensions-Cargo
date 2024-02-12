'use strict';

const Page = require( 'wdio-mediawiki/Page' );

/**
 * WDIO page object for Special:Drilldown.
 */
class DrilldownPage extends Page {

	get tables() {
		return $( '#drilldown-tables-tabs' );
	}

	get pages() {
		return $$( '.cargo-category-format-results a' );
	}

	get filters() {
		return $( '.drilldown-filter-values' );
	}

	/**
	 * Get the names of the tables displayed on Special:Drilldown.
	 *
	 * @return {Promise<string[]>}
	 */
	async getTableNames() {
		return this.tables.$$( 'a' ).map( ( e ) => e.getText() );
	}

	/**
	 * Get the names of the pages displayed on Special:Drilldown.
	 *
	 * @return {Promise<string[]>}
	 */
	async getPageNames() {
		return this.pages.map( ( e ) => e.getText() );
	}

	/**
	 * Select a table on Special:Drilldown.
	 *
	 * @param {string} tableName
	 */
	async selectTable( tableName ) {
		await this.tables.$( `*=${ tableName }` ).click();
	}

	/**
	 * Filter by a field value on Special:Drilldown.
	 *
	 * @param {string} value
	 * @return {Promise<void>}
	 */
	async applyValueFilter( value ) {
		await this.filters.$( `*=${ value }` ).click();
	}

	async open() {
		super.openTitle( 'Special:Drilldown' );
	}
}

module.exports = new DrilldownPage();

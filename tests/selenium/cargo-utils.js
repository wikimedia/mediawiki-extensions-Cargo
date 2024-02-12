'use strict';

/**
 * Utility functions for interacting with Cargo tables in E2E tests.
 */
class CargoTestUtils {
	/**
	 * Create a Cargo table with the given schema.
	 *
	 * @param {MWBot} bot Authenticated API client
	 * @param {string} tableName Cargo table name
	 * @param {Object} schema Field name => field type mapping
	 */
	async createTable( bot, tableName, schema ) {
		let tableDefinition = `{{#cargo_declare:_table=${ tableName }\n`;
		for ( const [ fieldName, fieldType ] of Object.entries( schema ) ) {
			tableDefinition += `|${ fieldName }=${ fieldType }\n`;
		}
		tableDefinition += '}}';

		const store = `{{#cargo_store:_table=${ tableName }}}`;
		const wikitext = `<noinclude>${ tableDefinition }</noinclude><includeonly>${ store }</includeonly>`;

		const { csrftoken } = await bot.getEditToken();

		await bot.edit( `Template:${ tableName }`, wikitext );
		await bot.request( {
			action: 'cargorecreatetables',
			template: tableName,
			token: csrftoken
		} );
	}

	/**
	 * Create a page that stores the given data in a Cargo table.
	 *
	 * @param {MWBot} bot Authenticated API client
	 * @param {string} title Page title
	 * @param {string} tableName Cargo table name
	 * @param {Object} pageData Data to store in the table (field name => value)
	 */
	async createPageWithData( bot, title, tableName, pageData ) {
		let templateInvocation = `{{${ tableName }\n`;
		for ( const [ fieldName, value ] of Object.entries( pageData ) ) {
			templateInvocation += `|${ fieldName }=${ value }\n`;
		}
		templateInvocation += '}}';

		await bot.edit( title, templateInvocation );
	}
}

module.exports = new CargoTestUtils();

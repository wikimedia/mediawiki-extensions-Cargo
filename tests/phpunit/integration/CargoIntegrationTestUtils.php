<?php

/**
 * Reusable test utilities for Cargo integration tests.
 */
class CargoIntegrationTestUtils {
	/**
	 * Get wikitext for declaring a Cargo table with a given schema and template parameters.
	 * @param string $tableName Name of the table to declare.
	 * @param array $schema Associative array mapping field names to field types.
	 * @return string Wikitext for declaring the table.
	 */
	public static function getCargoTableDeclaration( string $tableName, array $schema ): string {
		$declare = "{{#cargo_declare:_table=$tableName\n";
		foreach ( $schema as $field => $type ) {
			$declare .= "|$field=$type\n";
		}
		$declare .= '}}';

		// Make fields available as template parameters for easier data storage later.
		$store = "{{#cargo_store:_table=$tableName}}";

		return "<noinclude>$declare</noinclude>\n<includeonly>$store</includeonly>";
	}
}

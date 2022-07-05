<?php

/**
 * Class for exposing the parser functions for Cargo to Lua.
 * The functions are available via mw.ext.cargo Lua table.
 *
 * @author Yaron Koren.
 * @author Alexander Mashin.
 */
class CargoLuaLibrary extends Scribunto_LuaLibraryBase {

	/**
	 * Register two Lua bindings: mw.ext.cargo.query and mw.ext.cargo.format
	 * @return array|null
	 */
	public function register() {
		$lib = [
			'query' => [ $this, 'cargoQuery' ],
			'format' => [ $this, 'cargoFormat' ]
		];
		return $this->getEngine()->registerInterface( __DIR__ . '/../cargo.lua', $lib, [] );
	}

	/**
	 * Implementation of mw.ext.cargo.query.
	 *
	 * @param string $tables
	 * @param string $fields
	 * @param array|null $args
	 * @return array[]
	 * @throws MWException
	 * @throws Scribunto_LuaError
	 */
	public function cargoQuery( $tables, $fields, $args ): array {
		$this->checkType( 'query', 1, $tables, 'string' );
		$this->checkType( 'query', 2, $fields, 'string' );
		$this->checkTypeOptional( 'query', 3, $args, 'table', [] );

		if ( isset( $args['where'] ) ) {
			$where = $args['where'];
		} else {
			$where = null;
		}
		if ( isset( $args['join'] ) ) {
			$join = $args['join'];
		} else {
			$join = null;
		}
		if ( isset( $args['groupBy'] ) ) {
			$groupBy = $args['groupBy'];
		} else {
			$groupBy = null;
		}
		if ( isset( $args['having'] ) ) {
			$having = $args['having'];
		} else {
			$having = null;
		}
		if ( isset( $args['orderBy'] ) ) {
			$orderBy = $args['orderBy'];
		} else {
			$orderBy = null;
		}
		if ( isset( $args['limit'] ) ) {
			$limit = $args['limit'];
		} else {
			$limit = null;
		}
		if ( isset( $args['offset'] ) ) {
			$offset = $args['offset'];
		} else {
			$offset = null;
		}

		try {
			$query = CargoSQLQuery::newFromValues( $tables, $fields, $where, $join,
				$groupBy, $having, $orderBy, $limit, $offset );
			$rows = $query->run();
		} catch ( Exception $e ) {
			// Allow for error handling within Lua.
			throw new Scribunto_LuaError( $e->getMessage() );
		}

		$result = [];

		$fieldArray = CargoUtils::smartSplit( ',', $fields );

		$rowIndex = 1; // because Lua arrays start at 1
		foreach ( $rows as $row ) {
			$values = [];
			foreach ( $fieldArray as $fieldString ) {
				$alias = $query->getAliasForFieldString( $fieldString );
				if ( !isset( $row[$alias] ) ) {
					if ( !$GLOBALS["wgCargoLegacyNullLuaFieldsAsEmptyString"] ) {
						continue;
					}
					$row[$alias] = "";
				}
				$nameArray = CargoUtils::smartSplit( '=', $fieldString );
				$name = $nameArray[ count( $nameArray ) - 1 ];
				$values[$name] = htmlspecialchars_decode( $row[$alias] );
			}
			$result[$rowIndex++] = $values;
		}

		return [ $result ];
	}

	/**
	 * Implementation of mw.ext.cargo.formatTable().
	 *
	 * @param array[] $values A 2D row-based array of associative arrays corresponding to a Lua table.
	 * @param array $params Parameters, as passed to {{#cargo_query:}}.
	 * @return array [ [ 0 => string, 'noparse' => bool, 'isHTML' => bool ] ].
	 */
	public function cargoFormat( array $values, array $params ): array {
		$mappings = [];
		$rows = [];
		foreach ( self::convertLuaTableToArray( $values ) as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
				foreach ( $row as $key => $value ) {
					if ( !isset( $mappings[$key] ) ) {
						$mappings[$key] = $key;
					}
				}
			}
		}
		return [
			CargoDisplayFormat::formatArray( $this->getParser(), $rows, $mappings, self::convertLuaTableToArray( $params ) )
		];
	}

	/**
	 * Convert 1-based Lua table to 0-based PHP array.
	 *
	 * @param mixed $table
	 *
	 * @return mixed
	 */
	private static function convertLuaTableToArray( $table ) {
		if ( is_array( $table ) ) {
			$converted = [];
			foreach ( $table as $key => $value ) {
				if ( is_int( $key ) || is_string( $key ) ) {
					$new_key = is_int( $key ) ? $key - 1 : $key;
					$converted[$new_key] = self::convertLuaTableToArray( $value );
				}
			}
			return $converted;
		}
		return $table;
	}
}

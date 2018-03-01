<?php
/**
 * Class for the #cargo_declare parser function, as well as for the creation
 * (and re-creation) of Cargo database tables.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDeclare {

	/**
	 * "Reserved words" - terms that should not be used as table or field
	 * names, because they are reserved for SQL.
	 * (Some words here are much more likely to be used than others.)
	 * @TODO - currently this list only includes reserved words from
	 * MySQL; other DB systems' additional words (if any) should be added
	 * as well.
	 */
	private static $sqlReservedWords = array(
		'accessible', 'add', 'all', 'alter', 'analyze',
		'and', 'as', 'asc', 'asensitive', 'before',
		'between', 'bigint', 'binary', 'blob', 'both',
		'by', 'call', 'cascade', 'case', 'change',
		'char', 'character', 'check', 'collate', 'column',
		'condition', 'constraint', 'continue', 'convert', 'create',
		'cross', 'current_date', 'current_time', 'current_timestamp', 'current_user',
		'cursor', 'database', 'databases', 'day_hour', 'day_microsecond',
		'day_minute', 'day_second', 'dec', 'decimal', 'declare',
		'default', 'delayed', 'delete', 'desc', 'describe',
		'deterministic', 'distinct', 'distinctrow', 'div', 'double',
		'drop', 'dual', 'each', 'else', 'elseif',
		'enclosed', 'escaped', 'exists', 'exit', 'explain',
		'false', 'fetch', 'float', 'float4', 'float8',
		'for', 'force', 'foreign', 'from', 'fulltext',
		'generated', 'get', 'grant', 'group', 'having',
		'high_priority', 'hour_microsecond', 'hour_minute', 'hour_second', 'if',
		'ignore', 'in', 'index', 'infile', 'inner',
		'inout', 'insensitive', 'insert', 'int', 'int1',
		'int2', 'int3', 'int4', 'int8', 'integer',
		'interval', 'into', 'io_after_gtids', 'io_before_gtids', 'is',
		'iterate', 'join', 'key', 'keys', 'kill',
		'leading', 'leave', 'left', 'like', 'limit',
		'linear', 'lines', 'load', 'localtime', 'localtimestamp',
		'lock', 'long', 'longblob', 'longtext', 'loop',
		'low_priority', 'master_bind', 'master_ssl_verify_server_cert', 'match', 'maxvalue',
		'mediumblob', 'mediumint', 'mediumtext', 'middleint', 'minute_microsecond',
		'minute_second', 'mod', 'modifies', 'natural', 'no_write_to_binlog',
		'not', 'null', 'numeric', 'on', 'optimize',
		'optimizer_costs', 'option', 'optionally', 'or', 'order',
		'out', 'outer', 'outfile', 'partition', 'precision',
		'primary', 'procedure', 'purge', 'range', 'read',
		'read_write', 'reads', 'real', 'references', 'regexp',
		'release', 'rename', 'repeat', 'replace', 'require',
		'resignal', 'restrict', 'return', 'revoke', 'right',
		'rlike', 'schema', 'schemas', 'second_microsecond', 'select',
		'sensitive', 'separator', 'set', 'show', 'signal',
		'smallint', 'spatial', 'specific', 'sql', 'sql_big_result',
		'sql_calc_found_rows', 'sql_small_result', 'sqlexception', 'sqlstate', 'sqlwarning',
		'ssl', 'starting', 'stored', 'straight_join', 'table',
		'terminated', 'then', 'tinyblob', 'tinyint', 'tinytext',
		'to', 'trailing', 'trigger', 'true', 'undo',
		'union', 'unique', 'unlock', 'unsigned', 'update',
		'usage', 'use', 'using', 'utc_date', 'utc_time',
		'utc_timestamp', 'values', 'varbinary', 'varchar', 'varcharacter',
		'varying', 'virtual', 'when', 'where', 'while',
		'with', 'write', 'xor', 'year_month', 'zerofill'
	);

	/**
	 * Words that are similarly reserved for Cargo - thankfully, a much
	 * shorter list.
	 */
	private static $cargoReservedWords = array(
		'holds', 'matches', 'near', 'within'
	);

	/**
	 * Handles the #cargo_declare parser function.
	 *
	 * @todo Internationalize error messages
	 * @param Parser $parser
	 * @return string
	 */
	public static function run( &$parser ) {
		if ( $parser->getTitle()->getNamespace() != NS_TEMPLATE ) {
			return CargoUtils::formatError( "Error: #cargo_declare must be called from a template page." );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		$tableSchema = new CargoTableSchema();
		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
				if ( in_array( strtolower( $tableName ), self::$sqlReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$tableName\" cannot be used as a Cargo table name, because it is an SQL keyword." );
				} elseif ( in_array( strtolower( $tableName ), self::$cargoReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$tableName\" cannot be used as a Cargo table name, because it is already a Cargo keyword." );
				}
			} else {
				$fieldName = $key;
				$fieldDescriptionStr = $value;
				// Validate field name.
				if ( preg_match('/\s/', $fieldName ) ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains whitespaces. "
						. "Whitepaces of any kind are not allowed; consider using underscores (\"_\") instead." );
				} elseif ( strpos( $fieldName, '_' ) === 0 ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" begins with an "
						. "underscore; this is not allowed." );
				} elseif ( strpos( $fieldName, '__' ) !== false ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains more "
						. "than one underscore in a row; this is not allowed." );
				} elseif ( strpos( $fieldName, ',' ) !== false ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains a comma; "
						. "this is not allowed." );
				} elseif ( in_array( strtolower( $fieldName ), self::$sqlReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$fieldName\" cannot be used as a Cargo field name, because it is an SQL keyword." );
				} elseif ( in_array( strtolower( $fieldName ), self::$cargoReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$fieldName\" cannot be used as a Cargo field name, because it is already a Cargo keyword." );
				}
				try {
					$fieldDescription = CargoFieldDescription::newFromString( $fieldDescriptionStr );
				} catch ( Exception $e ) {
					return CargoUtils::formatError( $e->getMessage() );
				}
				if ( $fieldDescription == null ) {
					return CargoUtils::formatError( "Error: could not parse type for field \"$fieldName\"." );
				}
				if ( $fieldDescription->mIsHierarchy == true && $fieldDescription->mType == 'Coordinates' ) {
					return CargoUtils::formatError( "Error: Hierarchy enumeration field cannot be created for $fieldDescription->mType type." );
				}
				$tableSchema->mFieldDescriptions[$fieldName] = $fieldDescription;
			}
		}

		// Validate table name.
		if ( $tableName == '' ) {
			return CargoUtils::formatError( wfMessage( "cargo-notable" )->parse() );
		} elseif ( preg_match('/\s/', $tableName ) ) {
			return CargoUtils::formatError( "Error: Table name \"$tableName\" contains whitespaces. "
				. "Whitepaces of any kind are not allowed; consider using underscores (\"_\") instead." );
		} elseif ( strpos( $tableName, '_' ) === 0 ) {
			return CargoUtils::formatError( "Error: Table name \"$tableName\" begins with an "
				. "underscore; this is not allowed." );
		} elseif ( strpos( $tableName, '__' ) !== false ) {
			return CargoUtils::formatError( "Error: Table name \"$tableName\" contains more than one "
				. "underscore in a row; this is not allowed." );
		} elseif ( strpos( $tableName, ',' ) !== false ) {
			return CargoUtils::formatError( "Error: Table name \"$tableName\" contains a comma; "
				. "this is not allowed." );
		}

		$parserOutput = $parser->getOutput();

		$parserOutput->setProperty( 'CargoTableName', $tableName );
		$parserOutput->setProperty( 'CargoFields', $tableSchema->toDBString() );

		// Link to the Special:CargoTables page for this table, if it
		// exists already - otherwise, explain that it needs to be
		// created.
		$text = wfMessage( 'cargo-definestable', $tableName )->text();
		$cdb = CargoUtils::getDB();
		if ( $cdb->tableExists( $tableName ) ) {
			$ct = SpecialPage::getTitleFor( 'CargoTables' );
			$pageName = $ct->getPrefixedText() . "/$tableName";
			$viewTableMsg = wfMessage( 'cargo-cargotables-viewtablelink' )->parse();
			$text .= " [[$pageName|$viewTableMsg]].";
		} else {
			$text .= ' ' . wfMessage( 'cargo-tablenotcreated' )->text();
		}

		return $text;
	}

}
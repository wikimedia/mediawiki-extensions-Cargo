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
			} else {
				$fieldName = $key;
				$fieldDescriptionStr = $value;
				// Validate field name.
				if ( strpos( $fieldName, ' ' ) !== false ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains spaces. "
							. "Spaces are not allowed; consider using underscores(\"_\") instead." );
				} elseif ( strpos( $fieldName, '_' ) === 0 ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" begins with an "
							. "underscore; this is not allowed." );
				} elseif ( strpos( $fieldName, '__' ) !== false ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains more "
							. "than one underscore in a row; this is not allowed." );
				} elseif ( strpos( $fieldName, ',' ) !== false ) {
					return CargoUtils::formatError( "Error: Field name \"$fieldName\" contains a comma; "
							. "this is not allowed." );
				} elseif ( in_array( strtolower( $fieldName ),
					// Some of these are more likely as
					// field names than others...
					array( 'create', 'table', 'update', 'insert', 'delete',
						'select', 'distinct', 'from', 'join', 'on',
						'where', 'order', 'by', 'asc', 'desc', 'limit',
						'group', 'having', 'like', 'is', 'null',
						'not', 'and', 'or', 'between', 'merge',
						'union', 'intersect', 'except', 'case', 'if' ) ) ) {
					return CargoUtils::formatError( "Error: \"$fieldName\" cannot be used as a Cargo field name, because it is an SQL keyword." );
				} elseif ( in_array( strtolower( $fieldName ),
					// Similarly, handle the Cargo
					// pseudo-SQL keywords.
					array( 'holds', 'near' ) ) ) {
					return CargoUtils::formatError( "Error: \"$fieldName\" cannot be used as a Cargo field name, because it is already a Cargo keyword." );
				}

				$fieldDescription = CargoFieldDescription::newFromString( $fieldDescriptionStr );
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
			return CargoUtils::formatError( "Error: Table name must be specified." );
		} elseif ( strpos( $tableName, ' ' ) !== false ) {
			return CargoUtils::formatError( "Error: Table name \"$tableName\" contains spaces. "
					. "Spaces are not allowed; consider using underscores(\"_\") instead." );
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

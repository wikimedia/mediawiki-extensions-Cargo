<?php
/**
 * Background job to recreate the database table(s) for one template using the
 * data from the call(s) to that template in one page.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecreateTablesJob extends Job {

	/**
	 *
	 * @param Title $title
	 * @param array|bool $params
	 */
	function __construct( $title, $params = false ) {
		parent::__construct( 'cargoRecreateTables', $title, $params );
	}

	/**
	 * Run a CargoRecreateTables job.
	 *
	 * @return boolean success
	 */
	function run() {
		wfProfileIn( __METHOD__ );

		if ( is_null( $this->title ) ) {
			$this->error = "cargoRecreateTables: Invalid title";
			wfProfileOut( __METHOD__ );
			return false;
		}

		$templatePageID = $this->title->getArticleID();
		$success = self::recreateDBTablesForTemplate( $templatePageID );
		wfProfileOut( __METHOD__ );
		return $success;
	}

	/**
	 * Drop, and then create again, the database table(s) holding the
	 * data for this template.
	 * Why "tables"? Because every field that holds a list of values gets
	 * its own helper table.
	 *
	 * @global string $wgDBtype
	 * @param int $templatePageID
	 * @return boolean
	 * @throws MWException
	 */
	public static function recreateDBTablesForTemplate( $templatePageID ) {
		global $wgDBtype;

		$tableSchemaString = CargoUtils::getPageProp( $templatePageID, 'CargoFields' );
		// First, see if there even is DB storage for this template -
		// if not, exit.
		if ( is_null( $tableSchemaString ) ) {
			return false;
		}
		$tableSchema = CargoTableSchema::newFromDBString( $tableSchemaString );

		$dbr = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();

		$res = $dbr->select( 'cargo_tables', 'main_table', array( 'template_id' => $templatePageID ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$curTable = $row['main_table'];
			try {
				$cdb->dropTable( $curTable );
			} catch ( Exception $e ) {
				throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
				. "Please make sure that your database user account has the DROP permission." );
			}
			$dbr->delete( 'cargo_pages', array( 'table_name' => $curTable ) );
		}

		$dbr->delete( 'cargo_tables', array( 'template_id' => $templatePageID ) );

		$tableName = CargoUtils::getPageProp( $templatePageID, 'CargoTableName' );
		// Unfortunately, there is not yet a 'CREATE TABLE' wrapper
		// in the MediaWiki DB API, so we have to call SQL directly.
		$intTypeString = self::fieldTypeToSQLType( 'Integer', $wgDBtype );
		$textTypeString = self::fieldTypeToSQLType( 'Text', $wgDBtype );

		$createSQL = "CREATE TABLE " .
			$cdb->tableName( $tableName ) . ' ( ' .
			"_ID $intTypeString NOT NULL UNIQUE, " .
			"_pageName $textTypeString NOT NULL, " .
			"_pageTitle $textTypeString NOT NULL, " .
			"_pageNamespace $intTypeString NOT NULL, " .
			"_pageID $intTypeString NOT NULL";

		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$size = $fieldDescription->mSize;
			$isList = $fieldDescription->mIsList;
			$fieldType = $fieldDescription->mType;

			if ( $isList || $fieldType == 'Coordinates' ) {
				// No field will be created with this name -
				// instead, we'll have one called
				// fieldName + '__full', and a separate table
				// for holding each value.
				$createSQL .= ', ' . $fieldName . '__full ';
				// The field holding the full list will always
				// just be text
				$createSQL .= $textTypeString;
			} else {
				$createSQL .= ", $fieldName ";
				$createSQL .= self::fieldTypeToSQLType( $fieldType, $wgDBtype, $size );
			}

			if ( !$isList && $fieldType == 'Coordinates' ) {
				$floatTypeString = self::fieldTypeToSQLType( 'Float', $wgDBtype );
				$createSQL .= ', ' . $fieldName . '__lat ';
				$createSQL .= $floatTypeString;
				$createSQL .= ', ' . $fieldName . '__lon ';
				$createSQL .= $floatTypeString;
			} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
				$integerTypeString = self::fieldTypeToSQLType( 'Integer', $wgDBtype );
				$createSQL .= ', ' . $fieldName . '__precision ';
				$createSQL .= $integerTypeString;
			}
		}
		$createSQL .= ' )';

		//$cdb->ignoreErrors( true );
		$cdb->query( $createSQL );
		//$cdb->ignoreErrors( false );

		$createIndexSQL = "CREATE INDEX page_id_$tableName ON " . $cdb->tableName( $tableName ) . " (_pageID)";
		$cdb->query( $createIndexSQL );
		$createIndexSQL2 = "CREATE INDEX page_name_$tableName ON " . $cdb->tableName( $tableName ) . " (_pageName)";
		$cdb->query( $createIndexSQL2 );
		$createIndexSQL3 = "CREATE INDEX page_title_$tableName ON " . $cdb->tableName( $tableName ) . " (_pageTitle)";
		$cdb->query( $createIndexSQL3 );
		$createIndexSQL4 = "CREATE INDEX page_namespace_$tableName ON " . $cdb->tableName( $tableName )
			. " (_pageNamespace)";
		$cdb->query( $createIndexSQL4 );
		$createIndexSQL5 = "CREATE UNIQUE INDEX id_$tableName ON " . $cdb->tableName( $tableName ) . " (_ID)";
		$cdb->query( $createIndexSQL5 );

		// Now also create tables for each of the 'list' fields,
		// if there are any.
		$fieldTableNames = array();
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !$fieldDescription->mIsList ) {
				continue;
			}
			// The double underscore in this table name should
			// prevent anyone from giving this name to a "real"
			// table.
			$fieldTableName = $tableName . '__' . $fieldName;
			$cdb->dropTable( $fieldTableName );
			$fieldType = $fieldDescription->mType;
			$createSQL = "CREATE TABLE " .
				$cdb->tableName( $fieldTableName ) . ' ( ' .
				"_rowID $intTypeString, ";
			if ( $fieldType == 'Coordinates' ) {
				$floatTypeString = self::fieldTypeToSQLType( 'Float', $wgDBtype );
				$createSQL .= '_value ' . $floatTypeString . ', ';
				$createSQL .= '_lat ' . $floatTypeString . ', ';
				$createSQL .= '_lon ' . $floatTypeString;
			} else {
				$createSQL .= '_value ' . self::fieldTypeToSQLType( $fieldType, $wgDBtype, $size );
			}
			$createSQL .= ' )';
			$cdb->query( $createSQL );
			$createIndexSQL = "CREATE INDEX row_id_$fieldTableName ON " . $cdb->tableName( $fieldTableName ) . " (_rowID)";
			$cdb->query( $createIndexSQL );
			$fieldTableNames[] = $tableName . '__' . $fieldName;
		}

		// Finally, store all the info in the cargo_tables table.
		$dbr->insert( 'cargo_tables',
			array( 'template_id' => $templatePageID, 'main_table' => $tableName,
			'field_tables' => serialize( $fieldTableNames ), 'table_schema' => $tableSchemaString ) );
		return true;
	}

	public static function fieldTypeToSQLType( $fieldType, $dbType, $size = null ) {
		// Possible values for $dbType: "mssql", "mysql", "oracle",
		// "postgres", "sqlite"
		// @TODO - make sure it's one of these.
		if ( $fieldType == 'Integer' ) {
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
					return 'Int';
				case "sqlite":
					return 'INTEGER';
				case "oracle":
					return 'Number';
			}
		} elseif ( $fieldType == 'Float' ) {
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
					return 'Float';
				case "postgres":
					return 'Numeric';
				case "sqlite":
					return 'REAL';
				case "oracle":
					return 'Number';
			}
		} elseif ( $fieldType == 'Boolean' ) {
			switch ( $dbType ) {
				case "mssql":
					return 'Bit';
				case "mysql":
				case "postgres":
					return 'Boolean';
				case "sqlite":
					return 'INTEGER';
				case "oracle":
					return 'Byte';
			}
		} elseif ( $fieldType == 'Date' ) {
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
				case "oracle":
					return 'Date';
				case "sqlite":
					// Should really be 'REAL', with
					// accompanying handling.
					return 'TEXT';
			}
		} elseif ( $fieldType == 'Datetime' ) {
			// Some DB types have a datetime type that includes
			// the time zone, but MySQL unfortunately doesn't,
			// so the best solution for time zones is probably
			// to have a separate field for them.
			switch ( $dbType ) {
				case "mssql":
					return 'Datetime2';
				case "mysql":
					return 'Datetime';
				case "postgres":
				case "oracle":
					return 'Timestamp';
				case "sqlite":
					// Should really be 'REAL', with
					// accompanying handling.
					return 'TEXT';
			}
		} else { // 'Text', 'Page', etc.
			if ( $size == null ) {
				$size = 300;
			}
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
				case "oracle":
					return "Varchar($size)";
				case "sqlite":
					return 'TEXT';
			}
		}
	}

}

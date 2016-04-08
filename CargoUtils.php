<?php
/**
 * CargoUtils - utility functions for the Cargo extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoUtils {

	/**
	 *
	 * @global string $wgDBuser
	 * @global string $wgDBpassword
	 * @global string $wgCargoDBserver
	 * @global string $wgCargoDBname
	 * @global string $wgCargoDBuser
	 * @global string $wgCargoDBpassword
	 * @global string $wgCargoDBtype
	 * @return DatabaseBase
	 */
	public static function getDB() {
		global $wgDBuser, $wgDBpassword, $wgDBprefix;
		global $wgCargoDBserver, $wgCargoDBname, $wgCargoDBuser, $wgCargoDBpassword, $wgCargoDBtype;
		$dbw = wfGetDB( DB_MASTER );
		$server = $dbw->getServer();
		$name = $dbw->getDBname();
		$type = $dbw->getType();

		$dbType = is_null( $wgCargoDBtype ) ? $type : $wgCargoDBtype;
		$dbServer = is_null( $wgCargoDBserver ) ? $server : $wgCargoDBserver;
		$dbUsername = is_null( $wgCargoDBuser ) ? $wgDBuser : $wgCargoDBuser;
		$dbPassword = is_null( $wgCargoDBpassword ) ? $wgDBpassword : $wgCargoDBpassword;
		$dbName = is_null( $wgCargoDBname ) ? $name : $wgCargoDBname;
		$dbFlags = DBO_DEFAULT;
		$dbTablePrefix = $wgDBprefix . 'cargo__';

		$db = DatabaseBase::factory( $dbType,
				array(
				'host' => $dbServer,
				'user' => $dbUsername,
				'password' => $dbPassword,
				'dbname' => $dbName,
				'flags' => $dbFlags,
				'tablePrefix' => $dbTablePrefix,
				)
		);
		return $db;
	}

	/**
	 * Gets a page property for the specified page ID and property name.
	 */
	public static function getPageProp( $pageID, $pageProp ) {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'page_props', array(
			'pp_value'
			), array(
			'pp_page' => $pageID,
			'pp_propname' => $pageProp,
			)
		);

		if ( !$row = $dbw->fetchRow( $res ) ) {
			return null;
		}

		return $row['pp_value'];
	}

	/**
	 * Similar to getPageProp().
	 */
	public static function getAllPageProps( $pageProp ) {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'page_props', array(
			'pp_page',
			'pp_value'
			), array(
			'pp_propname' => $pageProp
			)
		);

		$pagesPerValue = array();
		while ( $row = $dbw->fetchRow( $res ) ) {
			$pageID = $row['pp_page'];
			$pageValue = $row['pp_value'];
			if ( array_key_exists( $pageValue, $pagesPerValue ) ) {
				$pagesPerValue[$pageValue][] = $pageID;
			} else {
				$pagesPerValue[$pageValue] = array( $pageID );
			}
		}

		return $pagesPerValue;
	}

	/**
	 * Gets the template page where this table is defined -
	 * hopefully there's exactly one of them.
	 */
	public static function getTemplateIDForDBTable( $tableName ) {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'page_props', array(
			'pp_page'
			), array(
			'pp_value' => $tableName,
			'pp_propname' => 'CargoTableName'
			)
		);
		if ( !$row = $dbw->fetchRow( $res ) ) {
			return null;
		}
		return $row['pp_page'];
	}

	public static function formatError( $errorString ) {
		return '<div class="error">' . $errorString . '</div>';
	}

	public static function getTables() {
		$tableNames = array();
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_tables', 'main_table' );
		while ( $row = $dbw->fetchRow( $res ) ) {
			$tableNames[] = $row[0];
		}
		return $tableNames;
	}

	static function getTableSchemas( $tableNames ) {
		$mainTableNames = array();
		foreach ( $tableNames as $tableName ) {
			if ( strpos( $tableName, '__' ) !== false ) {
				// We just want the first part of it.
				$tableNameParts = explode( '__', $tableName );
				$tableName = $tableNameParts[0];
			}
			if ( !in_array( $tableName, $mainTableNames ) ) {
				$mainTableNames[] = $tableName;
			}
		}
		$tableSchemas = array();
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_tables', array( 'main_table', 'table_schema' ),
			array( 'main_table' => $mainTableNames ) );
		while ( $row = $dbw->fetchRow( $res ) ) {
			$tableName = $row['main_table'];
			$tableSchemaString = $row['table_schema'];
			$tableSchemas[$tableName] = CargoTableSchema::newFromDBString( $tableSchemaString );
		}

		// Validate the table names.
		if ( count( $tableSchemas ) < count( $mainTableNames ) ) {
			foreach ( $mainTableNames as $tableName ) {
				if ( !array_key_exists( $tableName, $tableSchemas ) ) {
					throw new MWException( "Error: table \"$tableName\" not found." );
				}
			}
		}

		return $tableSchemas;
	}

	/**
	 * Get the Cargo table for the passed-in template specified via
	 * either #cargo_declare or #cargo_attach, if the template has a
	 * call to either one.
	 */
	static function getTableNameForTemplate( $templateTitle ) {
		$templatePageID = $templateTitle->getArticleID();
		$declaredTableName = self::getPageProp( $templatePageID, 'CargoTableName' );
		if ( !is_null( $declaredTableName ) ) {
			return array( $declaredTableName, true );
		}
		$attachedTableName = self::getPageProp( $templatePageID, 'CargoAttachedTable' );
		return array( $attachedTableName, false );
	}

	static function tableExists( $tableName ) {
		$cdb = self::getDB();
		try {
			$cdb->select( $tableName, '*', null, null, array( 'LIMIT' => 1 ) );
		} catch ( Exception $e ) {
			return false;
		}
		return true;
	}

	/**
	 * Splits a string by the delimiter, but ensures that parenthesis, separators
	 * and "the other quote" (single quote in a double quoted string or double
	 * quote in a single quoted string) inside a quoted string are not considered
	 * lexically.
	 */
	static function smartSplit( $delimiter, $string ) {
		if ( $string == '' ) {
			return array();
		}

		$ignoreNextChar = false;
		$returnValues = array();
		$numOpenParentheses = 0;
		$curReturnValue = '';

		for ( $i = 0; $i < strlen( $string ); $i++ ) {
			$curChar = $string{$i};

			if ( $ignoreNextChar ) {
				// If previous character was a backslash,
				// ignore the current one, since it's escaped.
				// What if this one is a backslash too?
				// Doesn't matter - it's escaped.
				$ignoreNextChar = false;
			} elseif ( $curChar == '(' ) {
				$numOpenParentheses++;
			} elseif ( $curChar == ')' ) {
				$numOpenParentheses--;
			} elseif ( $curChar == '\'' || $curChar == '"' ) {
				$pos = CargoUtils::findQuotedStringEnd($string, $curChar, $i + 1 );
				if ( $pos === false ) {
					throw new MWException( "Error: unmatched quote in SQL string constant." );
				}
				$curReturnValue .= substr( $string, $i, $pos - $i );
				$i = $pos;
			} elseif ( $curChar == '\\' ) {
				$ignoreNextChar = true;
			}

			if ( $curChar == $delimiter && $numOpenParentheses == 0 ) {
				$returnValues[] = trim( $curReturnValue );
				$curReturnValue = '';
			} else {
				$curReturnValue .= $curChar;
			}
		}
		$returnValues[] = trim( $curReturnValue );

		if ( $ignoreNextChar ) {
			throw new MWException( "Error: incomplete escape sequence." );
		}

		return $returnValues;
	}

	/**
	* find the end of a quoted string
	*/
	public static function findQuotedStringEnd( $string, $quoteChar, $pos ) {
		$ignoreNextChar = false;
		for ( $i = $pos ; $i < strlen($string) ; $i++ ) {
			$curChar = $string{$i};
			if ( $ignoreNextChar ) {
				$ignoreNextChar = false;
			} elseif ( $curChar == $quoteChar ) {
				if ( $i + 1 < strlen($string) && $string{$i + 1} == $quoteChar ) {
					$i++;
				} else {
					return $i;
				}
			} elseif ( $curChar == '\\' ) {
				$ignoreNextChar = true;
			}
		}
		if ( $ignoreNextChar ) {
			throw new MWException( "Error: incomplete escape sequence." );
		}
		return false;
	}

	/**
	* Delete text within quotes and raise exception if a quoted string
	* is not closed.
	*/
	public static function removeQuotedStrings( $string ) {
		$noQuotesPattern = '/("|\')([^\\1\\\\]|\\\\.)*?\\1/';
		$string = preg_replace( $noQuotesPattern, '', $string );
		if ( strpos($string,'"') !== false || strpos($string,"'") !== false ) {
			throw new MWException( "Error: unclosed string literal." );
		}
		return $string;
	}

	/**
	 * Generates a Regular Expression to match $fieldName in a SQL string.
	 * Allows for $ as valid identifier character.
	 */
	public static function getSQLFieldPattern( $fieldName, $closePattern = true ) {
		$fieldName = str_replace( '$', '\$', $fieldName );
		$pattern = '/([^\w$.,]|^)' . $fieldName;
		return $pattern . ( $closePattern ? '([^\w$]|$)/i' : '' );
	}

	/**
	 * Generates a Regular Expression to match $tableName.$fieldName in a
	 * SQL string. Allows for $ as valid identifier character.
	 */
	public static function getSQLTableAndFieldPattern( $tableName, $fieldName, $closePattern = true ) {
		$fieldName = str_replace( '$', '\$', $fieldName );
		$tableName = str_replace( '$', '\$', $tableName );
		$pattern = '/([^\w$,]|^)' . $tableName . '\.' . $fieldName;
		return $pattern . ( $closePattern ? '([^\w$]|$)/i' : '' );
	}

	/**
	 * Generates a Regular Expression to match $tableName in a SQL string.
	 * Allows for $ as valid identifier character.
	 */
	public static function getSQLTablePattern( $tableName, $closePattern = true ) {
		$tableName = str_replace( '$', '\$', $tableName );
		$pattern = '/([^\w$]|^)(' . $tableName . ')\.(\w*)';
		return $pattern . ( $closePattern ? '/i' : '' );
	}

	/**
	 * Parse a piece of wikitext differently depending on whether
	 * we're in a special or regular page.
	 *
	 * @global WebRequest $wgRequest
	 * @global Parser $wgParser
	 * @param string $value
	 * @param Parser $parser
	 * @return string
	 */
	public static function smartParse( $value, $parser ) {
		// This decode() call is here in case the value was
		// set using {{PAGENAME}}, which for some reason
		// HTML-encodes some of its characters - see
		// https://www.mediawiki.org/wiki/Help:Magic_words#Page_names
		// Of course, String and Page fields could be set using
		// {{PAGENAME}} as well, but those seem less likely.
		$value = htmlspecialchars_decode( $value );
		// Parse it as if it's wikitext. The exact call
		// depends on whether we're in a special page or not.
		global $wgRequest;
		if ( is_null( $parser ) ) {
			global $wgParser;
			$parser = $wgParser;
		}
		$title = $parser->getTitle();
		if ( is_null( $title ) ) {
			global $wgTitle;
			$title = $wgTitle;
		}

		if ( $title != null && $title->isSpecial( 'RunJobs' ) ) {
			// Conveniently, if this is called from within a job
			// being run, the name of the page will be
			// Special:RunJobs.
			// If that's the case, do nothing - we don't need to
			// parse the value.
		} elseif ( ( $title != null && $title->isSpecialPage() ) ||
			// The 'pagevalues' action is also a Cargo special page.
			$wgRequest->getVal( 'action' ) == 'pagevalues' ) {
			$parserOutput = $parser->parse( $value, $title, new ParserOptions(), false );
			$value = $parserOutput->getText();
		} else {
			$value = $parser->internalParse( $value );
		}
		return $value;
	}

	public static function parsePageForStorage( $title, $pageContents ) {
		// @TODO - is there a "cleaner" way to get a page to be parsed?
		global $wgParser;

		// Special handling for the Approved Revs extension.
		$pageText = null;
		$approvedText = null;
		if ( class_exists( 'ApprovedRevs' ) ) {
			$approvedText = ApprovedRevs::getApprovedContent( $title );
		}
		if ( $approvedText != null ) {
			$pageText = $approvedText;
		} else {
			$pageText = $pageContents;
		}
		$wgParser->parse( $pageText, $title, new ParserOptions() );
	}

	/**
	 * Drop, and then create again, the database table(s) holding the
	 * data for this template.
	 * Why "tables"? Because every field that holds a list of values gets
	 * its own helper table.
	 *
	 * @param int $templatePageID
	 * @return boolean
	 * @throws MWException
	 */
	public static function recreateDBTablesForTemplate( $templatePageID, $tableName = null ) {
		$tableSchemaString = self::getPageProp( $templatePageID, 'CargoFields' );
		// First, see if there even is DB storage for this template -
		// if not, exit.
		if ( is_null( $tableSchemaString ) ) {
			return false;
		}
		$tableSchema = CargoTableSchema::newFromDBString( $tableSchemaString );

		$dbw = wfGetDB( DB_MASTER );
		$cdb = self::getDB();

		$res = $dbw->select( 'cargo_tables', 'main_table', array( 'template_id' => $templatePageID ) );
		while ( $row = $dbw->fetchRow( $res ) ) {
			$curTable = $row['main_table'];
			try {
				$cdb->dropTable( $curTable );
			} catch ( Exception $e ) {
				throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
				. "Please make sure that your database user account has the DROP permission." );
			}
			$dbw->delete( 'cargo_pages', array( 'table_name' => $curTable ) );
		}

		$dbw->delete( 'cargo_tables', array( 'template_id' => $templatePageID ) );

		if ( $tableName == null ) {
			$tableName = self::getPageProp( $templatePageID, 'CargoTableName' );
		}

		self::createCargoTableOrTables( $cdb, $dbw, $tableName, $tableSchema, $tableSchemaString, $templatePageID );

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
		} elseif ( $fieldType == 'Text' ) {
			// This one is simple.
			return 'Text';
		} elseif ( $fieldType == 'Searchtext' ) {
			if ( $dbType != 'mysql' ) {
				throw new MWException( "Error: a \"Searchtext\" field can currently only be defined for MySQL databases." );
			}
			return 'Text';
		} else { // 'String', 'Page', etc.
			if ( $size == null ) {
				$size = 300;
			}
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
					// For at least MySQL, there's a limit
					// on how many total bytes a table's
					// fields can have, and "Text" and
					// "Blob" fields don't get added to the
					// total, so if it's a big piece of
					// text, just make it a "Text" field.
					if ( $size > 1000 ) {
						return 'Text';
					} else {
						return "Varchar($size)";
					}
				case "oracle":
					return "Varchar($size)";
				case "sqlite":
					return 'TEXT';
			}
		}
	}

	public static function createCargoTableOrTables( $cdb, $dbw, $tableName, $tableSchema, $tableSchemaString, $templatePageID ) {
		// Unfortunately, there is not yet a 'CREATE TABLE' wrapper
		// in the MediaWiki DB API, so we have to call SQL directly.
		$dbType = $cdb->getType();
		$intTypeString = self::fieldTypeToSQLType( 'Integer', $dbType );
		$stringTypeString = self::fieldTypeToSQLType( 'String', $dbType );

		$createSQL = "CREATE TABLE " .
			$cdb->tableName( $tableName ) . ' ( ' .
			$cdb->addIdentifierQuotes( '_ID' ) . " $intTypeString NOT NULL UNIQUE, " .
			$cdb->addIdentifierQuotes( '_pageName' ) . " $stringTypeString NOT NULL, " .
			$cdb->addIdentifierQuotes( '_pageTitle' ) . " $stringTypeString NOT NULL, " .
			$cdb->addIdentifierQuotes( '_pageNamespace' ) . " $intTypeString NOT NULL, " .
			$cdb->addIdentifierQuotes( '_pageID' ) . " $intTypeString NOT NULL";

		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$size = $fieldDescription->mSize;
			$isList = $fieldDescription->mIsList;
			$fieldType = $fieldDescription->mType;

			if ( $isList || $fieldType == 'Coordinates' ) {
				// No field will be created with this name -
				// instead, we'll have one called
				// fieldName + '__full', and a separate table
				// for holding each value.
				$createSQL .= ', ' . $cdb->addIdentifierQuotes( $fieldName . '__full' ) . ' ';
				// The field holding the full list will always
				// just be text
				$createSQL .= $stringTypeString;
			} else {
				$createSQL .= ', ' . $cdb->addIdentifierQuotes( $fieldName ) . ' ';
				$createSQL .= self::fieldTypeToSQLType( $fieldType, $dbType, $size );
			}

			if ( !$isList && $fieldType == 'Coordinates' ) {
				$floatTypeString = self::fieldTypeToSQLType( 'Float', $dbType );
				$createSQL .= ', ' . $cdb->addIdentifierQuotes( $fieldName . '__lat' ) . ' ';
				$createSQL .= $floatTypeString;
				$createSQL .= ', ' . $cdb->addIdentifierQuotes( $fieldName . '__lon' ) . ' ';
				$createSQL .= $floatTypeString;
			} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
				$integerTypeString = self::fieldTypeToSQLType( 'Integer', $dbType );
				$createSQL .= ", " . $cdb->addIdentifierQuotes( $fieldName . '__precision' ) . ' ';
				$createSQL .= $integerTypeString;
			} elseif ( $fieldType == 'Searchtext' ) {
				$createSQL .= ", FULLTEXT KEY $fieldName (" . $cdb->addIdentifierQuotes( $fieldName ) . ')';
			}
		}
		$createSQL .= ' )';

		//$cdb->ignoreErrors( true );
		$cdb->query( $createSQL );
		//$cdb->ignoreErrors( false );

		$createIndexSQL = "CREATE INDEX page_id_$tableName ON " . $cdb->tableName( $tableName ) .
			' (' . $cdb->addIdentifierQuotes( '_pageID' ) . ')';
		$cdb->query( $createIndexSQL );
		$createIndexSQL2 = "CREATE INDEX page_name_$tableName ON " . $cdb->tableName( $tableName ) .
			' (' . $cdb->addIdentifierQuotes( '_pageName' ) . ')';
		$cdb->query( $createIndexSQL2 );
		$createIndexSQL3 = "CREATE INDEX page_title_$tableName ON " . $cdb->tableName( $tableName ) .
			' (' . $cdb->addIdentifierQuotes( '_pageTitle' ) . ')';
		$cdb->query( $createIndexSQL3 );
		$createIndexSQL4 = "CREATE INDEX page_namespace_$tableName ON " . $cdb->tableName( $tableName )
			. ' (' . $cdb->addIdentifierQuotes( '_pageNamespace' ) . ')';
		$cdb->query( $createIndexSQL4 );
		$createIndexSQL5 = "CREATE UNIQUE INDEX id_$tableName ON " . $cdb->tableName( $tableName ) .
			' (' . $cdb->addIdentifierQuotes( '_ID' ) . ')';
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
				$cdb->addIdentifierQuotes( '_rowID' ) . " $intTypeString, ";
			if ( $fieldType == 'Coordinates' ) {
				$floatTypeString = self::fieldTypeToSQLType( 'Float', $dbType );
				$createSQL .= $cdb->addIdentifierQuotes( '_value' ) . " $floatTypeString, ";
				$createSQL .= $cdb->addIdentifierQuotes( '_lat' ) . " $floatTypeString, ";
				$createSQL .= $cdb->addIdentifierQuotes( '_lon' ) . " $floatTypeString";
			} else {
				$createSQL .= $cdb->addIdentifierQuotes( '_value' ) . ' ' . self::fieldTypeToSQLType( $fieldType, $dbType, $size );
			}
			$createSQL .= ' )';
			$cdb->query( $createSQL );
			$createIndexSQL = "CREATE INDEX row_id_$fieldTableName ON " .
				$cdb->tableName( $fieldTableName ) .
				' (' . $cdb->addIdentifierQuotes( '_rowID' ) . ')';
			$cdb->query( $createIndexSQL );
			$fieldTableNames[] = $tableName . '__' . $fieldName;
		}

		// Necessary in some cases.
		$cdb->close();

		// Finally, store all the info in the cargo_tables table.
		$dbw->insert( 'cargo_tables', array(
			'template_id' => $templatePageID,
			'main_table' => $tableName,
			'field_tables' => serialize( $fieldTableNames ),
			'table_schema' => $tableSchemaString
		) );
	}

	/**
	 * Parses one half of a set of coordinates into a number.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function coordinatePartToNumber( $coordinateStr ) {
		$degreesSymbols = array( "\x{00B0}", "d" );
		$minutesSymbols = array( "'", "\x{2032}", "\x{00B4}" );
		$secondsSymbols = array( '"', "\x{2033}", "\x{00B4}\x{00B4}" );

		$numDegrees = null;
		$numMinutes = null;
		$numSeconds = null;

		foreach ( $degreesSymbols as $degreesSymbol ) {
			$pattern = '/([\d\.]+)' . $degreesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numDegrees = floatval( $matches[1] );
				break;
			}
		}
		if ( $numDegrees == null ) {
			throw new MWException( "Error: could not parse degrees in \"$coordinateStr\"." );
		}

		foreach ( $minutesSymbols as $minutesSymbol ) {
			$pattern = '/([\d\.]+)' . $minutesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numMinutes = floatval( $matches[1] );
				break;
			}
		}
		if ( $numMinutes == null ) {
			// This might not be an error - the number of minutes
			// might just not have been set.
			$numMinutes = 0;
		}

		foreach ( $secondsSymbols as $secondsSymbol ) {
			$pattern = '/(\d+)' . $secondsSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numSeconds = floatval( $matches[1] );
				break;
			}
		}
		if ( $numSeconds == null ) {
			// This might not be an error - the number of seconds
			// might just not have been set.
			$numSeconds = 0;
		}

		return ( $numDegrees + ( $numMinutes / 60 ) + ( $numSeconds / 3600 ) );
	}

	/**
	 * Parses a coordinate string in (hopefully) any standard format.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function parseCoordinatesString( $coordinatesString ) {
		$coordinatesString = trim( $coordinatesString );
		if ( $coordinatesString == null ) {
			return;
		}

		// This is safe to do, right?
		$coordinatesString = str_replace( array( '[', ']' ), '', $coordinatesString );
		// See if they're separated by commas.
		if ( strpos( $coordinatesString, ',' ) > 0 ) {
			$latAndLonStrings = explode( ',', $coordinatesString );
		} else {
			// If there are no commas, the first half, for the
			// latitude, should end with either 'N' or 'S', so do a
			// little hack to split up the two halves.
			$coordinatesString = str_replace( array( 'N', 'S' ), array( 'N,', 'S,' ), $coordinatesString );
			$latAndLonStrings = explode( ',', $coordinatesString );
		}

		if ( count( $latAndLonStrings ) != 2 ) {
			throw new MWException( "Error parsing coordinates string: \"$coordinatesString\"." );
		}
		list( $latString, $lonString ) = $latAndLonStrings;

		// Handle strings one at a time.
		$latIsNegative = false;
		if ( strpos( $latString, 'S' ) > 0 ) {
			$latIsNegative = true;
		}
		$latString = str_replace( array( 'N', 'S' ), '', $latString );
		if ( is_numeric( $latString ) ) {
			$latNum = floatval( $latString );
		} else {
			$latNum = self::coordinatePartToNumber( $latString );
		}
		if ( $latIsNegative ) {
			$latNum *= -1;
		}

		$lonIsNegative = false;
		if ( strpos( $lonString, 'W' ) > 0 ) {
			$lonIsNegative = true;
		}
		$lonString = str_replace( array( 'E', 'W' ), '', $lonString );
		if ( is_numeric( $lonString ) ) {
			$lonNum = floatval( $lonString );
		} else {
			$lonNum = self::coordinatePartToNumber( $lonString );
		}
		if ( $lonIsNegative ) {
			$lonNum *= -1;
		}

		return array( $latNum, $lonNum );
	}

	public static function escapedFieldName( $cdb, $tableName, $fieldName ) {
		return $cdb->tableName( $tableName ) . '.' .
			$cdb->addIdentifierQuotes( $fieldName );
	}

	public static function joinOfMainAndFieldTable( $cdb, $mainTableName, $fieldTableName ) {
		return array(
			'LEFT OUTER JOIN',
			self::escapedFieldName( $cdb, $mainTableName, '_ID' ) .
				' = ' .
				self::escapedFieldName( $cdb, $fieldTableName, '_rowID' )
		);
	}

}

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
		global $wgDBuser, $wgDBpassword;
		global $wgCargoDBserver, $wgCargoDBname, $wgCargoDBuser, $wgCargoDBpassword, $wgCargoDBtype;
		$dbr = wfGetDB( DB_SLAVE );
		$server = $dbr->getServer();
		$name = $dbr->getDBname();
		$type = $dbr->getType();

		$dbType = is_null( $wgCargoDBtype ) ? $type : $wgCargoDBtype;
		$dbServer = is_null( $wgCargoDBserver ) ? $server : $wgCargoDBserver;
		$dbUsername = is_null( $wgCargoDBuser ) ? $wgDBuser : $wgCargoDBuser;
		$dbPassword = is_null( $wgCargoDBpassword ) ? $wgDBpassword : $wgCargoDBpassword;
		$dbName = is_null( $wgCargoDBname ) ? $name : $wgCargoDBname;
		$dbFlags = DBO_DEFAULT;
		$dbTablePrefix = 'cargo__';

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
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props', array(
			'pp_value'
			), array(
			'pp_page' => $pageID,
			'pp_propname' => $pageProp,
			)
		);

		if ( !$row = $dbr->fetchRow( $res ) ) {
			return null;
		}

		return $row['pp_value'];
	}

	public static function formatError( $errorString ) {
		return '<div class="error">' . $errorString . '</div>';
	}

	public static function getTables() {
		$tableNames = array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', 'main_table' );
		while ( $row = $dbr->fetchRow( $res ) ) {
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
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'table_schema' ),
			array( 'main_table' => $mainTableNames ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$tableName = $row['main_table'];
			$tableSchemaString = $row['table_schema'];
			$tableSchemas[$tableName] = CargoTableSchema::newFromDBString( $tableSchemaString );
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
	 * Splits a string by the delimiter, but ignores delimiters contained
	 * within parentheses.
	 */
	static function smartSplit( $delimiter, $string ) {
		if ( $string == '' ) {
			return array();
		}

		$returnValues = array();
		$numOpenParentheses = 0;
		$curReturnValue = '';

		for ( $i = 0; $i < strlen( $string ); $i++ ) {
			$curChar = $string{$i};
			if ( $curChar == '(' ) {
				$numOpenParentheses++;
			} elseif ( $curChar == ')' ) {
				$numOpenParentheses--;
			}

			if ( $curChar == $delimiter && $numOpenParentheses == 0 ) {
				$returnValues[] = trim( $curReturnValue );
				$curReturnValue = '';
			} else {
				$curReturnValue .= $curChar;
			}
		}
		$returnValues[] = trim( $curReturnValue );

		return $returnValues;
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
		// Of course, Text and Page fields could be set using
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

}

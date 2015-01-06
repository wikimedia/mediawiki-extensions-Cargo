<?php
/**
 * Class for the #cargo_attach parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoAttach {

	/**
	 * Handles the #cargo_attach parser function.
	 */
	public static function run( &$parser ) {
		if ( $parser->getTitle()->getNamespace() != NS_TEMPLATE ) {
			return CargoUtils::formatError( "Error: #cargo_attach must be called from a template page." );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );
			
			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
			}
		}

		// Validate table name.
		if ( $tableName == '' ) {
			return CargoUtils::formatError( "Error: Table name must be specified." );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', 'COUNT(*)', array( 'main_table' => $tableName ) );
		$row = $dbr->fetchRow( $res );
		if ( $row[0] == 0 ) {
			return CargoUtils::formatError( "Error: The specified table, \"$tableName\", does not exist." );
		}

		$parserOutput = $parser->getOutput();
		$parserOutput->setProperty( 'CargoAttachedTable', $tableName );

		// Link to the Special:ViewTable page for this table.
		$text = wfMessage( 'cargo-addsrows', $tableName )->text();
		$ct = SpecialPage::getTitleFor( 'CargoTables' );
		$pageName = $ct->getPrefixedText() . "/$tableName";
		$viewTableMsg = wfMessage( 'cargo-cargotables-viewtablelink' )->parse();
		$text .= " [[$pageName|$viewTableMsg]].";

		return $text;
	}

}

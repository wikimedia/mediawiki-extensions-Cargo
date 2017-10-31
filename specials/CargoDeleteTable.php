<?php
/**
 * An interface to delete a "Cargo table", which can be one or more real
 * database tables.
 *
 * The class is called CargoDeleteCargoTable, the file is called
 * CargoDeleteTable.php, and the wiki page is Special:DeleteCargoTable...
 * sorry for the confusion!
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDeleteCargoTable extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'DeleteCargoTable', 'deletecargodata' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * The table being deleted here is a Cargo table, not a DB table per
	 * se - a Cargo table corresponds to a main DB table, plus
	 * potentially one or more helper tables; all need to be deleted.
	 * Also, records need to be removed from the cargo_tables and
	 * cargo_pages tables.
	 */
	public static function deleteTable( $mainTable, $fieldTables, $fieldHelperTables ) {
		$cdb = CargoUtils::getDB();
		try {
			$cdb->dropTable( $mainTable );
			foreach ( $fieldTables as $fieldTable ) {
				$cdb->dropTable( $fieldTable );
			}
			if ( is_array( $fieldHelperTables ) ) {
				foreach ( $fieldHelperTables as $fieldHelperTable ) {
					$cdb->dropTable( $fieldHelperTable );
				}
			}
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
			. "Please make sure that your database user account has the DROP permission." );
		}
		$cdb->close();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'cargo_tables', array( 'main_table' => $mainTable ) );
		$dbw->delete( 'cargo_pages', array( 'table_name' => $mainTable ) );
	}

	function execute( $subpage = false ) {
		$out = $this->getOutput();
		$req = $this->getRequest();

		$this->setHeaders();
		if ( $subpage == '' ) {
			$out->addHTML( CargoUtils::formatError( wfMessage( "cargo-notable" )->parse() ) );
			return true;
		}

		$replacementTable = $req->getCheck( '_replacement' );
		$origTableName = $subpage;
		if ( $replacementTable ) {
			$tableName = $subpage . '__NEXT';
		} else {
			$tableName = $subpage;
		}

		// Make sure that this table exists.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables', 'field_helper_tables' ),
			array( 'main_table' => $tableName ) );
		if ( $res->numRows() == 0 ) {
			$out->addHTML( CargoUtils::formatError( wfMessage( "cargo-unknowntable", $tableName )->parse() ) );
			return true;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $this->getRequest()->getCheck( 'delete' ) ) {
			self::deleteTable( $tableName, $fieldTables, $fieldHelperTables );
			$text = Html::element( 'p', null, "The table \"$tableName\" has been deleted." ) . "\n";
			if ( method_exists( $this, 'getLinkRenderer' ) ) {
				$linkRenderer = $this->getLinkRenderer();
			} else {
				$linkRenderer = null;
			}
			$tablesLink = CargoUtils::makeLink( $linkRenderer, $ctPage->getTitle(), $ctPage->getDescription() );
			$text .= Html::rawElement( 'p', null, $this->msg( 'returnto', $tablesLink )->text() );
			$out->addHTML( $text );
			return true;
		}

		$ctURL = $ctPage->getTitle()->getLocalURL();
		$tableLink = Html::element( 'a', array( 'href' => "$ctURL/$origTableName", ), $origTableName );

		if ( $replacementTable ) {
			$replacementTableURL = "$ctURL/$origTableName";
			$replacementTableURL .= ( strpos( $replacementTableURL, '?' ) ) ? '&' : '?';
			$replacementTableURL .= '_replacement';
			$replacementTableLink = Html::element( 'a', array( 'href' => $replacementTableURL, ), 'replacement table' );
			$text = Html::rawElement( 'p', null, "Delete the $replacementTableLink for Cargo table \"$tableLink\"?" );
		} else {
			$text = Html::rawElement( 'p', null, "Delete the Cargo table \"$tableLink\"?" );
		}
		$formText = Xml::submitButton( $this->msg( 'delete' ), array( 'name' => 'delete' ) );
		$text .= Html::rawElement( 'form', array( 'method' => 'post' ), $formText );
		$out->addHTML( $text );

		return true;
	}
}
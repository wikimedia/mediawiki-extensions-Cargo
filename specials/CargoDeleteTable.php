<?php
/**
 * An interface to delete a "Cargo table", which can be one or more real
 * database tables.
 *
 * The class is called CargoDeleteCargoTable, the file is called
 * CargoDeleteTable.php, and the wiki page is Special:DeleteCargoTable...
 * sorry for the confusion!
 *
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDeleteCargoTable extends SpecialPage {

	function __construct() {
		parent::__construct( 'DeleteCargoTable', 'deletecargodata' );
	}

	/**
	 * The table being deleted here is a Cargo table, not a DB table per
	 * se - a Cargo table corresponds to a main DB table, plus
	 * potentially one or more helper tables; all need to be deleted.
	 * Also, records need to be removed from the cargo_tables and
	 * cargo_pages tables.
	 */
	public static function deleteTable( $mainTable, $fieldTables ) {
		$cdb = CargoUtils::getDB();
		try {
			$cdb->dropTable( $mainTable );
			foreach( $fieldTables as $fieldTable ) {
				$cdb->dropTable( $fieldTable );
			}
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to drop Cargo table. Please make sure that your database user account has the DROP permission." );
		}
		$cdb->close();

		$dbr = wfGetDB( DB_MASTER );
		$dbr->delete( 'cargo_tables', array( 'main_table' => $mainTable ) );
		$dbr->delete( 'cargo_pages', array( 'table_name' => $mainTable ) );
	}

	function execute( $subpage = false ) {
		$out = $this->getOutput();

		if ( ! $this->getUser()->isAllowed( 'deletecargodata' ) ) {
			$out->permissionRequired( 'deletecargodata' );
			return;
		}

		$this->setHeaders();
		if ( $subpage == '' ) {
			$out->addHTML( CargoUtils::formatError( "Error: table name must be set." ) );
			return true;
		}

		// Make sure that this table exists.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables' ), array( 'main_table' => $subpage ) );
		if ( $res->numRows() == 0 ) {
			$out->addHTML( CargoUtils::formatError( "Error: no table found named \"$subpage\"." ) );
			return true;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );

		if ( $this->getRequest()->getCheck( 'delete' ) ) {
			self::deleteTable( $subpage, $fieldTables );
			$text = Html::element( 'p', null, "The table \"$subpage\" has been deleted." ) . "\n";
			$tablesLink = Linker::linkKnown( $ctPage->getTitle(), $ctPage->getDescription() );
			$text .= Html::rawElement( 'p', null, wfMessage( 'returnto', $tablesLink )->text() );
			$out->addHTML( $text );
			return true;
		}

		$ctURL = $ctPage->getTitle()->getLocalURL();
		$tableLink = Html::element( 'a', array( 'href' => "$ctURL/$subpage", ), $subpage );

		$text = Html::rawElement( 'p', null, "Delete the Cargo table \"$tableLink\"?" );
		$formText = Html::submitButton( wfMessage( 'delete' ), array( 'name' => 'delete' ) );
		$text .= Html::rawElement( 'form', array( 'method' => 'post' ), $formText );
		$out->addHTML( $text );

		return true;
	}

}

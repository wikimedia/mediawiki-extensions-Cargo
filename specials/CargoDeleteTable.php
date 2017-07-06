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
 * @todo This should really inherit from UnlistedSpecialPage and there should be a link from
 * Special:CargoTables to delete that table.
 */

class CargoDeleteCargoTable extends SpecialPage {

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
	public static function deleteTable( $mainTable, $fieldTables, $fieldHelperTables = array() ) {
		$cdb = CargoUtils::getDB();
		try {
			$cdb->dropTable( $mainTable );
			foreach ( $fieldTables as $fieldTable ) {
				$cdb->dropTable( $fieldTable );
			}
			foreach ( $fieldHelperTables as $fieldHelperTable ) {
				$cdb->dropTable( $fieldHelperTable );
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

		$this->setHeaders();
		if ( $subpage == '' ) {
			/** @todo i18n for these error messages */
			$out->addHTML( CargoUtils::formatError( "Error: table name must be set." ) );
			return true;
		}

		// Make sure that this table exists.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables', 'field_helper_tables' ),
			array( 'main_table' => $subpage ) );
		if ( $res->numRows() == 0 ) {
			$out->addHTML( CargoUtils::formatError( "Error: no table found named \"$subpage\"." ) );
			return true;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $this->getRequest()->getCheck( 'delete' ) ) {
			self::deleteTable( $subpage, $fieldTables, $fieldHelperTables );
			$text = Html::element( 'p', null, "The table \"$subpage\" has been deleted." ) . "\n";
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
		$tableLink = Html::element( 'a', array( 'href' => "$ctURL/$subpage", ), $subpage );

		$text = Html::rawElement( 'p', null, "Delete the Cargo table \"$tableLink\"?" );
		$formText = Xml::submitButton( $this->msg( 'delete' ), array( 'name' => 'delete' ) );
		$text .= Html::rawElement( 'form', array( 'method' => 'post' ), $formText );
		$out->addHTML( $text );

		return true;
	}

	/**
	 * @todo Should be unlisted
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'cargo';
	}
}

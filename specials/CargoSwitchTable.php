<?php
/**
 * An interface to delete a "Cargo table", which can be one or more real
 * database tables.
 *
 * The class is called CargoSwitchCargoTable, the file is called
 * CargoSwitchTable.php, and the wiki page is Special:SwitchCargoTable...
 * sorry for the confusion!
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoSwitchCargoTable extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'SwitchCargoTable', 'recreatecargodata' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * The table being switched here is a Cargo table, not a DB table per
	 * se - a Cargo table corresponds to a main DB table, plus
	 * potentially one or more helper tables; all need to be switched.
	 * Also, records need to be removed, and modified, in the cargo_tables and
	 * cargo_pages tables.
	 */
	public static function switchInTableReplacement( $mainTable, $fieldTables, $fieldHelperTables ) {
		$cdb = CargoUtils::getDB();
		try {
			$cdb->begin();
			$cdb->dropTable( $mainTable );
			$cdb->query( 'ALTER TABLE ' .
				$cdb->tableName( $mainTable . '__NEXT' ) .
				' RENAME TO ' . $cdb->tableName( $mainTable ) );
			// The helper tables' names come from the database,
			// so they already contain '__NEXT' - remove that,
			// instead of adding it, when getting table names.
			foreach ( $fieldTables as $fieldTable ) {
				$origFieldTable = str_replace( '__NEXT', '', $fieldTable );
				$cdb->dropTable( $origFieldTable );
				$fieldTableName = $cdb->tableName( $fieldTable );
				$cdb->query( 'ALTER TABLE ' .
					$cdb->tableName( $fieldTable ) .
					' RENAME TO ' .
					$cdb->tableName( $origFieldTable ) );
			}
			if ( is_array( $fieldHelperTables ) ) {
				foreach ( $fieldHelperTables as $fieldHelperTable ) {
					$origFieldHelperTable = str_replace( '__NEXT', '', $fieldHelperTable );
					$cdb->dropTable( $origFieldHelperTable );
					$cdb->query( 'ALTER TABLE ' .
						$cdb->tableName( $fieldHelperTable ) .
						' RENAME TO ' .
						$cdb->tableName( $origFieldHelperTable ) );
				}
			}
			$cdb->commit();
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to switch in replacement for Cargo table. "
			. "Please make sure that your database user account has the DROP permission." );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'cargo_tables', array( 'main_table' => $mainTable ) );
		$dbw->delete( 'cargo_pages', array( 'table_name' => $mainTable ) );
		$dbw->update( 'cargo_tables', array( 'main_table' => $mainTable ), array( 'main_table' => $mainTable . '__NEXT' ) );
		$origFieldTableNames = array();
		foreach ( $fieldTables as $fieldTable ) {
			$origFieldTableNames[] = str_replace( '__NEXT', '', $fieldTable );
		}
		$dbw->update( 'cargo_tables', array( 'field_tables' => serialize( $origFieldTableNames ) ), array( 'main_table' => $mainTable ) );
		$dbw->update( 'cargo_pages', array( 'table_name' => $mainTable ), array( 'table_name' => $mainTable . '__NEXT' ) );

		CargoUtils::logTableAction( 'replacetable', $mainTable );
	}

	function execute( $subpage = false ) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$tableName = $subpage;
		$out->enableOOUI();

		$this->setHeaders();
		if ( $tableName == '' ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-notable" ) );
			return true;
		}

		// Make sure that this table, and its replacement, both exist.
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables', 'field_helper_tables' ),
			array( 'main_table' => $tableName ) );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName ) );
			return true;
		}
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables', 'field_helper_tables' ),
			array( 'main_table' => $tableName . '__NEXT' ) );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName . "__NEXT" ) );
			return true;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $this->getRequest()->getCheck( 'switch' ) ) {
			self::switchInTableReplacement( $tableName, $fieldTables, $fieldHelperTables );
			$text = Html::element( 'p', null, "The replacement for the table \"$tableName\" was switched in." ) . "\n";
			if ( method_exists( $this, 'getLinkRenderer' ) ) {
				$linkRenderer = $this->getLinkRenderer();
			} else {
				$linkRenderer = null;
			}
			$tablesLink = CargoUtils::makeLink( $linkRenderer, $ctPage->getPageTitle(), $ctPage->getDescription() );
			$text .= Html::rawElement( 'p', null, $this->msg( 'returnto', $tablesLink )->text() );
			$out->addHTML( $text );
			return true;
		}

		$ctURL = $ctPage->getPageTitle()->getLocalURL();
		$tableLink = Html::element( 'a', array( 'href' => "$ctURL/$tableName", ), $tableName );

		$text = Html::rawElement( 'p', null, $this->msg( 'cargo-switchtables-confirm', $tableLink )->text() );
		$out->addHTML( $text );

		// Class, and display format, were added in MW 1.25.
		$displayFormat = ( class_exists( 'OOUIHTMLForm' ) ) ? 'ooui' : 'div';
		$htmlForm = HTMLForm::factory( $displayFormat, array(), $this->getContext() );
		$htmlForm
			->setMethod( 'post' )
			->setSubmitName( 'switch' )
			->setSubmitTextMsg( 'cargo-switchtables-switch' )
			->prepareForm()
			->displayForm( false );

		return true;
	}

}

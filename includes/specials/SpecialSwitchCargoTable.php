<?php
/**
 * An interface to delete a "Cargo table", which can be one or more real
 * database tables.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class SpecialSwitchCargoTable extends UnlistedSpecialPage {

	public function __construct() {
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
	public static function switchInTableReplacement(
		$mainTable,
		$fieldTables,
		$fieldHelperTables,
		User $user
	) {
		$cdb = CargoUtils::getDB();
		$origFieldTableNames = [];
		$origFieldHelperTableNames = [];
		try {
			$cdb->begin( __METHOD__ );

			// The helper tables' names come from the database,
			// so they already contain '__NEXT' - remove that,
			// instead of adding it, when getting table names.
			foreach ( $fieldTables as $fieldTable ) {
				$origFieldTable = str_replace( '__NEXT', '', $fieldTable );
				$origFieldTableNames[] = $origFieldTable;
				$cdb->dropTable( $origFieldTable, __METHOD__ );
				$cdb->query( 'ALTER TABLE ' .
					$cdb->tableName( $fieldTable ) .
					' RENAME TO ' .
					$cdb->tableName( $origFieldTable ), __METHOD__ );
			}
			if ( is_array( $fieldHelperTables ) ) {
				foreach ( $fieldHelperTables as $fieldHelperTable ) {
					$origFieldHelperTable = str_replace( '__NEXT', '', $fieldHelperTable );
					$origFieldHelperTableNames[] = $origFieldHelperTable;
					$cdb->dropTable( $origFieldHelperTable, __METHOD__ );
					$cdb->query( 'ALTER TABLE ' .
						$cdb->tableName( $fieldHelperTable ) .
						' RENAME TO ' .
						$cdb->tableName( $origFieldHelperTable ), __METHOD__ );
				}
			}

			$cdb->dropTable( $mainTable, __METHOD__ );
			$cdb->query( 'ALTER TABLE ' .
				$cdb->tableName( $mainTable . '__NEXT' ) .
				' RENAME TO ' . $cdb->tableName( $mainTable ), __METHOD__ );

			$cdb->commit( __METHOD__ );
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to switch in replacement for Cargo table. "
			. "Please make sure that your database user account has the DROP permission." );
		}

		$dbw = CargoUtils::getMainDBForWrite();
		$dbw->delete( 'cargo_tables', [ 'main_table' => $mainTable ], __METHOD__ );
		$dbw->delete( 'cargo_pages', [ 'table_name' => $mainTable ], __METHOD__ );
		$dbw->update( 'cargo_tables', [ 'main_table' => $mainTable ], [ 'main_table' => $mainTable . '__NEXT' ], __METHOD__ );
		$dbw->update(
			'cargo_tables',
			[
				'field_tables' => serialize( $origFieldTableNames ),
				'field_helper_tables' => serialize( $origFieldHelperTableNames )
			],
			[ 'main_table' => $mainTable ],
			__METHOD__
		);
		$dbw->update( 'cargo_pages', [ 'table_name' => $mainTable ], [ 'table_name' => $mainTable . '__NEXT' ], __METHOD__ );

		CargoUtils::logTableAction( 'replacetable', $mainTable, $user );
	}

	public function execute( $subpage = false ) {
		$this->checkPermissions();

		$out = $this->getOutput();
		$req = $this->getRequest();
		$csrfTokenSet = $this->getContext()->getCsrfTokenSet();

		$tableName = $subpage;
		$out->enableOOUI();

		$this->setHeaders();
		if ( $tableName == '' ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-notable" ) );
			return true;
		}

		// Make sure that this table, and its replacement, both exist.
		$dbr = CargoUtils::getMainDBForRead();
		$res = $dbr->select( 'cargo_tables', [ 'main_table', 'field_tables', 'field_helper_tables' ],
			[ 'main_table' => $tableName ], __METHOD__ );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName ) );
			return true;
		}
		$res = $dbr->select( 'cargo_tables', [ 'main_table', 'field_tables', 'field_helper_tables' ],
			[ 'main_table' => $tableName . '__NEXT' ], __METHOD__ );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName . "__NEXT" ) );
			return true;
		}

		$ctPage = CargoUtils::getSpecialPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $req->wasPosted() && $req->getCheck( 'switch' ) && $csrfTokenSet->matchToken( $req->getText( 'wpEditToken' ) ) ) {
			self::switchInTableReplacement( $tableName, $fieldTables, $fieldHelperTables, $this->getUser() );
			$text = Html::element( 'p', null, $this->msg( 'cargo-switchtables-success', $tableName )->parse() ) . "\n";
			$tablesLink = CargoUtils::makeLink( $this->getLinkRenderer(),
				$ctPage->getPageTitle(), $ctPage->getDescription() );
			$text .= Html::rawElement( 'p', null, $this->msg( 'returnto' )->rawParams( $tablesLink )->escaped() );
			$out->addHTML( $text );
			return true;
		}

		$ctURL = $ctPage->getPageTitle()->getLocalURL();
		$tableLink = Html::element( 'a', [ 'href' => "$ctURL/$tableName", ], $tableName );

		$text = Html::rawElement( 'p', null, $this->msg( 'cargo-switchtables-confirm' )->rawParams( $tableLink )->escaped() );
		$out->addHTML( $text );

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->setSubmitName( 'switch' )
			->setSubmitTextMsg( 'cargo-switchtables-switch' )
			->prepareForm()
			->displayForm( false );

		return true;
	}

}

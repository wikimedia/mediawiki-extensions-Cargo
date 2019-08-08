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
			$cdb->begin();
			$cdb->dropTable( $mainTable );
			foreach ( $fieldTables as $fieldTable ) {
				$cdb->dropTable( $fieldTable );
			}
			if ( is_array( $fieldHelperTables ) ) {
				foreach ( $fieldHelperTables as $fieldHelperTable ) {
					$cdb->dropTable( $fieldHelperTable );
				}
			}
			$cdb->commit();
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
			. "Please make sure that your database user account has the DROP permission." );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'cargo_tables', array( 'main_table' => $mainTable ) );
		$dbw->delete( 'cargo_pages', array( 'table_name' => $mainTable ) );
	}

	function execute( $subpage = false ) {
		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->enableOOUI();

		$this->setHeaders();
		if ( $subpage == '' ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-notable" ) );
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
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', array( 'main_table', 'field_tables', 'field_helper_tables' ),
			array( 'main_table' => $tableName ) );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName ) );
			return true;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $this->getRequest()->getCheck( 'delete' ) ) {
			self::deleteTable( $tableName, $fieldTables, $fieldHelperTables );
			$text = Html::element( 'p', null, $this->msg( 'cargo-deletetable-success', $tableName )->parse() ) . "\n";
			if ( method_exists( $this, 'getLinkRenderer' ) ) {
				$linkRenderer = $this->getLinkRenderer();
			} else {
				$linkRenderer = null;
			}
			$tablesLink = CargoUtils::makeLink( $linkRenderer,
				$ctPage->getPageTitle(),
				htmlspecialchars( $ctPage->getDescription() ) );
			$text .= Html::rawElement( 'p', null, $this->msg( 'returnto', $tablesLink )->text() );
			$out->addHTML( $text );
			if ( !$replacementTable ) {
				CargoUtils::logTableAction( 'deletetable', $tableName );
			}
			return true;
		}

		$ctURL = $ctPage->getPageTitle()->getFullURL();
		$tableLink = "[$ctURL/$origTableName $origTableName]";

		if ( $replacementTable ) {
			$replacementTableURL = "$ctURL/$origTableName";
			$replacementTableURL .= ( strpos( $replacementTableURL, '?' ) ) ? '&' : '?';
			$replacementTableURL .= '_replacement';
			$text = Html::rawElement( 'p',
				array( 'class' => 'plainlinks' ),
				$this->msg( 'cargo-deletetable-replacementconfirm', $replacementTableURL, $tableLink )->parse()
			);
		} else {
			$text = Html::rawElement( 'p',
				array( 'class' => 'plainlinks' ),
				$this->msg( 'cargo-deletetable-confirm', $tableLink )->parse()
			);
		}
		$out->addHTML( $text );

		// Class, and display format, were added in MW 1.25.
		$displayFormat = ( class_exists( 'OOUIHTMLForm' ) ) ? 'ooui' : 'div';
		$htmlForm = HTMLForm::factory( $displayFormat, array(), $this->getContext() );

		if ( $replacementTable ) {
			$htmlForm = $htmlForm->addHiddenField( '_replacement', '' );
		}

		$htmlForm
			->setSubmitName( 'delete' )
			->setSubmitTextMsg( 'delete' )
			->setSubmitDestructive();

		// setSubmitDestructive() can only be "chained" with other
		// calls starting in MW 1.27.
		$htmlForm
			->prepareForm()
			->displayForm( false );

		return true;
	}
}

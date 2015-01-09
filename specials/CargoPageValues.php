<?php
/**
 * Displays an interface to let users recreate data via the Cargo
 * extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoPageValues extends IncludableSpecialPage {
	public $mTitle;

	function __construct( $title = null ) {
		parent::__construct( 'PageValues' );

		$this->mTitle = $title;
	}

	function execute( $subpage = null ) {
		if ( $subpage ) {
			// Allow inclusion with e.g. {{Special:PageValues/Book}}
			$this->mTitle = Title::newFromText( $subpage );
		}
		$out = $this->getOutput();

		$this->setHeaders();

		$pageName = $this->mTitle->getPrefixedText();
		$out->setPageTitle( $this->msg( 'cargo-pagevaluesfor', $pageName )->text() );

		// Exit if this page does not exist.
		// @TODO - display some message?
		if ( !$this->mTitle->exists() ) {
			return;
		}

		$text = '';

		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			'cargo_pages', 'table_name', array( 'page_id' => $this->mTitle->getArticleID() ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$tableName = $row['table_name'];
			$queryResults = $this->getRowsForPageInTable( $tableName );
			$text .= Html::element( 'h2', null,
					$this->msg( 'cargo-pagevalues-tablevalues', $tableName )->text() ) . "\n";
			foreach ( $queryResults as $rowValues ) {
				$tableContents = '';
				foreach ( $rowValues as $field => $value ) {
					// @HACK - this check should ideally
					// be done earlier.
					if ( strpos( $field, '__precision' ) !== false ) {
						continue;
					}
					$tableContents .= $this->printRow( $field, $value );
				}
				$text .= $this->printTable( $tableContents );
			}
		}
		$out->addHTML( $text );

		return true;
	}

	function getRowsForPageInTable( $tableName ) {
		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mTableNames = array( $tableName );

		$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = array();
		foreach( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( $fieldDescription->mIsHidden ) {
				// @TODO - do some custom formatting
			}

			$fieldAlias = str_replace( '_', ' ', $fieldName );

			if ( $fieldDescription->mIsList ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} elseif ( $fieldDescription->mType == 'Coordinates' ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} else {
				$aliasedFieldNames[$fieldAlias] = $fieldName;
			}
		}

		$sqlQuery->mAliasedFieldNames = $aliasedFieldNames;
		$sqlQuery->setDescriptionsForFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->mWhereStr = "_pageID = " . $this->mTitle->getArticleID();

		$queryResults = $sqlQuery->run();
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );
		return $formattedQueryResults;
	}

	/**
	 * Based on MediaWiki's InfoAction::addRow()
	 */
	function printRow( $name, $value ) {
		return Html::rawElement( 'tr', array(),
			Html::rawElement( 'td', array( 'style' => 'vertical-align: top;' ), $name ) .
			Html::rawElement( 'td', array(), $value )
		);
	}

	/**
	 * Based on MediaWiki's InfoAction::addTable()
	 */
	function printTable( $tableContents ) {
		return Html::rawElement( 'table', array( 'class' => 'wikitable mw-page-info' ),
			$tableContents ) . "\n";
	}

	/**
	 * Don't list this in Special:SpecialPages.
	 */
	function isListed() {
		return false;
	}
}

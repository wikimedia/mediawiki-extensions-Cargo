<?php
/**
 * Defines a special page that shows the contents of a single table in
 * the Cargo database.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTables extends IncludableSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CargoTables' );
	}

	function execute( $tableName ) {
		$out = $this->getOutput();
		$this->setHeaders();

		if ( $tableName == '' ) {
			$out->addHTML( $this->displayListOfTables() );
			return;
		}

		$pageTitle = $this->msg( 'cargo-cargotables-viewtable', $tableName )->parse();
		$out->setPageTitle( $pageTitle );

		// Mimic the appearance of a subpage to link back to
		// Special:CargoTables.
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}
		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$mainPageLink = CargoUtils::makeLink(
			$linkRenderer,
			$ctPage->getTitle(),
			htmlspecialchars( $ctPage->getDescription() )
		);
		$out->setSubtitle( '< '. $mainPageLink );

		$cdb = CargoUtils::getDB();

		// First, display a count.
		try {
			$res = $cdb->select( $tableName, 'COUNT(*) AS total' );
		} catch ( Exception $e ) {
			$out->addHTML( Html::element( 'div', array( 'class' => 'error' ),
					$this->msg( 'cargo-cargotables-tablenotfound', $tableName )->parse() ) . "\n" );
			return;
		}
		$row = $cdb->fetchRow( $res );
		$out->addWikiText( $this->msg( 'cargo-cargotables-totalrows' )->numParams( intval($row['total']) )->text() . "\n" );

		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mTablesStr = $tableName;
		$sqlQuery->mAliasedTableNames = array( $tableName => $tableName );

		$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = array( $this->msg( 'nstab-main' )->parse() => '_pageName' );
		foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// Skip "hidden" fields.
			if ( array_key_exists( 'hidden', $fieldDescription ) ) {
				continue;
			}

			if ( $fieldName[0] != '_' ) {
				$fieldAlias = str_replace( '_', ' ', $fieldName );
			} else {
				$fieldAlias = $fieldName;
			}
			$fieldType = $fieldDescription->mType;
			// Special handling for URLs, to avoid them
			// overwhelming the page.
			// @TODO - something similar should be done for lists
			// of URLs.
			if ( $fieldType == 'URL' && !$fieldDescription->mIsList ) {
				// CONCAT() was only defined in MS SQL Server
				// in version 11.0, from 2012.
				if ( $cdb->getType() == 'mssql' && version_compare( $cdb->getServerInfo(), '11.0', '<' ) ) {
					// Just show the URL.
				} else {
					// Thankfully, there's a message in core
					// MediaWiki that seems to just be "URL".
					$fieldName = "CONCAT('[', $fieldName, ' " .
						$this->msg( 'version-entrypoints-header-url' )->parse() . "]')";
				}
			}

			if ( $fieldDescription->mIsList ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} elseif ( $fieldType == 'Coordinates' ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} else {
				$aliasedFieldNames[$fieldAlias] = $fieldName;
			}
		}

		$sqlQuery->mAliasedFieldNames = $aliasedFieldNames;
		// Set mFieldsStr in case we need to show a "More" link
		// at the end.
		$fieldsStr = '';
		foreach ( $aliasedFieldNames as $alias => $fieldName ) {
			$fieldsStr .= "$fieldName=$alias,";
		}
		// Remove the comma at the end.
		$sqlQuery->mFieldsStr = trim( $fieldsStr, ',' );

		$sqlQuery->setDescriptionsForFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->setOrderBy();
		$sqlQuery->mQueryLimit = 100;

		$queryResults = $sqlQuery->run();

		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );

		$displayParams = array();

		$tableFormat = new CargoTableFormat( $this->getOutput() );
		$text = $tableFormat->display( $queryResults, $formattedQueryResults,
			$sqlQuery->mFieldDescriptions, $displayParams );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink();
		}

		$out->addHTML( $text );
	}

	/**
	 * Returns HTML for a bulleted list of Cargo tables, with various
	 * links and information for each one.
	 */
	function displayListOfTables() {
		global $wgUser;

		$text = '';

		// Show a note if there are currently Cargo populate-data jobs
		// that haven't been run, to make troubleshooting easier.
		$group = JobQueueGroup::singleton();
		// The following line would have made more sense to call, but
		// it seems to return true if there are *any* jobs in the
		// queue - a bug in MediaWiki?
		//if ( $group->queuesHaveJobs( 'cargoPopulateTable' ) ) {
		if ( in_array( 'cargoPopulateTable', $group->getQueuesWithJobs() ) ) {
			$text .= '<div class="warningbox">' . $this->msg( 'cargo-cargotables-beingpopulated' )->text() . "</div>\n";
		}

		$cdb = CargoUtils::getDB();
		$tableNames = CargoUtils::getTables();
		$templatesThatDeclareTables = CargoUtils::getAllPageProps( 'CargoTableName' );
		$templatesThatAttachToTables = CargoUtils::getAllPageProps( 'CargoAttachedTable' );

		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$ctURL = $ctPage->getTitle()->getFullURL();
		$text .= Html::rawElement( 'p', null,
			$this->msg( 'cargo-cargotables-tablelist' )->numParams( count( $tableNames ) )->parse() ) . "\n";
		$text .= "<ul>\n";
		foreach ( $tableNames as $tableName ) {
			if ( !$cdb->tableExists( $tableName ) ) {
				$tableText = "$tableName - ";
				// @TODO - this should probably be an i18n message.
				$tableText .= "<span class=\"error\">Table is registered, but does not exist!</span>";
				$text .= Html::rawElement( 'li', null, $tableText );
				continue;
			}

			$actionLinks = Html::element( 'a', array( 'href' => "$ctURL/$tableName", ),
					$this->msg( 'view' )->text() );

			// Actions for this table - this display is modeled on
			// Special:ListUsers.
			$drilldownPage = SpecialPageFactory::getPage( 'Drilldown' );
			$drilldownURL = $drilldownPage->getTitle()->getLocalURL() . '/' . $tableName;
			$drilldownURL .= ( strpos( $drilldownURL, '?' ) ) ? '&' : '?';
			$drilldownURL .= "_single";
			$actionLinks .= ' | ' . Html::element( 'a', array( 'href' => $drilldownURL ),
					$drilldownPage->getDescription() );

			// It's a bit odd to include the "Recreate data"
			// link, since it's an action for the template and
			// not the table (if a template defines two tables,
			// this will recreate both of them), but for standard
			// setups, this makes things more convenient.
			if ( $wgUser->isAllowed( 'recreatecargodata' ) && array_key_exists( $tableName, $templatesThatDeclareTables ) ) {
				$firstTemplateID = $templatesThatDeclareTables[$tableName][0];
				$templateTitle = Title::newFromID( $firstTemplateID );
				$actionLinks .= ' | ' . CargoUtils::makeLink( $linkRenderer, $templateTitle,
					$this->msg( 'recreatedata' )->text(), array(), array( 'action' => 'recreatedata' ) );
			}

			if ( $wgUser->isAllowed( 'deletecargodata' ) ) {
				$deleteTablePage = SpecialPageFactory::getPage( 'DeleteCargoTable' );
				$deleteTableURL = $deleteTablePage->getTitle()->getLocalURL() . '/' . $tableName;
				$actionLinks .= ' | ' . Html::element( 'a', array( 'href' => $deleteTableURL ),
						$this->msg( 'delete' )->text() );
			}

			$res = $cdb->select( $tableName, 'COUNT(*) AS total' );
			$row = $cdb->fetchRow( $res );
			$numRowsText = $this->msg( 'cargo-cargotables-totalrowsshort' )->numParams( intval($row['total']) )->parse();

			// "Declared by" text
			if ( !array_key_exists( $tableName, $templatesThatDeclareTables ) ) {
				$declaringTemplatesText = $this->msg( 'cargo-cargotables-notdeclared' )->text();
			} else {
				$templatesThatDeclareThisTable = $templatesThatDeclareTables[$tableName];
				$templateLinks = array();
				foreach ( $templatesThatDeclareThisTable as $templateID ) {
					$templateTitle = Title::newFromID( $templateID );
					$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
				}
				$declaringTemplatesText = $this->msg(
						'cargo-cargotables-declaredby', implode( $templateLinks ) )->text();
			}

			// "Attached by" text
			if ( array_key_exists( $tableName, $templatesThatAttachToTables ) ) {
				$templatesThatAttachToThisTable = $templatesThatAttachToTables[$tableName];
			} else {
				$templatesThatAttachToThisTable = array();
			}

			if ( count( $templatesThatAttachToThisTable ) == 0 ) {
				$attachingTemplatesText = '';
			} else {
				$templateLinks = array();
				foreach ( $templatesThatAttachToThisTable as $templateID ) {
					$templateTitle = Title::newFromID( $templateID );
					$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
				}
				$attachingTemplatesText = $this->msg(
						'cargo-cargotables-attachedby', implode( $templateLinks ) )->text();
			}

			$tableText = "$tableName ($actionLinks) - $numRowsText ($declaringTemplatesText";
			if ( $attachingTemplatesText != '' ) {
				$tableText .= ", $attachingTemplatesText";
			}
			$tableText .= ')';

			$text .= Html::rawElement( 'li', null, $tableText );
		}
		$text .= "</ul>\n";
		return $text;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

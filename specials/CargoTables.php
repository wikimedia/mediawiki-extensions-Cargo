<?php
/**
 * Defines a special page that shows the contents of a single table in
 * the Cargo database.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTables extends IncludableSpecialPage {

	private $templatesThatDeclareTables;
	private $templatesThatAttachToTables;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CargoTables' );
		$this->templatesThatDeclareTables = CargoUtils::getAllPageProps( 'CargoTableName' );
		$this->templatesThatAttachToTables = CargoUtils::getAllPageProps( 'CargoAttachedTable' );
	}

	function execute( $tableName ) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();
		$this->setHeaders();

		$out->addModules( 'ext.cargo.main' );

		if ( $tableName == '' ) {
			$out->addHTML( $this->displayListOfTables() );
			return;
		}

		$cdb = CargoUtils::getDB();

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$ctURL = $ctPage->getTitle()->getFullURL();
		$viewURL = "$ctURL/$tableName";

		if ( $req->getCheck( '_replacement' ) ) {
			$pageTitle = $this->msg( 'cargo-cargotables-viewreplacement', '"' . $tableName . '"' )->parse();
			$tableLink = Html::element( 'a', array( 'href' => $viewURL ), $tableName );
			$text = $this->msg( 'cargo-cargotables-replacementtable', $tableLink )->text();
			if ( $user->isAllowed( 'recreatecargodata' ) ) {
				$sctPage = SpecialPageFactory::getPage( 'SwitchCargoTable' );
				$switchURL = $sctPage->getTitle()->getFullURL() . "/$tableName";
				$text .= ' ' . Html::element( 'a', array( 'href' => $switchURL ),
					$this->msg( "cargo-cargotables-switch" )->parse() );

				if ( $user->isAllowed( 'deletecargodata' ) ) {
					$dctPage = SpecialPageFactory::getPage( 'DeleteCargoTable' );
					$deleteURL = $dctPage->getTitle()->getFullURL() . "/$tableName";
					$deleteURL .= strpos( $deleteURL, '?' ) ? '&' : '?';
					$deleteURL .= "_replacement";
					$text .= ' ' . $this->msg( 'cargo-cargotables-deletereplacement', $deleteURL )->parse();
				}
			}
			$out->addHtml( Html::rawElement( 'div', array( 'class' => 'warningbox plainlinks' ), $text ) );
			$tableName .= '__NEXT';
		} else {
			$pageTitle = $this->msg( 'cargo-cargotables-viewtable', $tableName )->parse();
			if ( $cdb->tableExists( $tableName . '__NEXT' ) ) {
				$text = Html::rawElement( 'div',
					array( 'class' => 'warningbox' ),
					$this->msg( 'cargo-cargotables-hasreplacement' )->parse()
				);
				$out->addHtml( $text );
			}
		}

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

		$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
		$fieldDescriptions = $tableSchemas[$tableName]->mFieldDescriptions;

		// Display the table structure.
		$structureDesc = '<p>' . $this->msg( 'cargo-cargotables-tablestructure' )->parse() . '</p>';
		$structureDesc .= '<ul>';
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldDesc = '<strong>' . $fieldName . '</strong> - ';
			$typeDesc = '<tt>' . $fieldDescription->mType . '</tt>';
			if ( $fieldDescription->mIsList ) {
				$fieldDesc .= $this->msg( 'cargo-cargotables-listof', $typeDesc )->parse();
			} else {
				$fieldDesc .= $typeDesc;
			}
			$structureDesc .= Html::rawElement( 'li', null, $fieldDesc ) . "\n";
		}
		$structureDesc .= '</ul>';
		$out->addHTML( $structureDesc );

		// Then, display a count.
		try {
			$res = $cdb->select( $tableName, 'COUNT(*) AS total' );
		} catch ( Exception $e ) {
			$out->addHTML( Html::element( 'div', array( 'class' => 'error' ),
					$this->msg( 'cargo-cargotables-tablenotfound', $tableName )->parse() ) . "\n" );
			return;
		}
		$row = $cdb->fetchRow( $res );
		$out->addWikiText( $this->msg( 'cargo-cargotables-totalrows' )->numParams( intval( $row['total'] ) )->text() . "\n" );

		// Then, show the actual table, via a query.
		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mTablesStr = $tableName;
		$sqlQuery->mAliasedTableNames = array( $tableName => $tableName );

		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = array( $this->msg( 'nstab-main' )->parse() => '_pageName' );
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
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

		$displayParams = array();
		$displayParams['max display chars'] = 300;

		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$queryDisplayer->mDisplayParams = $displayParams;
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );

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

	function displayNumRowsForTable( $cdb, $tableName ) {
		$res = $cdb->select( $tableName, 'COUNT(*) AS total' );
		$row = $cdb->fetchRow( $res );
		return $this->msg( 'cargo-cargotables-totalrowsshort' )->numParams( intval( $row['total'] ) )->parse();
	}

	function displayActionLinksForTable( $tableName, $isReplacementTable, $hasReplacementTable ) {
		global $wgUser;

		$canBeRecreated = !$hasReplacementTable && array_key_exists( $tableName, $this->templatesThatDeclareTables );
		$templateID = $canBeRecreated ? $this->templatesThatDeclareTables[$tableName][0] : null;

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$ctURL = $ctPage->getTitle()->getFullURL();
		$viewURL = "$ctURL/$tableName";
		if ( $isReplacementTable ) {
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
		}
		$actionLinks = array();
		$actionLinks['view'] = Html::element( 'a', array( 'href' => $viewURL ),
			$this->msg( 'view' )->text() );

		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}

		// Actions for this table - this display is modeled on
		// Special:ListUsers.
		$drilldownPage = SpecialPageFactory::getPage( 'Drilldown' );
		$drilldownURL = $drilldownPage->getTitle()->getLocalURL() . '/' . $tableName;
		$drilldownURL .= strpos( $drilldownURL, '?' ) ? '&' : '?';
		if ( $isReplacementTable ) {
			$drilldownURL .= "_replacement";
		} else {
			$drilldownURL .= "_single";
		}
		$actionLinks['drilldown'] = Html::element( 'a', array( 'href' => $drilldownURL ),
			$drilldownPage->getDescription() );

		// It's a bit odd to include the "Recreate data" link, since
		// it's an action for the template and not the table (if a
		// template defines two tables, this will recreate both of
		// them), but for standard setups, this makes things more
		// convenient.
		if ( $canBeRecreated && $wgUser->isAllowed( 'recreatecargodata' ) ) {
			$templateTitle = Title::newFromID( $templateID );
			$actionLinks['recreate'] = CargoUtils::makeLink( $linkRenderer, $templateTitle,
				$this->msg( 'recreatedata' )->text(), array(), array( 'action' => 'recreatedata' ) );
		}

		if ( $wgUser->isAllowed( 'deletecargodata' ) ) {
			$deleteTablePage = SpecialPageFactory::getPage( 'DeleteCargoTable' );
			$deleteTableURL = $deleteTablePage->getTitle()->getLocalURL() . '/' . $tableName;
			if ( $isReplacementTable ) {
				$deleteTableURL .= strpos( $deleteTableURL, '?' ) ? '&' : '?';
				$deleteTableURL .= "_replacement";
			}
			$actionLinks['delete'] = Html::element( 'a', array( 'href' => $deleteTableURL ),
				$this->msg( 'delete' )->text() );
		}

		Hooks::run( 'CargoTablesActionLinks', array( &$actionLinks, $tableName, $isReplacementTable,
			$hasReplacementTable, $this->templatesThatDeclareTables, $this->templatesThatAttachToTables ) );
		return implode( ' | ', $actionLinks );
	}

	function tableTemplatesText( $tableName ) {
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}

		// "Declared by" text
		if ( !array_key_exists( $tableName, $this->templatesThatDeclareTables ) ) {
			$declaringTemplatesText = $this->msg( 'cargo-cargotables-notdeclared' )->text();
		} else {
			$templatesThatDeclareThisTable = $this->templatesThatDeclareTables[$tableName];
			$templateLinks = array();
			foreach ( $templatesThatDeclareThisTable as $templateID ) {
				$templateTitle = Title::newFromID( $templateID );
				$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
			}
			$declaringTemplatesText = $this->msg(
				'cargo-cargotables-declaredby', implode( ', ', $templateLinks ) )->text();
		}

		// "Attached by" text
		if ( array_key_exists( $tableName, $this->templatesThatAttachToTables ) ) {
			$templatesThatAttachToThisTable = $this->templatesThatAttachToTables[$tableName];
		} else {
			$templatesThatAttachToThisTable = array();
		}

		if ( count( $templatesThatAttachToThisTable ) == 0 ) {
			return $declaringTemplatesText;
		}

		$templateLinks = array();
		foreach ( $templatesThatAttachToThisTable as $templateID ) {
			$templateTitle = Title::newFromID( $templateID );
			$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
		}
		$attachingTemplatesText = $this->msg(
			'cargo-cargotables-attachedby', implode( ', ', $templateLinks ) )->text();

		return "$declaringTemplatesText, $attachingTemplatesText";
	}

	/**
	 * Returns HTML for a bulleted list of Cargo tables, with various
	 * links and information for each one.
	 */
	function displayListOfTables() {
		$text = '';

		// Show a note if there are currently Cargo populate-data jobs
		// that haven't been run, to make troubleshooting easier.
		$group = JobQueueGroup::singleton();
		// The following line would have made more sense to call, but
		// it seems to return true if there are *any* jobs in the
		// queue - a bug in MediaWiki?
		// if ( $group->queuesHaveJobs( 'cargoPopulateTable' ) ) {
		if ( in_array( 'cargoPopulateTable', $group->getQueuesWithJobs() ) ) {
			$text .= '<div class="warningbox">' . $this->msg( 'cargo-cargotables-beingpopulated' )->text() . "</div>\n";
		}

		$cdb = CargoUtils::getDB();
		$tableNames = CargoUtils::getTables();

		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}

		$text .= Html::rawElement( 'p', null,
			$this->msg( 'cargo-cargotables-tablelist' )->numParams( count( $tableNames ) )->parse() ) . "\n";
		$text .= "<ul>\n";
		foreach ( $tableNames as $tableName ) {
			if ( !$cdb->tableExists( $tableName ) ) {
				$tableText = "$tableName - ";
				$tableText .= '<span class="error">' . $this->msg( "cargo-cargotables-nonexistenttable" )->parse() . '</span>';
				$tableText .= ' (' . $this->tableTemplatesText( $tableName ) . ')';
				$text .= Html::rawElement( 'li', null, $tableText );
				continue;
			}

			$possibleReplacementTable = $tableName . '__NEXT';
			$hasReplacementTable = $cdb->tableExists( $possibleReplacementTable );
			$actionLinks = $this->displayActionLinksForTable( $tableName, false, $hasReplacementTable );
			$numRowsText = $this->displayNumRowsForTable( $cdb, $tableName );
			$templatesText = $this->tableTemplatesText( $tableName );

			$tableText = "$tableName ($actionLinks) - $numRowsText ($templatesText)";

			if ( $hasReplacementTable ) {
				if ( !$cdb->tableExists( $tableName . '__NEXT' ) ) {
					$tableText .= "\n<div class=\"cargoReplacementTableInfo\">";
					$tableText .= $tableName . '__NEXT - ';
					$tableText .= '<span class="error">' . $this->msg( "cargo-cargotables-nonexistenttable" )->parse() . '</span>';
					$tableText .= "</div>";
					$text .= Html::rawElement( 'li', null, $tableText );
					continue;
				}
				global $wgUser;
				$replacementGeneratedMsg = $this->msg( "cargo-cargotables-replacementgenerated" )->parse();
				$numRowsText = $this->displayNumRowsForTable( $cdb, $tableName . '__NEXT' );
				$actionLinks = $this->displayActionLinksForTable( $tableName, true, false );
				$tableText .= "\n<div class=\"cargoReplacementTableInfo\">$replacementGeneratedMsg ($actionLinks) - $numRowsText";
				if ( $wgUser->isAllowed( 'recreatecargodata' ) ) {
					$sctPage = SpecialPageFactory::getPage( 'SwitchCargoTable' );
					$switchURL = $sctPage->getTitle()->getFullURL() . "/$tableName";
					$tableText .= "<br />\n" . Html::element( 'a', array( 'href' => $switchURL ),
						$this->msg( "cargo-cargotables-switch" )->parse() );
				}
				$tableText .= "</div>";
			}

			$text .= Html::rawElement( 'li', null, $tableText );
		}
		$text .= "</ul>\n";
		return $text;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

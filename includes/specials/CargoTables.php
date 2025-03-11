<?php
/**
 * Defines a special page that shows the contents of a single table in
 * the Cargo database.
 *
 * @author Yaron Koren
 * @author Megan Cutrofello
 * @ingroup Cargo
 */

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class CargoTables extends IncludableSpecialPage {

	private $templatesThatDeclareTables;
	private $templatesThatAttachToTables;

	private static $actionList = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CargoTables' );
		$this->templatesThatDeclareTables = CargoUtils::getAllPageProps( 'CargoTableName' );
		$this->templatesThatAttachToTables = CargoUtils::getAllPageProps( 'CargoAttachedTable' );
	}

	public function execute( $tableName ) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();
		$this->setHeaders();

		$out->addModules( [
			'ext.cargo.main',
			'ext.cargo.cargotables',
		] );
		$out->addModuleStyles( [
			'mediawiki.pager.styles'
		] );

		if ( $tableName == '' ) {
			$out->addHTML( $this->displayListOfTables() );

			return;
		}

		if ( !CargoUtils::tableFullyExists( $tableName ) ) {
			$out->addHTML( Html::element( 'div', [ 'class' => 'error' ],
				$this->msg( "cargo-unknowntable", $tableName )->escaped() ) );

			return;
		}

		$ctURL = SpecialPage::getTitleFor( 'CargoTables' )->getFullURL();
		$viewURL = "$ctURL/$tableName";

		if ( $req->getCheck( '_replacement' ) ) {
			$pageTitle =
				$this->msg( 'cargo-cargotables-viewreplacement', '"' . $tableName . '"' )->escaped();
			$tableLink = Html::element( 'a', [ 'href' => $viewURL ], $tableName );
			$text = $this->msg( 'cargo-cargotables-replacementtable' )->rawParams( $tableLink )->escaped();
			if ( $user->isAllowed( 'recreatecargodata' ) ) {
				$switchURL =
					SpecialPage::getTitleFor( 'SwitchCargoTable' )->getFullURL() . "/$tableName";
				$text .= ' ' . Html::element( 'a', [ 'href' => $switchURL ],
						$this->msg( "cargo-cargotables-switch" )->escaped() );

				if ( $user->isAllowed( 'deletecargodata' ) ) {
					$deleteURL =
						SpecialPage::getTitleFor( 'DeleteCargoTable' )->getFullURL() .
						"/$tableName";
					$deleteURL .= strpos( $deleteURL, '?' ) ? '&' : '?';
					$deleteURL .= "_replacement";
					$text .= ' ' .
						$this->msg( 'cargo-cargotables-deletereplacement', $deleteURL )->parse();
				}
			}
			$out->addHtml( Html::warningBox( $text ) );
			$tableName .= '__NEXT';
		} else {
			$pageTitle = $this->msg( 'cargo-cargotables-viewtable', $tableName )->escaped();
			if ( CargoUtils::tableFullyExists( $tableName . '__NEXT' ) ) {
				$text = Html::warningBox( $this->msg( 'cargo-cargotables-hasreplacement' )->escaped() );
				$out->addHtml( $text );
			}
		}

		$out->setPageTitle( $pageTitle );

		// Mimic the appearance of a subpage to link back to
		// Special:CargoTables.
		$ctPage = CargoUtils::getSpecialPage( 'CargoTables' );
		$mainPageLink =
			CargoUtils::makeLink( $this->getLinkRenderer(), $ctPage->getPageTitle(),
				htmlspecialchars( $ctPage->getDescription() ) );
		$out->setSubtitle( '< ' . $mainPageLink );

		$tableSchemas = CargoUtils::getTableSchemas( [ $tableName ] );
		$fieldDescriptions = $tableSchemas[$tableName]->mFieldDescriptions;

		// Display the table structure.
		$structureDesc = '<p>' . $this->msg( 'cargo-cargotables-tablestructure' )->escaped() . '</p>';
		$structureDesc .= '<ol>';
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldDesc = '<strong>' . $fieldName . '</strong> - ';
			$fieldDesc .= $fieldDescription->prettyPrintTypeAndAttributes();
			$structureDesc .= Html::rawElement( 'li', null, $fieldDesc ) . "\n";
		}
		$structureDesc .= '</ol>';
		$out->addHTML( $structureDesc );

		// Then, display a count.
		$cdb = CargoUtils::getDB();
		$numRows = $cdb->selectRowCount( $tableName, '*', null, __METHOD__ );
		$numRowsMessage =
			$this->msg( 'cargo-cargotables-totalrows' )->numParams( $numRows )->parse();
		$out->addWikiTextAsInterface( $numRowsMessage . "\n" );

		// Display "Recreate data" link.
		if ( array_key_exists( $tableName, $this->templatesThatDeclareTables ) ) {
			$templatesThatDeclareThisTable = $this->templatesThatDeclareTables[$tableName];
		}
		if ( isset( $templatesThatDeclareThisTable ) && count( $templatesThatDeclareThisTable ) > 0 ) {
			// Most likely, there's only one.
			$templateID = $templatesThatDeclareThisTable[0];
			$templateTitle = Title::newFromID( $templateID );
			$recreateDataURL = $templateTitle->getLocalURL( [ 'action' => 'recreatedata' ] );
			$recreateDataMessage = $this->msg( 'recreatedata' )->escaped();
			$recreateDataLink = Html::element( 'a', [ 'href' => $recreateDataURL ], $recreateDataMessage );
			$out->addHTML( '<p>' . $recreateDataLink . '.</p>' );
		}

		// Then, show the actual table, via a query.
		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mTablesStr = $tableName;
		$sqlQuery->mAliasedTableNames = [ $tableName => $tableName ];

		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = [ $this->msg( 'nstab-main' )->parse() => '_pageName' ];
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			// Skip "hidden" fields.
			if ( property_exists( $fieldDescription, 'hidden' ) ) {
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
				$quotedFieldName = $cdb->addIdentifierQuotes( $fieldName );
				// Thankfully, there's a message in core
				// MediaWiki that seems to just be "URL".
				$fieldName =
					"CONCAT('[', $quotedFieldName, ' " .
					$this->msg( 'version-entrypoints-header-url' )->parse() . "]')";
				// Only display this if the URL is not blank.
				// Unfortunately, IF is not consistently
				// supported in PostgreSQL - just leave this
				// logic out if we're using Postgres.
				if ( $cdb->getType() !== 'postgres' ) {
					$fieldName = "IF ($quotedFieldName <> '', $fieldName, '')";
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
		$sqlQuery->mOrigAliasedFieldNames = $aliasedFieldNames;
		// Set mFieldsStr in case we need to show a "More" link
		// at the end.
		$fieldsStr = '';
		foreach ( $aliasedFieldNames as $alias => $fieldName ) {
			$fieldsStr .= "$fieldName=$alias,";
		}
		// Remove the comma at the end.
		$sqlQuery->mFieldsStr = trim( $fieldsStr, ',' );

		$sqlQuery->setDescriptionsAndTableNamesForFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->setOrderBy();
		$sqlQuery->mQueryLimit = 100;

		$queryResults = $sqlQuery->run();

		$displayParams = [];
		$displayParams['max display chars'] = 300;
		$displayParams['edit link'] = 'yes';

		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$queryDisplayer->mDisplayParams = $displayParams;
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );

		$tableFormat = new CargoTableFormat( $this->getOutput() );
		$text =
			$tableFormat->display( $queryResults, $formattedQueryResults,
				$sqlQuery->mFieldDescriptions, $displayParams );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink();
		}

		$out->addHTML( $text );
	}

	public function displayNumRowsForTable( $cdb, $tableName ) {
		global $wgCargoDecimalMark;
		global $wgCargoDigitGroupingCharacter;

		$res = $cdb->select( $tableName, 'COUNT(*) AS total', '', __METHOD__ );
		$row = $res->fetchRow();

		return number_format( intval( $row['total'] ), 0, $wgCargoDecimalMark,
			$wgCargoDigitGroupingCharacter );
	}

	public function displayNumColumnsForTable( $tableName ) {
		$tableSchemas = CargoUtils::getTableSchemas( [ $tableName ] );
		return count( $tableSchemas[$tableName]->mFieldDescriptions );
	}

	private function getTableLinkedToView( $tableName, $isReplacementTable ) {
		$viewURL = SpecialPage::getTitleFor( 'CargoTables' )->getFullURL() . "/$tableName";
		if ( $isReplacementTable ) {
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
		}

		$displayText =
			$isReplacementTable ? $this->msg( 'cargo-cargotables-replacementlink' ) : $tableName;

		return Html::element( 'a', [ 'href' => $viewURL ], $displayText );
	}

	public function getActionButton( $action, $target ) {
		// a button is a clickable link, its target being a table action
		$actionList = self::$actionList;
		$displayIcon = $actionList[$action]['ooui-icon'];
		$displayTitle = $this->msg( $actionList[$action]['ooui-title'] );
		$element = new OOUI\ButtonWidget( [
				'icon' => $displayIcon,
				'title' => $displayTitle,
				'href' => $target,
			] );

		return $element->toString();
	}

	private function getActionIcon( $action ) {
		// an icon is just a static icon, no link. these are used in headings.
		$actionList = self::$actionList;
		$displayIcon = $actionList[$action]['ooui-icon'];
		$displayTitle = $this->msg( $actionList[$action]['ooui-title'] );
		$element = new OOUI\IconWidget( [
				'icon' => $displayIcon,
				'title' => $displayTitle,
			] );

		return $element->toString();
	}

	private function setAllowedActions() {
		// initialize needed ooui stuff
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-interactions' ] );
		$this->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-moderation' ] );
		OOUI\Element::setDefaultDir( 'ltr' );

		// add display information for all actions that a user is able to perform
		// if the parent param is set, then the action belongs to another column
		// and will NOT cause creation of a new column
		$user = $this->getUser();

		$allowedActions = [];
		if ( $user->isAllowed( 'runcargoqueries' ) ) {
			$allowedActions['drilldown'] = [
				"ooui-icon" => "funnel",
				"ooui-title" => "cargo-cargotables-action-drilldown",
			];
		}

		// recreatecargodata allows both recreating and switching in replacements
		if ( $user->isAllowed( 'recreatecargodata' ) ) {
			$allowedActions['recreate'] =
				[ "ooui-icon" => "reload", "ooui-title" => "cargo-cargotables-action-recreate" ];
			$allowedActions['switchReplacement'] =
				[
					"ooui-icon" => "check",
					"ooui-title" => "cargo-cargotables-action-switchreplacement",
					"parent" => "recreate",
				];
			// Should this have a separate permission?
			$allowedActions['create'] =
				[
					"ooui-icon" => "add",
					"ooui-title" => "create",
					"parent" => "recreate",
				];
		}

		// deletecargodata allows deleting live tables & their replacements
		// these cases are handled separately so they can use separate icons
		if ( $user->isAllowed( 'deletecargodata' ) ) {
			$allowedActions['delete'] =
				[ "ooui-icon" => "trash", "ooui-title" => "cargo-cargotables-action-delete" ];
			$allowedActions['deleteReplacement'] =
				[
					"ooui-icon" => "cancel",
					"ooui-title" => "cargo-cargotables-action-deletereplacement",
					"parent" => "delete",
				];
		}

		// allow opportunity for adding additional actions & display info
		$this->getHookContainer()->run( 'CargoTablesSetAllowedActions', [ $this, &$allowedActions ] );
		self::$actionList = $allowedActions;
	}

	private function deriveListOfColumnsFromUserAllowedActions() {
		$columns = [];
		foreach ( self::$actionList as $action => $actionInfo ) {
			if ( array_key_exists( "parent", $actionInfo ) ) {
				continue;
			}
			$columns[] = $action;
		}

		return $columns;
	}

	private function getActionLinksForTable( $tableName, $isReplacementTable, $hasReplacementTable, $isSpecialTable = false ) {
		$user = $this->getUser();

		$canBeRecreated =
			!$isReplacementTable && !$hasReplacementTable &&
			( $isSpecialTable || array_key_exists( $tableName, $this->templatesThatDeclareTables ) );

		$actionLinks = [];

		if ( array_key_exists( 'drilldown', self::$actionList ) ) {
			$drilldownPage = CargoUtils::getSpecialPage( 'Drilldown' );
			$drilldownURL = $drilldownPage->getPageTitle()->getLocalURL() . '/' . $tableName;
			$drilldownURL .= strpos( $drilldownURL, '?' ) ? '&' : '?';
			if ( $isReplacementTable ) {
				$drilldownURL .= "_replacement";
			} else {
				$drilldownURL .= "_single";
			}
			$actionLinks['drilldown'] = $this->getActionButton( 'drilldown', $drilldownURL );
		}

		// Recreate permission governs both recreating and switching
		if ( array_key_exists( 'recreate', self::$actionList ) ) {
			// It's a bit odd to include the "Recreate data" link, since
			// it's an action for the template and not the table (if a
			// template defines two tables, this will recreate both of
			// them), but for standard setups, this makes things more
			// convenient.
			if ( $canBeRecreated ) {
				if ( $isSpecialTable ) {
					$recreateURL =
						SpecialPage::getTitleFor( 'RecreateCargoData' )->getFullURL() . "/$tableName";
					$actionLinks['recreate'] = $this->getActionButton( 'recreate', $recreateURL );
				} else {
					$templateID = $this->templatesThatDeclareTables[$tableName][0];
					$templateTitle = Title::newFromID( $templateID );
					if ( $templateTitle !== null ) {
						$recreateDataURL = $templateTitle->getLocalURL( [ 'action' => 'recreatedata' ] );
						$actionLinks['recreate'] = $this->getActionButton( 'recreate', $recreateDataURL );
					}
				}
			} elseif ( $isReplacementTable ) {
				// switch will be in the same column as recreate
				$switchURL =
					SpecialPage::getTitleFor( 'SwitchCargoTable' )->getFullURL() . "/$tableName";
				$actionLinks['recreate'] =
					$this->getActionButton( 'switchReplacement', $switchURL );
			}
		}

		if ( array_key_exists( 'delete', self::$actionList ) ) {
			$deleteTableURL =
				SpecialPage::getTitleFor( 'DeleteCargoTable' )->getLocalURL() . "/$tableName";
			$deleteAction = "delete";
			if ( $isReplacementTable ) {
				$deleteTableURL .= strpos( $deleteTableURL, '?' ) ? '&' : '?';
				$deleteTableURL .= "_replacement";
				$deleteAction = "deleteReplacement";
			}
			$actionLinks['delete'] = $this->getActionButton( $deleteAction, $deleteTableURL );
		}

		$this->getHookContainer()->run( 'CargoTablesSetActionLinks', [
			$this,
			&$actionLinks,
			$tableName,
			$isReplacementTable,
			$hasReplacementTable,
			$this->templatesThatDeclareTables,
			$this->templatesThatAttachToTables,
			self::$actionList,
			$user
		] );

		return $actionLinks;
	}

	private function tableTemplatesText( $tableName ) {
		$linkRenderer = $this->getLinkRenderer();

		// "Declared by" text
		if ( !array_key_exists( $tableName, $this->templatesThatDeclareTables ) ) {
			$declaringTemplatesText = $this->msg( 'cargo-cargotables-notdeclared' )->text();
		} else {
			$templatesThatDeclareThisTable = $this->templatesThatDeclareTables[$tableName];
			$templateLinks = [];
			foreach ( $templatesThatDeclareThisTable as $templateID ) {
				$templateTitle = Title::newFromID( $templateID );
				$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
			}
			$declaringTemplatesText =
				Html::rawElement( 'span', [ "class" => "cargo-tablelist-template-declaring" ],
					implode( ', ', $templateLinks ) );
		}

		// "Attached by" text
		if ( array_key_exists( $tableName, $this->templatesThatAttachToTables ) ) {
			$templatesThatAttachToThisTable = $this->templatesThatAttachToTables[$tableName];
		} else {
			$templatesThatAttachToThisTable = [];
		}

		if ( count( $templatesThatAttachToThisTable ) == 0 ) {
			return $declaringTemplatesText;
		}

		$templateLinks = [];
		foreach ( $templatesThatAttachToThisTable as $templateID ) {
			$templateTitle = Title::newFromID( $templateID );
			$templateLinks[] = CargoUtils::makeLink( $linkRenderer, $templateTitle );
		}
		$attachingTemplatesText =
			Html::rawElement( 'span', [ "class" => "cargo-tablelist-template-attaching" ],
				implode( ', ', $templateLinks ) );

		return "$declaringTemplatesText, $attachingTemplatesText";
	}

	/**
	 * Returns HTML for a bulleted list of Cargo tables, with various
	 * links and information for each one.
	 */
	private function displayListOfTables() {
		global $wgCargoTablesPrioritizeReplacements;
		$text = '';

		$this->setAllowedActions();

		$listOfColumns = $this->deriveListOfColumnsFromUserAllowedActions();

		// Show a note if there are currently Cargo populate-data jobs
		// that haven't been run, to make troubleshooting easier.
		$group = MediaWikiServices::getInstance()->getJobQueueGroup();
		// The following line would have made more sense to call, but
		// it seems to return true if there are *any* jobs in the
		// queue - a bug in MediaWiki?
		// if ( $group->queuesHaveJobs( 'cargoPopulateTable' ) ) {
		if ( in_array( 'cargoPopulateTable', $group->getQueuesWithJobs() ) ) {
			$text .= Html::warningBox(
				$this->msg( 'cargo-cargotables-beingpopulated' )->parse()
			);
		}

		$cdb = CargoUtils::getDB();
		$tableNames = CargoUtils::getTables();

		// Move the "special" tables into a separate array.
		$existingSpecialTables = [];
		foreach ( $tableNames as $tableIndex => $tableName ) {
			if ( substr( $tableName, 0, 1 ) === '_' ) {
				unset( $tableNames[$tableIndex] );
				$existingSpecialTables[] = $tableName;
			}
		}

		// reorder table list so tables with replacements are first,
		// but only if the preference is set to do so
		if ( $wgCargoTablesPrioritizeReplacements ) {
			foreach ( $tableNames as $tableIndex => $tableName ) {
				$possibleReplacementTable = $tableName . '__NEXT';
				if ( $cdb->tableExists( $possibleReplacementTable, __METHOD__ ) ) {
					unset( $tableNames[$tableIndex] );
					array_unshift( $tableNames, $tableName );
				}
			}
		}

		$text .= Html::rawElement( 'p', null, $this->msg( 'cargo-cargotables-tablelist' )
				->numParams( count( $tableNames ) )
				->escaped() ) . "\n";

		$headerText = Html::element( 'th', null, $this->msg( "cargo-cargotables-header-table" ) );
		$headerText .= Html::element( 'th', null,
			$this->msg( "cargo-cargotables-header-rowcount" ) );
		$headerText .= Html::element( 'th', [ 'class' => 'cargotables-columncount' ],
			$this->msg( "cargo-cargotables-header-columncount" ) );

		foreach ( $listOfColumns as $action ) {
			$headerText .= Html::rawElement( 'th', null, $this->getActionIcon( $action ) );
		}

		$headerText .= Html::element( 'th', null,
			$this->msg( "cargo-cargotables-header-templates" ) );

		$wikitableText = Html::rawElement( 'tr', null, $headerText );

		foreach ( $tableNames as $tableName ) {

			$tableLink = $this->getTableLinkedToView( $tableName, false );

			$rowText = "";
			if ( !CargoUtils::tableFullyExists( $tableName ) ) {
				continue;
			}

			$possibleReplacementTable = $tableName . '__NEXT';
			$hasReplacementTable = CargoUtils::tableFullyExists( $possibleReplacementTable );
			$actionLinks = $this->getActionLinksForTable( $tableName, false, $hasReplacementTable );

			$numRowsText = $this->displayNumRowsForTable( $cdb, $tableName );
			$numColumnsText = $this->displayNumColumnsForTable( $tableName );
			$templatesText = $this->tableTemplatesText( $tableName );

			$rowText .= Html::rawElement( 'td', [ 'class' => 'cargo-tablelist-tablename' ],
				$tableLink );
			$rowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numrows' ],
				$numRowsText );
			$rowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numcolumns' ],
				$numColumnsText );

			$this->displayActionLinks( $listOfColumns, $actionLinks, $rowText );

			if ( !$hasReplacementTable ) {
				$rowText .= Html::rawElement( 'td', null, $templatesText );
				$wikitableText .= Html::rawElement( 'tr', null, $rowText );
				continue;
			}

			// if there's a replacement table, the template links need to span 2 rows
			$rowText .= Html::rawElement( 'td', [ 'rowspan' => 2 ], $templatesText );
			$wikitableText .= Html::rawElement( 'tr', null, $rowText );

			$replacementRowText = '';
			$tableLink = $this->getTableLinkedToView( $tableName, true );

			$numRowsText = $this->displayNumRowsForTable( $cdb, $tableName . '__NEXT' );
			$numColumnsText = $this->displayNumColumnsForTable( $tableName . '__NEXT' );
			$actionLinks = $this->getActionLinksForTable( $tableName, true, false );

			$replacementRowText .= Html::rawElement( 'td',
				[ 'class' => 'cargo-tablelist-tablename' ], $tableLink );
			$replacementRowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numrows' ],
				$numRowsText );
			$replacementRowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numcolumns' ],
				$numColumnsText );

			$this->displayActionLinks( $listOfColumns, $actionLinks, $replacementRowText );

			$wikitableText .= Html::rawElement( 'tr',
				[ 'class' => 'cargo-tablelist-replacement-row' ], $replacementRowText );
		}
		$text .= Html::rawElement( 'table', [ 'class' => 'mw-datatable cargo-tablelist' ],
			$wikitableText );

		// Now display the table for the special Cargo tables.
		$text .= '<br />';
		$text .= Html::element( 'p', null, $this->msg( 'cargo-cargotables-specialtables' )->escaped() );
		$specialTableNames = CargoUtils::specialTableNames();
		$headerText = Html::element( 'th', null, $this->msg( "cargo-cargotables-header-table" ) );
		$headerText .= Html::element( 'th', null,
			$this->msg( "cargo-cargotables-header-rowcount" )->escaped() );
		$headerText .= Html::element( 'th', [ 'class' => 'cargotables-columncount' ],
			$this->msg( "cargo-cargotables-header-columncount" )->escaped() );

		$invalidActionsForSpecialTables = [ 'edit' ];
		foreach ( $listOfColumns as $i => $action ) {
			if ( in_array( $action, $invalidActionsForSpecialTables ) ) {
				unset( $listOfColumns[$i] );
				continue;
			}
			$headerText .= Html::rawElement( 'th', null, $this->getActionIcon( $action ) );
		}
		$wikitableText = Html::rawElement( 'tr', null, $headerText );

		foreach ( $specialTableNames as $specialTableName ) {
			$rowText = '';
			$tableExists = in_array( $specialTableName, $existingSpecialTables );
			if ( $tableExists ) {
				$possibleReplacementTable = $specialTableName . '__NEXT';
				$hasReplacementTable = CargoUtils::tableFullyExists( $possibleReplacementTable );
				$actionLinks = $this->getActionLinksForTable( $specialTableName, false, $hasReplacementTable, true );
			} else {
				$hasReplacementTable = false;
				$actionLinks = [];
				if ( array_key_exists( 'recreate', self::$actionList ) ) {
					$recreateURL =
						SpecialPage::getTitleFor( 'RecreateCargoData' )->getFullURL() . "/$specialTableName";
					$actionLinks['recreate'] = $this->getActionButton( 'create', $recreateURL );
				}
			}
			foreach ( $actionLinks as $action => $actionLink ) {
				if ( in_array( $action, $invalidActionsForSpecialTables ) ) {
					unset( $actionLinks[$action] );
				}
			}
			if ( $tableExists ) {
				$tableLink = $this->getTableLinkedToView( $specialTableName, false );
			} else {
				$tableLink = $specialTableName;
			}
			$rowText .= Html::rawElement( 'td', [ 'class' => 'cargo-tablelist-tablename' ],
				$tableLink );
			if ( $tableExists ) {
				$numRowsText = $this->displayNumRowsForTable( $cdb, $specialTableName );
				$numColumnsText = $this->displayNumColumnsForTable( $specialTableName );
			} else {
				$numRowsText = '';
				$numColumnsText = '';
			}
			$rowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numrows' ],
				$numRowsText );

			$rowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numcolumns' ],
				$numColumnsText );

			$this->displayActionLinks( $listOfColumns, $actionLinks, $rowText );
			$wikitableText .= Html::rawElement( 'tr', null, $rowText );

			if ( !$hasReplacementTable ) {
				continue;
			}

			// if there's a replacement table, the template links need to span 2 rows
			$replacementRowText = '';
			$tableLink = $this->getTableLinkedToView( $specialTableName, true );

			$numRowsText = $this->displayNumRowsForTable( $cdb, $specialTableName . '__NEXT' );
			$numColumnsText = $this->displayNumColumnsForTable( $specialTableName . '__NEXT' );
			$actionLinks = $this->getActionLinksForTable( $specialTableName, true, false );

			$replacementRowText .= Html::rawElement( 'td',
				[ 'class' => 'cargo-tablelist-tablename' ], $tableLink );
			$replacementRowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numrows' ],
				$numRowsText );
			$replacementRowText .= Html::element( 'td', [ 'class' => 'cargo-tablelist-numcolumns' ],
				$numColumnsText );

			$this->displayActionLinks( $listOfColumns, $actionLinks, $replacementRowText );

			$wikitableText .= Html::rawElement( 'tr',
				[ 'class' => 'cargo-tablelist-replacement-row' ], $replacementRowText );
		}

		$text .= Html::rawElement( 'table', [
				'class' => 'mw-datatable cargo-tablelist',
				'style' => 'min-width: auto;'
			],
			$wikitableText );

		return $text;
	}

	private function displayActionLinks( $listOfColumns, $actionLinks, &$rowText ) {
		foreach ( $listOfColumns as $action ) {
			if ( array_key_exists( $action, $actionLinks ) ) {
				$rowText .= Html::rawElement( 'td', [ "class" => "cargo-tablelist-actionbutton" ],
					$actionLinks[$action] );
			} else {
				$rowText .= Html::rawElement( 'td', null, '' );
			}
		}
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

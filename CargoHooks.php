<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

/**
 * CargoHooks class
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoHooks {

	public static function registerExtension() {
		define( 'CARGO_VERSION', '3.5-alpha' );
	}

	public static function initialize() {
		global $cgScriptPath, $wgExtensionAssetsPath, $wgCargoFieldTypes;

		// Script path.
		$cgScriptPath = $wgExtensionAssetsPath . '/Cargo';

		$wgCargoFieldTypes = [
			'Page', 'String', 'Text', 'Integer', 'Float', 'Date',
			'Start date', 'End date', 'Datetime', 'Start datetime',
			'End datetime', 'Boolean', 'Coordinates', 'Wikitext',
			'Wikitext string', 'Searchtext', 'File', 'URL', 'Email',
			'Rating'
		];
	}

	public static function registerParserFunctions( $parser ) {
		$parser->setFunctionHook( 'cargo_declare', [ 'CargoDeclare', 'run' ] );
		$parser->setFunctionHook( 'cargo_attach', [ 'CargoAttach', 'run' ] );
		$parser->setFunctionHook( 'cargo_store', [ 'CargoStore', 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'cargo_query', [ 'CargoQuery', 'run' ] );
		$parser->setFunctionHook( 'cargo_compound_query', [ 'CargoCompoundQuery', 'run' ] );
		$parser->setFunctionHook( 'recurring_event', [ 'CargoRecurringEvent', 'run' ] );
		$parser->setFunctionHook( 'cargo_display_map', [ 'CargoDisplayMap', 'run' ] );
	}

	/**
	 * Add date-related messages to Global JS vars in user language
	 *
	 * @param array &$vars Global JS vars
	 * @param OutputPage $out
	 */
	public static function setGlobalJSVariables( array &$vars, OutputPage $out ) {
		global $wgCargoMapClusteringMinimum;
		global $wgCargoDefaultQueryLimit;

		$vars['wgCargoDefaultQueryLimit'] = $wgCargoDefaultQueryLimit;

		$vars['wgCargoMapClusteringMinimum'] = $wgCargoMapClusteringMinimum;

		// Date-related arrays for the 'calendar' and 'timeline'
		// formats.
		// Built-in arrays already exist for month names, but those
		// unfortunately are based on the language of the wiki, not
		// the language of the user.
		$vars['wgCargoMonthNames'] = $out->getLanguage()->getMonthNamesArray();
		/**
		 * @TODO - should these be switched to objects with keys starting
		 *         from 1 to match month indexes instead of 0-index?
		 */
		array_shift( $vars['wgCargoMonthNames'] ); // start keys from 0

		$vars['wgCargoMonthNamesShort'] = $out->getLanguage()->getMonthAbbreviationsArray();
		array_shift( $vars['wgCargoMonthNamesShort'] ); // start keys from 0

		$vars['wgCargoWeekDays'] = [];
		$vars['wgCargoWeekDaysShort'] = [];
		for ( $i = 1; $i < 8; $i++ ) {
			$vars['wgCargoWeekDays'][] = $out->getLanguage()->getWeekdayName( $i );
			$vars['wgCargoWeekDaysShort'][] = $out->getLanguage()->getWeekdayAbbreviation( $i );
		}
	}

	/**
	 * Add the "purge cache" link to page actions.
	 *
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 * @param SkinTemplate $skinTemplate
	 * @param mixed[] &$links
	 */
	public static function addPurgeCacheTab( SkinTemplate $skinTemplate, array &$links ) {
		$title = $skinTemplate->getTitle();

		// Skip special and nonexistent pages.
		if ( $title->isSpecialPage() || !$title->exists() ) {
			return;
		}

		// Only add this tab if neither the Purge nor SemanticMediaWiki extension
		// (which has its own "purge link") is installed.
		$extReg = ExtensionRegistry::getInstance();
		if ( $extReg->isLoaded( 'SemanticMediaWiki' ) || $extReg->isLoaded( 'Purge' ) ) {
			return;
		}

		if ( $skinTemplate->getUser()->isAllowed( 'purge' ) ) {
			$skinTemplate->getOutput()->addModules( 'ext.cargo.purge' );
			$links['actions']['cargo-purge'] = [
				'class' => false,
				'text' => $skinTemplate->msg( 'cargo-purgecache' )->text(),
				'href' => $title->getLocalUrl( [ 'action' => 'purge' ] )
			];
		}
	}

	public static function addTemplateFieldStart( $field, &$fieldStart ) {
		// If a generated template contains a field of type
		// 'Coordinates', add a #cargo_display_map call to the
		// display of that field.
		if ( $field->getFieldType() == 'Coordinates' ) {
			$fieldStart .= '{{#cargo_display_map:point=';
		}
	}

	public static function addTemplateFieldEnd( $field, &$fieldEnd ) {
		// If a generated template contains a field of type
		// 'Coordinates', add (the end of) a #cargo_display_map call
		// to the display of that field.
		if ( $field->getFieldType() == 'Coordinates' ) {
			$fieldEnd .= '}}';
		}
	}

	/**
	 * Deletes all Cargo data for a specific page - *except* data
	 * contained in Cargo tables which are read-only because their
	 * "replacement table" exists.
	 *
	 * @param int $pageID
	 * @todo - move this to a different class, like CargoUtils?
	 */
	public static function deletePageFromSystem( $pageID ) {
		// We'll delete every reference to this page in the
		// Cargo tables - in the data tables as well as in
		// cargo_pages. (Though we need the latter to be able to
		// efficiently delete from the former.)

		// Get all the "main" tables that this page is contained in.
		$dbw = wfGetDB( DB_PRIMARY );
		$cdb = CargoUtils::getDB();
		$cdb->begin();
		$cdbPageIDCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];

		$res = $dbw->select( 'cargo_pages', 'table_name', [ 'page_id' => $pageID ] );
		foreach ( $res as $row ) {
			$curMainTable = $row->table_name;

			if ( $cdb->tableExists( $curMainTable . '__NEXT' ) ) {
				// It's a "read-only" table - ignore.
				continue;
			}

			// First, delete from the "field" tables.
			$fieldTablesValue = $dbw->selectField( 'cargo_tables', 'field_tables', [ 'main_table' => $curMainTable ] );
			$fieldTableNames = unserialize( $fieldTablesValue );
			foreach ( $fieldTableNames as $curFieldTable ) {
				// Thankfully, the MW DB API already provides a
				// nice method for deleting based on a join.
				$cdb->deleteJoin(
					$curFieldTable,
					$curMainTable,
					$cdb->addIdentifierQuotes( '_rowID' ),
					$cdb->addIdentifierQuotes( '_ID' ),
					$cdbPageIDCheck
				);
			}

			// Delete from the "files" helper table, if it exists.
			$curFilesTable = $curMainTable . '___files';
			if ( $cdb->tableExists( $curFilesTable ) ) {
				$cdb->delete( $curFilesTable, $cdbPageIDCheck );
			}

			// Now, delete from the "main" table.
			$cdb->delete( $curMainTable, $cdbPageIDCheck );
		}

		self::deletePageFromSpecialTable( $pageID, '_pageData' );
		// Unfortunately, once a page has been deleted we can no longer
		// get its namespace (or can we?), so we need to call these
		// deletion methods for all tables every time.
		self::deletePageFromSpecialTable( $pageID, '_fileData' );
		self::deletePageFromSpecialTable( $pageID, '_bpmnData' );
		self::deletePageFromSpecialTable( $pageID, '_ganttData' );

		// Finally, delete from cargo_pages.
		$dbw->delete( 'cargo_pages', [ 'page_id' => $pageID ] );
		CargoBackLinks::managePageDeletion( $pageID );

		// End transaction and apply DB changes.
		$cdb->commit();
	}

	public static function deletePageFromSpecialTable( $pageID, $specialTableName ) {
		$cdb = CargoUtils::getDB();
		// There's a reasonable chance that this table doesn't exist
		// at all - if so, exit.
		if ( !$cdb->tableExists( $specialTableName ) ) {
			return;
		}
		$replacementTableName = $specialTableName . '__NEXT';
		if ( $cdb->tableExists( $replacementTableName ) ) {
			$specialTableName = $replacementTableName;
		}

		$cdbPageIDCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];

		$dbr = wfGetDB( DB_REPLICA );
		$fieldTablesValue = $dbr->selectField( 'cargo_tables', 'field_tables', [ 'main_table' => $specialTableName ] );
		$fieldTableNames = unserialize( $fieldTablesValue );
		foreach ( $fieldTableNames as $curFieldTable ) {
			$cdb->deleteJoin(
				$curFieldTable,
				$specialTableName,
				$cdb->addIdentifierQuotes( '_rowID' ),
				$cdb->addIdentifierQuotes( '_ID' ),
				$cdbPageIDCheck
			);
		}
		$cdb->delete( $specialTableName, $cdbPageIDCheck );
	}

	/**
	 * Called by the MediaWiki 'PageSaveComplete' hook.
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $user,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		// First, delete the existing data.
		$pageID = $wikiPage->getID();
		self::deletePageFromSystem( $pageID );

		// Now parse the page again, so that #cargo_store will be
		// called.
		// Even though the page will get parsed again after the save,
		// we need to parse it here anyway, for the settings we
		// added to remain set.
		CargoStore::$settings['origin'] = 'page save';
		CargoUtils::parsePageForStorage(
			$wikiPage->getTitle(),
			$revisionRecord->getContent( SlotRecord::MAIN )->getText()
		);

		// Also, save data to any relevant "special tables", if they
		// exist.
		self::saveToSpecialTables( $wikiPage->getTitle() );

		// Invalidate pages that reference this page in their Cargo query results.
		CargoBackLinks::purgePagesThatQueryThisPage( $pageID );
	}

	public static function saveToSpecialTables( $title ) {
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $title, $useReplacementTable, false );
		if ( $title->getNamespace() == NS_FILE ) {
			$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
			CargoFileData::storeValuesForFile( $title, $useReplacementTable );
		} elseif ( defined( 'FD_NS_BPMN' ) && $title->getNamespace() == FD_NS_BPMN ) {
			$useReplacementTable = $cdb->tableExists( '_bpmnData__NEXT' );
			CargoBPMNData::storeBPMNValues( $title, $useReplacementTable );
		} elseif ( defined( 'FD_NS_GANTT' ) && $title->getNamespace() == FD_NS_GANTT ) {
			$useReplacementTable = $cdb->tableExists( '_ganttData__NEXT' );
			CargoGanttData::storeGanttValues( $title, $useReplacementTable );
		}
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionApproved( $output, $title, $revID, $content ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// In an unexpected surprise, it turns out that simply adding
		// this setting will (usually) be enough to get the correct
		// revision of this page to be saved by Cargo, since the page
		// will (usually) be parsed right after this.
		// The one exception to that rule is that if it's the latest
		// revision being approved, the page is sometimes not parsed (?) -
		// so in that case, we'll parse it ourselves.
		CargoStore::$settings['origin'] = 'Approved Revs revision approved';
		if ( $revID == $title->getLatestRevID() ) {
			CargoUtils::parsePageForStorage( $title, null );
		}
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $title, $useReplacementTable );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable );
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionUnapproved( $output, $title, $content ) {
		global $egApprovedRevsBlankIfUnapproved;

		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		if ( !$egApprovedRevsBlankIfUnapproved ) {
			// No point storing the Cargo data if it's blank.
			CargoStore::$settings['origin'] = 'Approved Revs revision unapproved';
		}
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $title, $useReplacementTable, true, $egApprovedRevsBlankIfUnapproved );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable );
	}

	/**
	 * Called by the PageMoveComplete hook.
	 *
	 * Updates the entries for a page within all Cargo tables, if the page is renamed/moved.
	 *
	 * @param LinkTarget $old
	 * @param LinkTarget $new
	 * @param UserIdentity $userIdentity Unused
	 * @param int $pageid
	 * @param int $redirid
	 * @param string $reason Unused
	 * @param RevisionRecord $revision Unused
	 */
	public static function onPageMoveComplete( LinkTarget $old, LinkTarget $new, UserIdentity $userIdentity,
		int $pageid, int $redirid, string $reason, RevisionRecord $revision ) {
		// For each main data table to which this page belongs, change
		// the page name-related fields.
		$newPageTitle = $new->getText();
		$newPageNamespace = $new->getNamespace();
		if ( $newPageNamespace === NS_MAIN ) {
			$newPageName = $newPageTitle;
		} else {
			$nsText = MediaWikiServices::getInstance()->getNamespaceInfo()->
				getCanonicalName( $newPageNamespace );
			$newPageName = $nsText . ':' . $newPageTitle;
		}
		$dbw = wfGetDB( DB_PRIMARY );
		$cdb = CargoUtils::getDB();
		$cdb->begin();

		$res = $dbw->select( 'cargo_pages', 'table_name', [ 'page_id' => $pageid ] );
		foreach ( $res as $row ) {
			$curMainTable = $row->table_name;
			$cdb->update( $curMainTable,
				[
					$cdb->addIdentifierQuotes( '_pageName' ) => $newPageName,
					$cdb->addIdentifierQuotes( '_pageTitle' ) => $newPageTitle,
					$cdb->addIdentifierQuotes( '_pageNamespace' ) => $newPageNamespace
				],
				[ $cdb->addIdentifierQuotes( '_pageID' ) => $pageid ]
			);
		}

		// Update the page title in the "general data" tables.
		$generalTables = [ '_pageData', '_fileData' ];
		foreach ( $generalTables as $generalTable ) {
			if ( $cdb->tableExists( $generalTable ) ) {
				// Update in the replacement table, if one exists.
				if ( $cdb->tableExists( $generalTable . '__NEXT' ) ) {
					$generalTable = $generalTable . '__NEXT';
				}
				$cdb->update( $generalTable,
					[
						$cdb->addIdentifierQuotes( '_pageName' ) => $newPageName,
						$cdb->addIdentifierQuotes( '_pageTitle' ) => $newPageTitle,
						$cdb->addIdentifierQuotes( '_pageNamespace' ) => $newPageNamespace
					],
					[ $cdb->addIdentifierQuotes( '_pageID' ) => $pageid ]
				);
			}
		}

		// End transaction and apply DB changes.
		$cdb->commit();

		// Save data for the original page (now a redirect).
		if ( $redirid != 0 ) {
			$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
			$oldTitle = Title::newFromLinkTarget( $old );
			CargoPageData::storeValuesForPage( $oldTitle, $useReplacementTable );
		}
	}

	/**
	 * Deletes all Cargo data about a page, if the page has been deleted.
	 *
	 * Called by the MediaWiki PageDeleteComplete hook.
	 */
	public static function onPageDeleteComplete(
		MediaWiki\Page\ProperPageIdentity $page, MediaWiki\Permissions\Authority $deleter, string $reason,
		int $pageID, MediaWiki\Revision\RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		self::deletePageFromSystem( $pageID );
	}

	/**
	 * Called by the MediaWiki 'UploadComplete' hook.
	 *
	 * Updates a file's entry in the _fileData table if it has been
	 * uploaded or re-uploaded.
	 *
	 * @param Image $image
	 */
	public static function onUploadComplete( $image ) {
		$cdb = CargoUtils::getDB();
		if ( !$cdb->tableExists( '_fileData' ) ) {
			return;
		}
		$title = $image->getLocalFile()->getTitle();
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		$pageID = $title->getArticleID();
		$cdbPageIDCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];
		$fileDataTable = $useReplacementTable ? '_fileData__NEXT' : '_fileData';
		$cdb->delete( $fileDataTable, $cdbPageIDCheck );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable );
	}

	/**
	 * Called by the MediaWiki 'CategoryAfterPageAdded' hook.
	 *
	 * @param Category $category
	 * @param WikiPage $wikiPage
	 */
	public static function addCategoryToPageData( $category, $wikiPage ) {
		self::addOrRemoveCategoryData( $category, $wikiPage, true );
	}

	/**
	 * Called by the MediaWiki 'CategoryAfterPageRemoved' hook.
	 *
	 * @param Category $category
	 * @param WikiPage $wikiPage
	 */
	public static function removeCategoryFromPageData( $category, $wikiPage ) {
		self::addOrRemoveCategoryData( $category, $wikiPage, false );
	}

	/**
	 * We use hooks to modify the _categories field in _pageData, instead of
	 * saving it on page save as is done with all other fields (in _pageData
	 * and elsewhere), because the categories information is often not set
	 * until after the page has already been saved, due to the use of jobs.
	 * We can use the same function for both adding and removing categories
	 * because it's almost the same code either way.
	 * If anything gets messed up in this process, the data can be recreated
	 * by calling setCargoPageData.php.
	 */
	public static function addOrRemoveCategoryData( $category, $wikiPage, $isAdd ) {
		global $wgCargoPageDataColumns;
		if ( !in_array( 'categories', $wgCargoPageDataColumns ) ) {
			return;
		}

		$cdb = CargoUtils::getDB();

		// We need to make sure that the "categories" field table
		// already exists, because we're only modifying it here, not
		// creating it.
		if ( $cdb->tableExists( '_pageData__NEXT___categories' ) ) {
			$pageDataTable = '_pageData__NEXT';
		} elseif ( $cdb->tableExists( '_pageData___categories' ) ) {
			$pageDataTable = '_pageData';
		} else {
			return;
		}
		$categoriesTable = $pageDataTable . '___categories';
		$categoryName = $category->getName();
		$pageID = $wikiPage->getId();

		$cdb = CargoUtils::getDB();
		$cdb->begin();
		$res = $cdb->select( $pageDataTable, '_ID', [ '_pageID' => $pageID ] );
		if ( $res->numRows() == 0 ) {
			$cdb->commit();
			return;
		}
		$row = $res->fetchRow();
		$rowID = $row['_ID'];
		$categoriesForPage = [];
		$res2 = $cdb->select( $categoriesTable, '_value',  [ '_rowID' => $rowID ] );
		foreach ( $res2 as $row2 ) {
			$categoriesForPage[] = $row2->_value;
		}
		$categoryAlreadyListed = in_array( $categoryName, $categoriesForPage );
		// This can be done with a NOT XOR (i.e. XNOR), but let's not make it more confusing.
		if ( ( $isAdd && $categoryAlreadyListed ) || ( !$isAdd && !$categoryAlreadyListed ) ) {
			$cdb->commit();
			return;
		}

		// The real operation is here.
		if ( $isAdd ) {
			$categoriesForPage[] = $categoryName;
		} else {
			foreach ( $categoriesForPage as $i => $cat ) {
				if ( $cat == $categoryName ) {
					unset( $categoriesForPage[$i] );
				}
			}
		}
		$newCategoriesFull = implode( '|', $categoriesForPage );
		$cdb->update( $pageDataTable, [ '_categories__full' => $newCategoriesFull ], [ '_pageID' => $pageID ] );
		if ( $isAdd ) {
			$res3 = $cdb->select( $categoriesTable, 'MAX(_position) as MaxPosition',  [ '_rowID' => $rowID ] );
			$row3 = $res3->fetchRow();
			$maxPosition = $row3['MaxPosition'];
			$cdb->insert( $categoriesTable, [ '_rowID' => $rowID, '_value' => $categoryName, '_position' => $maxPosition + 1 ] );
		} else {
			$cdb->delete( $categoriesTable, [ '_rowID' => $rowID, '_value' => $categoryName ] );
		}

		// End transaction and apply DB changes.
		$cdb->commit();
	}

	public static function describeDBSchema( DatabaseUpdater $updater ) {
		// DB updates
		// For now, there's just a single SQL file for all DB types.

		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionTable( 'cargo_tables', __DIR__ . "/sql/Cargo.sql" );
			$updater->addExtensionTable( 'cargo_pages', __DIR__ . "/sql/Cargo.sql" );
			$updater->addExtensionTable( 'cargo_backlinks', __DIR__ . "/sql/cargo_backlinks.sql" );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_tables', __DIR__ . "/sql/Cargo.pg.sql", true ] );
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_pages', __DIR__ . "/sql/Cargo.pg.sql", true ] );
		}
	}

	/**
	 * Called by a hook in the Admin Links extension.
	 *
	 * @param ALTree $adminLinksTree
	 */
	public static function addToAdminLinks( $adminLinksTree ) {
		$browseSearchSection = $adminLinksTree->getSection(
			wfMessage( 'adminlinks_browsesearch' )->text() );
		$cargoRow = new ALRow( 'cargo' );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoTables' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'Drilldown' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoQuery' ) );
		$browseSearchSection->addRow( $cargoRow );
	}

	/**
	 * Called by MediaWiki's ResourceLoaderStartUpModule::getConfig()
	 * to set static (not request-specific) configuration variables
	 * @param array &$vars
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";
	}

	public static function addLuaLibrary( $engine, &$extraLibraries ) {
		$extraLibraries['mw.ext.cargo'] = CargoLuaLibrary::class;
	}

	public static function cargoSchemaUpdates( DatabaseUpdater $updater ) {
		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionField( 'cargo_tables', 'field_helper_tables', __DIR__ . '/sql/cargo_tables.patch.field_helper_tables.sql' );
			$updater->dropExtensionIndex( 'cargo_tables', 'cargo_tables_template_id', __DIR__ . '/sql/cargo_tables.patch.index_template_id.sql' );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionField( 'cargo_tables', 'field_helper_tables', __DIR__ . '/sql/cargo_tables.patch.field_helper_tables.pg.sql' );
			$updater->dropExtensionIndex( 'cargo_tables', 'cargo_tables_template_id', __DIR__ . '/sql/cargo_tables.patch.index_template_id.pg.sql' );
		}
	}

}

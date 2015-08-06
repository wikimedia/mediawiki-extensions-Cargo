<?php

/**
 * CargoHooks class
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoHooks {

	public static function registerExtension() {
		global $cgScriptPath, $wgScriptPath, $wgCargoFieldTypes, $wgCargoAllowedSQLFunctions, $wgGroupPermissions;

		// Script path.
		$cgScriptPath = $wgScriptPath . '/extensions/Cargo';

		$wgCargoFieldTypes = array( 'Page', 'Text', 'Integer', 'Float', 'Date', 'Datetime', 'Boolean', 'Coordinates', 'Wikitext', 'File' );
		$wgCargoAllowedSQLFunctions = array(
			// Math functions
			'COUNT', 'FLOOR', 'CEIL', 'ROUND',
			'MAX', 'MIN', 'AVG', 'SUM', 'POWER', 'LN', 'LOG',
			// String functions
			'CONCAT', 'LOWER', 'LCASE', 'UPPER', 'UCASE', 'SUBSTRING', 'FORMAT',
			// Date functions
			'DATE', 'DATE_FORMAT', 'DATE_ADD', 'DATE_SUB', 'DATE_DIFF'
		);

		$wgGroupPermissions['sysop']['recreatecargodata'] = true;
		$wgGroupPermissions['sysop']['deletecargodata'] = true;
	}


	public static function registerParserFunctions( &$parser ) {
		$parser->setFunctionHook( 'cargo_declare', array( 'CargoDeclare', 'run' ) );
		$parser->setFunctionHook( 'cargo_attach', array( 'CargoAttach', 'run' ) );
		$parser->setFunctionHook( 'cargo_store', array( 'CargoStore', 'run' ) );
		$parser->setFunctionHook( 'cargo_query', array( 'CargoQuery', 'run' ) );
		$parser->setFunctionHook( 'cargo_compound_query', array( 'CargoCompoundQuery', 'run' ) );
		$parser->setFunctionHook( 'recurring_event', array( 'CargoRecurringEvent', 'run' ) );
		$parser->setFunctionHook( 'cargo_display_map', array( 'CargoDisplayMap', 'run' ) );
		return true;
	}

	/**
	 * Add date-related messages to Global JS vars in user language
	 *
	 * @global int $wgCargoMapClusteringMinimum
	 * @param array $vars Global JS vars
	 * @param OutputPage $out
	 * @return boolean
	 */
	static function setGlobalJSVariables( array &$vars, OutputPage $out ) {
		global $wgCargoMapClusteringMinimum;

		$vars['wgCargoMapClusteringMinimum'] = $wgCargoMapClusteringMinimum;

		// Date-related arrays for the 'calendar' and 'timeline'
		// formats.
		// Built-in arrays already exist for month names, but those
		// unfortunately are based on the language of the wiki, not
		// the language of the user.
		$vars['wgCargoMonthNames'] = $out->getLanguage()->getMonthNamesArray();
		/**
		 * @todo all these arrays should perhaps be switched to start keys from 1, in order to
		 * match built-in arrys, such as wgMonthNames.
		 */
		array_shift( $vars['wgCargoMonthNames'] ); //start keys from 0

		$vars['wgCargoMonthNamesShort'] = $out->getLanguage()->getMonthAbbreviationsArray();
		array_shift( $vars['wgCargoMonthNamesShort'] ); //start keys from 0

		$vars['wgCargoWeekDays'] = array();
		$vars['wgCargoWeekDaysShort'] = array();
		for ( $i = 1; $i < 8; $i++ ) {
			$vars['wgCargoWeekDays'][] = $out->getLanguage()->getWeekdayName( $i );
			$vars['wgCargoWeekDaysShort'][] = $out->getLanguage()->getWeekdayAbbreviation( $i );
		}

		return true;
	}

	/**
	 * Add the "purge cache" tab to actions
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array $links
	 * @return boolean
	 */
	public static function addPurgeCacheTab( SkinTemplate &$skinTemplate, array &$links ) {
		// Only add this tab if Semantic MediaWiki (which has its
		// identical "refresh" tab) is not installed.
		if ( defined( 'SMW_VERSION' ) ) {
			return true;
		}

		if ( $skinTemplate->getUser()->isAllowed( 'purge' ) ) {
			$links['actions']['cargo-purge'] = array(
				'class' => false,
				'text' => $skinTemplate->msg( 'cargo-purgecache' )->text(),
				'href' => $skinTemplate->getTitle()->getLocalUrl( array( 'action' => 'purge' ) )
			);
		}

		return true;
	}

	/**
	 * Delete a page
	 *
	 * @param int $pageID
	 * @TODO - move this to a different class, like CargoUtils?
	 */
	public static function deletePageFromSystem( $pageID ) {
		// We'll delete every reference to this page in the
		// Cargo tables - in the data tables as well as in
		// cargo_pages. (Though we need the latter to be able to
		// efficiently delete from the former.)

		// Get all the "main" tables that this page is contained in.
		$dbr = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		$res = $dbr->select( 'cargo_pages', 'table_name', array( 'page_id' => $pageID ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$curMainTable = $row['table_name'];

			// First, delete from the "field" tables.
			$res2 = $dbr->select( 'cargo_tables', 'field_tables', array( 'main_table' => $curMainTable ) );
			$row2 = $dbr->fetchRow( $res2 );
			$fieldTableNames = unserialize( $row2['field_tables'] );
			foreach ( $fieldTableNames as $curFieldTable ) {
				// Thankfully, the MW DB API already provides a
				// nice method for deleting based on a join.
				$cdb->deleteJoin(
					$curFieldTable, $curMainTable, '_rowID', '_ID', array( '_pageID' => $pageID ) );
			}

			// Now, delete from the "main" table.
			$cdb->delete( $curMainTable, array( '_pageID' => $pageID ) );
		}

		// Finally, delete from cargo_pages.
		$dbr->delete( 'cargo_pages', array( 'page_id' => $pageID ) );

		// This call is needed to get deletions to actually happen.
		$cdb->close();
	}

	/**
	 * Called by the MediaWiki 'PageContentSaveComplete' hook.
	 *
	 * We use that hook, instead of 'PageContentSave', because we need
	 * the page ID to have been set already for newly-created pages.
	 *
	 * @global Parser $wgParser
	 * @param WikiPage $article
	 * @param User $user Unused
	 * @param Content $content
	 * @param string $summary Unused
	 * @param boolean $isMinor Unused
	 * @param null $isWatch Unused
	 * @param null $section Unused
	 * @param int $flags Unused
	 * @param Status $status Unused
	 * @return boolean
	 */
	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor,
		$isWatch, $section, $flags, $status ) {
		// First, delete the existing data.
		$pageID = $article->getID();
		self::deletePageFromSystem( $pageID );

		// Now parse the page again, so that #cargo_store will be
		// called.
		// Even though the page will get parsed again after the save,
		// we need to parse it here anyway, for the settings we
		// added to remain set.
		CargoStore::$settings['origin'] = 'page save';
		CargoUtils::parsePageForStorage( $article->getTitle(), $content->getNativeData() );

		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionApproved( $parser, $title, $revID ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// In an unexpected surprise, it turns out that simply adding
		// this setting will be enough to get the correct revision of
		// this page to be saved by Cargo, since the page will be
		// parsed right after this.
		CargoStore::$settings['origin'] = 'Approved Revs revision approved';
		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionUnapproved( $parser, $title ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// This is all we need - see onARRevisionApproved(), above.
		CargoStore::$settings['origin'] = 'Approved Revs revision unapproved';
		return true;
	}

	/**
	 *
	 * @param Title $title Unused
	 * @param Title $newtitle
	 * @param User $user Unused
	 * @param int $oldid
	 * @param int $newid Unused
	 * @param string $reason Unused
	 * @return boolean
	 */
	public static function onTitleMoveComplete( Title &$title, Title &$newtitle, User &$user, $oldid,
		$newid, $reason ) {
		// For each main data table to which this page belongs, change
		// the page name-related fields.
		$newPageName = $newtitle->getPrefixedText();
		$newPageTitle = $newtitle->getText();
		$newPageNamespace = $newtitle->getNamespace();
		$dbr = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		// We use $oldid, because that's the page ID - $newid is the
		// ID of the redirect page.
		// @TODO - do anything with the redirect?
		$res = $dbr->select( 'cargo_pages', 'table_name', array( 'page_id' => $oldid ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$curMainTable = $row['table_name'];
			$cdb->update( $curMainTable,
				array(
					'_pageName' => $newPageName,
					'_pageTitle' => $newPageTitle,
					'_pageNamespace' => $newPageNamespace
				),
				array( '_pageID' => $oldid )
			);
		}

		// This call is needed to get the update to occur.
		$cdb->close();

		return true;
	}

	/**
	 * Deletes all Cargo data about a page, if the page has been deleted.
	 */
	public static function onArticleDeleteComplete( &$article, User &$user, $reason, $id, $content,
		$logEntry ) {
		self::deletePageFromSystem( $id );
		return true;
	}

	public static function describeDBSchema( DatabaseUpdater $updater ) {
		// DB updates
		// For now, there's just a single SQL file for all DB types.

		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionTable( 'cargo_tables', __DIR__ . "/sql/Cargo.sql" );
			$updater->addExtensionTable( 'cargo_pages', __DIR__ . "/sql/Cargo.sql" );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionUpdate( array( 'addTable', 'cargo_tables', __DIR__ . "/sql/Cargo.pg.sql", true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cargo_pages', __DIR__ . "/sql/Cargo.pg.sql", true ) );
		}
		return true;
	}

	/**
	 * Called by a hook in the Admin Links extension.
	 *
	 * @param ALTree $adminLinksTree
	 * @return boolean
	 */
	public static function addToAdminLinks( &$adminLinksTree ) {
		$browseSearchSection = $adminLinksTree->getSection(
			wfMessage( 'adminlinks_browsesearch' )->text() );
		$cargoRow = new ALRow( 'cargo' );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoTables' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'Drilldown' ) );
		$browseSearchSection->addRow( $cargoRow );

		return true;
	}

	/**
	 * Called by MediaWiki's ResourceLoaderStartUpModule::getConfig()
	 * to set static (not request-specific) configuration variables
	 * @param array $vars
	*/
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";

		return true;
	}

}

<?php

/**
 * This script creates, or recreates, one or more Cargo database tables,
 * based on the relevant data contained in the wiki.
 *
 * Usage:
 *  php cargoRecreateData.php [--table tableName] [--quiet] etc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author Yaron Koren
 * @ingroup Maintenance
 */

if ( getenv('MW_INSTALL_PATH') ) {
	require_once( getenv('MW_INSTALL_PATH') . '/maintenance/Maintenance.php' );
} else {
	require_once( dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php' );
}

$maintClass = "CargoRecreateData";

class CargoRecreateData extends Maintenance {

	public function __construct() {
		parent::__construct();

		// MW 1.28
		if ( method_exists( $this, 'requireExtension' ) ) {
			$this->requireExtension( 'Cargo' );
		}
		$this->mDescription = "Recreate the data for one or more Cargo database tables.";
		$this->addOption( 'table', 'The Cargo table to recreate', false, true );
	}

	public function execute() {
		$quiet = $this->getOption( 'quiet' );
		$this->templatesThatDeclareTables = CargoUtils::getAllPageProps( 'CargoTableName' );
		$this->templatesThatAttachToTables = CargoUtils::getAllPageProps( 'CargoAttachedTable' );

		$tableName = $this->getOption( 'table' );
		if ( $tableName == null ) {
			$tableNames = CargoUtils::getTables();
			foreach ( $tableNames as $i => $tableName ) {
				if ( $tableName == '_pageData' || $tableName == '_fileData' ) {
					// This is handled in a separate script.
					continue;
				}
				if ( $i > 0 && !$quiet ) {
					print "\n";
				}
				$this->recreateAllDataForTable( $tableName );
			}
		} else {
			$this->recreateAllDataForTable( $tableName );
		}
	}

	function recreateAllDataForTable( $tableName ) {
		$quiet = $this->getOption( 'quiet' );

		// Quick retrieval and check before we do anything else.
		$templatePageID = CargoUtils::getTemplateIDForDBTable( $tableName );
		if ( $templatePageID == null ) {
			print "Table \"$tableName\" is not declared in any template.\n";
			return;
		}


		if ( !$quiet ) {
			print "Recreating data for Cargo table $tableName in 5 seconds... hit [Ctrl]-C to escape.\n";
			// No point waiting if the user doesn't know about it
			// anyway, right?
			sleep( 5 );
		}

		if ( !$quiet ) {
			print "Deleting and recreating table...\n";
		}
		CargoUtils::recreateDBTablesForTemplate( $templatePageID, $tableName );

		// These arrays are poorly named. @TODO - fix names
		if ( array_key_exists( $tableName, $this->templatesThatDeclareTables ) ) {
			$templatesThatDeclareThisTable = $this->templatesThatDeclareTables[$tableName];
		} else {
			// This shouldn't happen, given that we already did
			// a check.
			print "Table \"$tableName\" is not declared in any template.\n";
			return;
		}

		if ( array_key_exists( $tableName, $this->templatesThatAttachToTables ) ) {
			$templatesThatAttachToThisTable = $this->templatesThatAttachToTables[$tableName];
		} else {
			$templatesThatAttachToThisTable = array();
		}
		$templatesForThisTable = array_merge( $templatesThatDeclareThisTable, $templatesThatAttachToThisTable );

		foreach( $templatesForThisTable as $templatePageID ) {
			$templateTitle = Title::newFromID( $templatePageID );
			if( $templateTitle == null ) {
				// It is possible that the Template to which the table is associated, is now deleted by the user
				print "Template (Template Page ID = $templatePageID) does not exist, cannot recreate data corresponding to this template\n";
				continue;
			}
			if ( !$quiet ) {
				print "Handling template that adds to this table: " . $templateTitle->getText() . "\n";
			}

			$offset = 0;
			do {
				$titlesWithThisTemplate = $templateTitle->getTemplateLinksTo( array(
					'LIMIT' => 500, 'OFFSET' => $offset ) );
				if ( !$quiet ) {
					print "Saving data for pages " . ( $offset + 1 ) . " to " . ( $offset + count( $titlesWithThisTemplate ) ) . " that call this template...\n";
				}

				foreach( $titlesWithThisTemplate as $title ) {
					// All we need to do here is set some global variables based
					// on the parameters of this job, then parse the page -
					// the #cargo_store function will take care of the rest.
					CargoStore::$settings['origin'] = 'template';
					CargoStore::$settings['dbTableName'] = $tableName;
					$wikiPage = WikiPage::newFromID( $title->getArticleID() );
					$content = $wikiPage->getContent();
					$contentText = ContentHandler::getContentText( $content );
					CargoUtils::parsePageForStorage( $title, $contentText );
				}
				$offset += 500;
			} while ( count( $titlesWithThisTemplate ) == 500 );
		}
	}

}

require_once( DO_MAINTENANCE );

<?php

use MediaWiki\MediaWikiServices;

/**
 * Static functions for dealing with the "_pageData" table.
 *
 * @author Yaron Koren
 */
class CargoPageData {

	/**
	 * Set the schema based on what has been entered in LocalSettings.php.
	 * Strings are used to set the field names; it would have been
	 * better to use constants (like CARGO_CREATION_DATE or
	 * CargoPageData::CREATION_DATE instead of 'creationDate') but
	 * unfortunately the extension.json system doesn't support any kind
	 * of constants.
	 *
	 * @return CargoTableSchema
	 */
	public static function getTableSchema() {
		global $wgCargoPageDataColumns;

		$fieldTypes = [];

		// @TODO - change this code to match the approach taken in
		// CargoFileData.php. This will be more important if/when
		// some additional parameter is added, like 'hidden'.
		if ( in_array( 'creationDate', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creationDate'] = [ 'Datetime', false ];
		}
		if ( in_array( 'modificationDate', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_modificationDate'] = [ 'Datetime', false ];
		}
		if ( in_array( 'creator', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creator'] = [ 'String', false ];
		}
		if ( in_array( 'fullText', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_fullText'] = [ 'Searchtext', false ];
		}
		if ( in_array( 'categories', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_categories'] = [ 'String', true ];
		}
		if ( in_array( 'numRevisions', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_numRevisions'] = [ 'Integer', false ];
		}
		if ( in_array( 'isRedirect', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_isRedirect'] = [ 'Boolean', false ];
		}
		if ( in_array( 'pageNameOrRedirect', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_pageNameOrRedirect'] = [ 'String', false ];
		}
		if ( in_array( 'pageIDOrRedirect', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_pageIDOrRedirect'] = [ 'Integer', false ];
		}
		if ( in_array( 'lastEditor', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_lastEditor'] = [ 'String', false ];
		}

		$tableSchema = new CargoTableSchema();
		foreach ( $fieldTypes as $field => $fieldVals ) {
			list( $type, $isList ) = $fieldVals;
			$fieldDesc = new CargoFieldDescription();
			$fieldDesc->mType = $type;
			if ( $isList ) {
				$fieldDesc->mIsList = true;
				$fieldDesc->setDelimiter( '|' );
			}
			$tableSchema->mFieldDescriptions[$field] = $fieldDesc;
		}

		return $tableSchema;
	}

	/**
	 * The $setToBlank argument is a bit of a hack - used right now only
	 * for "blank if unapproved" with the Approved Revs extension, because
	 * that setting doesn't seem to take effect soon enough to get parsed
	 * as a blank page.
	 *
	 * @param Title $title
	 * @param bool $createReplacement
	 * @param bool $storeCategories
	 * @param bool $setToBlank
	 */
	public static function storeValuesForPage(
		Title $title, $createReplacement, $storeCategories = true, $setToBlank = false
	) {
		global $wgCargoPageDataColumns;

		if ( $title == null ) {
			return;
		}

		$pageDataTable = $createReplacement ? '_pageData__NEXT' : '_pageData';

		// If this table does not exist, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ $pageDataTable ] );
		} catch ( MWException $e ) {
			return;
		}

		$wikiPage = CargoUtils::makeWikiPage( $title );
		$pageDataValues = [];

		if ( in_array( 'creationDate', $wgCargoPageDataColumns ) ) {
			if ( method_exists( 'MediaWiki\Revision\RevisionLookup', 'getFirstRevision' ) ) {
				// MW >= 1.35
				$firstRevision = MediaWikiServices::getInstance()->getRevisionLookup()->getFirstRevision( $title );
			} else {
				$firstRevision = $title->getFirstRevision();
			}
			if ( $firstRevision == null ) {
				// This can sometimes happen.
				$pageDataValues['_creationDate'] = null;
			} else {
				$pageDataValues['_creationDate'] = $firstRevision->getTimestamp();
			}
		}
		if ( in_array( 'modificationDate', $wgCargoPageDataColumns ) ) {
			$pageDataValues['_modificationDate'] = $wikiPage->getTimestamp();
		}
		if ( in_array( 'creator', $wgCargoPageDataColumns ) ) {
			$pageDataValues['_creator'] = $wikiPage->getCreator();
		}
		if ( in_array( 'fullText', $wgCargoPageDataColumns ) ) {
			if ( $setToBlank ) {
				$pageDataValues['_fullText'] = '';
			} else {
				$page = CargoUtils::makeWikiPage( $title );
				$pageDataValues['_fullText'] = ContentHandler::getContentText( $page->getContent() );
			}
		}
		if ( $storeCategories && in_array( 'categories', $wgCargoPageDataColumns ) ) {
			$pageCategories = [];
			if ( !$setToBlank ) {
				$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
				$dbr = $lb->getConnectionRef( DB_REPLICA );
				$res = $dbr->select(
					'categorylinks',
					'cl_to',
					[ 'cl_from' => $title->getArticleID() ],
					__METHOD__
				);
				foreach ( $res as $row ) {
					$pageCategories[] = str_replace( '_', ' ', $row->cl_to );
				}
			}

			$pageCategoriesString = implode( '|', $pageCategories );
			$pageDataValues['_categories'] = $pageCategoriesString;
		}
		if ( in_array( 'numRevisions', $wgCargoPageDataColumns ) ) {
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbr = $lb->getConnectionRef( DB_REPLICA );
			$pageDataValues['_numRevisions'] = $dbr->selectRowCount(
				'revision',
				'*',
				[ 'rev_page' => $title->getArticleID() ],
				__METHOD__
			);
		}
		if ( in_array( 'isRedirect', $wgCargoPageDataColumns ) ) {
			$pageDataValues['_isRedirect'] = ( $title->isRedirect() ? 1 : 0 );
		}
		// Check whether the page is a redirect only once,
		// and evaluate pageNameOrRedirect and pageIDOrRedirect at the same time
		if ( in_array( 'pageNameOrRedirect', $wgCargoPageDataColumns ) || in_array( 'pageIDOrRedirect', $wgCargoPageDataColumns ) ) {
			// case when redirect
			if ( $title->isRedirect() ) {
				$page = CargoUtils::makeWikiPage( $title );
				$redirTitle = $page->getRedirectTarget();
				if ( $redirTitle !== null ) {
					if ( in_array( 'pageNameOrRedirect', $wgCargoPageDataColumns ) ) {
						$pageDataValues['_pageNameOrRedirect'] = $redirTitle->getPrefixedText();
					}
					if ( in_array( 'pageIDOrRedirect', $wgCargoPageDataColumns ) ) {
						$pageDataValues['_pageIDOrRedirect'] = $redirTitle->getArticleID();
					}
				}
			// case when not a redirect
			} else {
				if ( in_array( 'pageNameOrRedirect', $wgCargoPageDataColumns ) ) {
					$pageDataValues['_pageNameOrRedirect'] = $title->getPrefixedText();
				}
				if ( in_array( 'pageIDOrRedirect', $wgCargoPageDataColumns ) ) {
					$pageDataValues['_pageIDOrRedirect'] = $title->getArticleID();
				}
			}
		}
		if ( in_array( 'lastEditor', $wgCargoPageDataColumns ) ) {
			$latestRevision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
			if ( $latestRevision == null ) {
				$pageDataValues['_lastEditor'] = null;
			} else {
				$pageDataValues['_lastEditor'] = $latestRevision->getUser()->getName();
			}
		}

		$pageDataSchema = $tableSchemas[$pageDataTable];
		// If this is being called as a result of a page save, we
		// don't handle the '_categories' field, because categories
		// often don't get set until after the page has been saved,
		// due to jobs. Instead there are separate hooks to handle it.
		if ( !$storeCategories ) {
			$pageDataSchema->removeField( '_categories' );
		}

		CargoStore::storeAllData( $title, $pageDataTable, $pageDataValues, $pageDataSchema );
	}

}

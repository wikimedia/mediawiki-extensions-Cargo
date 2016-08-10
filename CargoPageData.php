<?php

/**
 * Static functions for dealing with the "_pageData" table.
 *
 * @author Yaron Koren
 */
class CargoPageData {

	// Constants for possible table fields/columns
	const CREATION_DATE = 1;
	const MODIFICATION_DATE = 2;
	const CREATOR = 3;
	const FULL_TEXT = 4;
	const CATEGORIES = 5;
	const NUM_REVISIONS = 6;

	static function getTableSchema() {
		global $wgCargoPageDataColumns;

		$fieldTypes = array();

		if ( in_array( self::CREATION_DATE, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creationDate'] = array( 'Date', false );
		}
		if ( in_array( self::MODIFICATION_DATE, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_modificationDate'] = array( 'Date', false );
		}
		if ( in_array( self::CREATOR, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creator'] = array( 'String', false );
		}
		if ( in_array( self::FULL_TEXT, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_fullText'] = array( 'Searchtext', false );
		}
		if ( in_array( self::CATEGORIES, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_categories'] = array( 'String', true );
		}
		if ( in_array( self::NUM_REVISIONS, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_numRevisions'] = array( 'Integer', false );
		}

		$tableSchema = new CargoTableSchema();
		foreach ( $fieldTypes as $field => $fieldVals ) {
			list ( $type, $isList ) = $fieldVals;
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
	 */
	public static function storeValuesForPage( $title, $setToBlank = false ) {
		global $wgCargoPageDataColumns;

		if ( $title == null ) {
			return;
		}

		// If there is no _pageData table, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( array( '_pageData' ) );
		} catch ( MWException $e ) {
			return;
		}

		$wikiPage = WikiPage::factory( $title );
		$pageDataValues = array();

		if ( in_array( self::CREATION_DATE, $wgCargoPageDataColumns ) ) {
			$firstRevision = $title->getFirstRevision();
			if ( $firstRevision == null ) {
				// This can sometimes happen.
				$pageDataValues['_creationDate'] = null;
			} else {
				$pageDataValues['_creationDate'] = $firstRevision->getTimestamp();
			}
		}
		if ( in_array( self::MODIFICATION_DATE, $wgCargoPageDataColumns ) ) {
			$pageDataValues['_modificationDate'] = $wikiPage->getTimestamp();
		}
		if ( in_array( self::CREATOR, $wgCargoPageDataColumns ) ) {
			$pageDataValues['_creator'] = $wikiPage->getCreator();
		}
		if ( in_array( self::FULL_TEXT, $wgCargoPageDataColumns ) ) {
			if ( $setToBlank ) {
				$pageDataValues['_fullText'] = '';
			} else {
				$article = new Article( $title );
				$pageDataValues['_fullText'] = $article->getContent();
			}
		}
		if ( in_array( self::CATEGORIES, $wgCargoPageDataColumns ) ) {
			$pageCategories = array();
			if ( !$setToBlank ) {
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'categorylinks',
					'cl_to',
					array( 'cl_from' => $title->getArticleID() ),
					__METHOD__
				);
				foreach ( $res as $row ) {
					$pageCategories[] = $row->cl_to;
				}
			}

			$pageCategoriesString = implode( '|', $pageCategories );
			$pageDataValues['_categories'] = $pageCategoriesString;
		}
		if ( in_array( self::NUM_REVISIONS, $wgCargoPageDataColumns ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'revision',
				'COUNT(*)',
				array( 'rev_page' => $title->getArticleID() ),
				__METHOD__
			);
			$row = $dbr->fetchRow( $res );
			$pageDataValues['_numRevisions'] = $row[0];
		}

		CargoStore::storeAllData( $title, '_pageData', $pageDataValues, $tableSchemas['_pageData'] );
	}

}

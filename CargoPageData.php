<?php

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
	 */
	static function getTableSchema() {
		global $wgCargoPageDataColumns;

		$fieldTypes = array();

		if ( in_array( 'creationDate', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creationDate'] = array( 'Date', false );
		}
		if ( in_array( 'modificationDate', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_modificationDate'] = array( 'Date', false );
		}
		if ( in_array( 'creator', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creator'] = array( 'String', false );
		}
		if ( in_array( 'fullText', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_fullText'] = array( 'Searchtext', false );
		}
		if ( in_array( 'categories', $wgCargoPageDataColumns ) ) {
			$fieldTypes['_categories'] = array( 'String', true );
		}
		if ( in_array( 'numRevisions', $wgCargoPageDataColumns ) ) {
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

		if ( in_array( 'creationDate', $wgCargoPageDataColumns ) ) {
			$firstRevision = $title->getFirstRevision();
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
				$article = new Article( $title );
				$pageDataValues['_fullText'] = $article->getContent();
			}
		}
		if ( in_array( 'categories', $wgCargoPageDataColumns ) ) {
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
		if ( in_array( 'numRevisions', $wgCargoPageDataColumns ) ) {
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

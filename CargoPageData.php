<?php

/**
 * Static functions for dealing with the "_pageData" table.
 *
 * @author Yaron Koren
 */
class CargoPageData {

	static function getTableSchema() {
		global $wgCargoPageDataColumns;

		$fieldTypes = array();

		if ( in_array( CARGO_STORE_CREATION_DATE, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creationDate'] = array( 'Date', false );
		}
		if ( in_array( CARGO_STORE_MODIFICATION_DATE, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_modificationDate'] = array( 'Date', false );
		}
		if ( in_array( CARGO_STORE_CREATOR, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_creator'] = array( 'String', false );
		}
		if ( in_array( CARGO_STORE_FULL_TEXT, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_fullText'] = array( 'Searchtext', false );
		}
		if ( in_array( CARGO_STORE_CATEGORIES, $wgCargoPageDataColumns ) ) {
			$fieldTypes['_categories'] = array( 'String', true );
		}
		if ( in_array( CARGO_STORE_NUM_REVISIONS, $wgCargoPageDataColumns ) ) {
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

	public static function storeValuesForPage( $title ) {
		global $wgCargoPageDataColumns;

		if ( $title == null ) {
			return;
		}

                $wikiPage = WikiPage::factory( $title );
                $pageDataValues = array();

                if ( in_array( CARGO_STORE_CREATION_DATE, $wgCargoPageDataColumns ) ) {
                        $firstRevision = $title->getFirstRevision();
                        if ( $firstRevision == null ) {
                                // This can sometimes happen.
                                $pageDataValues['_creationDate'] = null;
                        } else {
                                $pageDataValues['_creationDate'] = $firstRevision->getTimestamp();
                        }
                }
                if ( in_array( CARGO_STORE_MODIFICATION_DATE, $wgCargoPageDataColumns ) ) {
                        $pageDataValues['_modificationDate'] = $wikiPage->getTimestamp();
                }
                if ( in_array( CARGO_STORE_CREATOR, $wgCargoPageDataColumns ) ) {
                        $pageDataValues['_creator'] = $wikiPage->getCreator();
                }
                if ( in_array( CARGO_STORE_FULL_TEXT, $wgCargoPageDataColumns ) ) {
                        $article = new Article( $title );
                        $pageDataValues['_fullText'] = $article->getContent();
                }
                if ( in_array( CARGO_STORE_CATEGORIES, $wgCargoPageDataColumns ) ) {
                        $pageCategories = array();
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

                        $pageCategoriesString = implode( '|', $pageCategories );
                        $pageDataValues['_categories'] = $pageCategoriesString;
		}
                if ( in_array( CARGO_STORE_NUM_REVISIONS, $wgCargoPageDataColumns ) ) {
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

                $tableSchemas = CargoUtils::getTableSchemas( array( '_pageData' ) );
                if ( !array_key_exists( '_pageData', $tableSchemas ) ) {
                        return false;
                }

                CargoStore::storeAllData( $title, '_pageData', $pageDataValues, $tableSchemas['_pageData'] );
	}

}

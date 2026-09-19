<?php

use MediaWiki\MediaWikiServices;

class CargoBackLinks {

	/**
	 * ParserOutput extension data key for backlinks.
	 */
	public const BACKLINKS_DATA_KEY = 'ext-cargo-backlinks';

	public static function managePageDeletion( $pageId ) {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $pageId );
		$pageTitle = $page ? $page->getTitle() : null;
		if ( $pageTitle ) {
			$pageId = $pageTitle->getArticleID();
			// Purge the cache of all pages that may have this
			// page in their displayed query results.
			self::purgePagesThatQueryThisPage( $pageId );
		}
		// Remove all entries that are based on queries that were
		// on this page.
		self::removeBackLinks( $pageId );
	}

	public static function removeBackLinks( $pageId ) {
		global $wgCargoIgnoreBacklinks;
		if ( $wgCargoIgnoreBacklinks ) {
			return;
		}

		$dbw = CargoUtils::getMainDBForWrite();
		if ( $dbw->tableExists( 'cargo_backlinks', __METHOD__ ) && !$dbw->isReadOnly() ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'cargo_backlinks' )
				->where( [ 'cbl_query_page_id' => $pageId ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public static function setBackLinks( $title, $resultsPageIds ) {
		global $wgCargoIgnoreBacklinks;
		if ( $wgCargoIgnoreBacklinks ) {
			return;
		}

		$dbw = CargoUtils::getMainDBForWrite();
		if ( !$dbw->tableExists( 'cargo_backlinks', __METHOD__ ) || $dbw->isReadOnly() ) {
			return;
		}
		// Sanity check
		$resultsPageIds = array_unique( $resultsPageIds );

		$pageId = $title->getArticleID();
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'cargo_backlinks' )
			->where( [ 'cbl_query_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();

		$rows = [];
		foreach ( $resultsPageIds as $resultPageId ) {
			if ( $resultPageId ) {
				$rows[] = [
					'cbl_query_page_id' => $pageId,
					'cbl_result_page_id' => $resultPageId,
				];
			}
		}
		if ( $rows ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'cargo_backlinks' )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public static function purgePagesThatQueryThisPage( $resultPageId ) {
		global $wgCargoIgnoreBacklinks;
		if ( $wgCargoIgnoreBacklinks ) {
			return;
		}

		$dbr = CargoUtils::getMainDBForRead();
		if ( !$dbr->tableExists( 'cargo_backlinks', __METHOD__ ) ) {
			return;
		}

		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'cbl_query_page_id' ] )
			->from( 'cargo_backlinks' )
			->where( [ 'cbl_result_page_id' => $resultPageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		foreach ( $res as $row ) {
			$queryPageId = $row->cbl_query_page_id;
			if ( $queryPageId ) {
				$page = $wikiPageFactory->newFromID( $queryPageId );
				if ( $page ) {
					$page->doPurge();
				}
			}
		}
	}
}

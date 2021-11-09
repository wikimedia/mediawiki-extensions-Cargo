<?php

use MediaWiki\MediaWikiServices;

class CargoBackLinks {
	public static function managePageDeletion( $pageId ) {
		$page = \WikiPage::newFromID( $pageId );
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
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		if ( $dbw->tableExists( 'cargo_backlinks' ) ) {
			$dbw->delete( 'cargo_backlinks', [
				'cbl_query_page_id' => $pageId
			], __METHOD__ );
		}
	}

	public static function setBackLinks( $title, $resultsPageIds ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		if ( !$dbw->tableExists( 'cargo_backlinks' ) ) {
			return;
		}
		// Sanity check
		$resultsPageIds = array_unique( $resultsPageIds );
		$pageId = $title->getArticleID();
		self::removeBackLinks( $pageId );

		foreach ( $resultsPageIds as $resultPageId ) {
			if ( $resultPageId ) {
				$dbw->insert( 'cargo_backlinks', [
					'cbl_query_page_id' => $pageId,
					'cbl_result_page_id' => $resultPageId,
				 ] );
			}
		}
	}

	public static function purgePagesThatQueryThisPage( $resultPageId ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		if ( !$dbr->tableExists( 'cargo_backlinks' ) ) {
			return;
		}

		$res = $dbr->select( 'cargo_backlinks',
			[ 'cbl_query_page_id' ],
			[ 'cbl_result_page_id' => $resultPageId ] );
		foreach ( $res as $row ) {
			$queryPageId = $row->cbl_query_page_id;
			if ( $queryPageId ) {
				$page = \WikiPage::newFromID( $queryPageId );
				if ( $page ) {
					$page->doPurge();
				}
			}
		}
	}
}

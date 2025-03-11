<?php
/**
 * @ingroup Cargo
 * @file
 */

use MediaWiki\Feed\FeedItem;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Handle the feed export format.
 * @since 3.5
 */
class CargoFeedFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'feed type' => [ 'values' => [ 'atom', 'rss' ] ],
			'link text' => [ 'type' => 'string' ],
			'feed title' => [ 'type' => 'string' ],
			'feed description' => [ 'type' => 'string' ],
		];
	}

	/**
	 * @param CargoSQLQuery[] $sqlQueries
	 * @param string[] $displayParams Unused
	 * @param string[]|null $querySpecificParams Unused
	 * @return string An HTML link to Special:CargoExport with the required query string.
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'feed';

		// Feed type. Defaults to the first one set in $wgAdvertisedFeedTypes.
		$queryParams['feed type'] = $this->getFeedType( $displayParams['feed type'] ?? null );

		// Feed title.
		if ( isset( $displayParams['feed title'] ) && trim( $displayParams['feed title'] ) != '' ) {
			$queryParams['feed title'] = $displayParams['feed title'];
		}

		// Feed description.
		if ( isset( $displayParams['feed description'] ) && trim( $displayParams['feed description'] ) !== '' ) {
			$queryParams['feed description'] = $displayParams['feed description'];
		}

		// Link.
		if ( isset( $displayParams['link text'] ) && $displayParams['link text'] ) {
			$linkText = $displayParams['link text'];
		} else {
			// The following messages can be used here:
			// * feed-rss
			// * feed-atom
			$feedName = wfMessage( 'feed-' . $queryParams['feed type'] );
			$linkText = wfMessage( 'cargo-viewfeed', [ $feedName ] )->parse();
		}

		// Output full anchor element. The array_filter is to avoid empty params.
		$export = SpecialPage::getTitleFor( 'CargoExport' );
		return Html::element( 'a', [ 'href' => $export->getFullURL( array_filter( $queryParams ) ) ], $linkText );
	}

	/**
	 * Determine the feed type (Atom or RSS).
	 * @param string|null $in The user-provided value.
	 * @return string Either 'atom' or 'rss'.
	 */
	private function getFeedType( ?string $in = null ): string {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$types = array_keys( $config->get( 'FeedClasses' ) );

		// User-provided (if it's valid).
		$inType = strtolower( trim( $in ?? '' ) );
		if ( in_array( $inType, $types ) ) {
			return $inType;
		}

		// Otherwise, fall back to Atom.
		return 'atom';
	}

	/**
	 * Get the iCalendar format output.
	 * @param WebRequest $request
	 * @param CargoSQLQuery[] $sqlQueries
	 * @return string
	 */
	public function outputFeed( WebRequest $request, $sqlQueries ) {
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'feed';
		$feedType = $this->getFeedType( $request->getText( 'feed_type' ) );
		$title = $request->getText( 'feed_title', 'News feed' );
		$description = $request->getText( 'feed_description', '' );

		$feedClasses = MediaWikiServices::getInstance()->getMainConfig()->get( 'FeedClasses' );
		/** @var RSSFeed|AtomFeed $feed */
		$feed = new $feedClasses[$feedType]( $title, $description, $request->getFullRequestURL() );

		$services = MediaWikiServices::getInstance();
		$parser = $services->getParser();
		$pageTitle = $parser->getTitle();
		$parserOptions = ParserOptions::newFromAnon();
		if ( method_exists( $parserOptions, 'setSuppressSectionEditLinks' ) ) {
			// MW 1.42+
			$parserOptions->setSuppressSectionEditLinks();
		}
		$contentRenderer = $services->getContentRenderer();
		$items = [];
		foreach ( $sqlQueries as $sqlQuery ) {
			$dateFields = $sqlQuery->getMainStartAndEndDateFields();

			$queryResults = $sqlQuery->run();
			foreach ( $queryResults as $queryResult ) {
				$title = Title::newFromText( $queryResult['_pageName'] );
				if ( isset( $queryResult['description'] ) ) {
					$description = $queryResult['description'];
					$parserOutput = $parser->parse( $description, $pageTitle, $parserOptions );
				} else {
					$wikiPage = new WikiPage( $title );
					$parserOutput = $contentRenderer->getParserOutput( $wikiPage->getContent(), $title, null, $parserOptions );
				}
				$description = $parserOutput->getText();
				$item = new FeedItem(
					$queryResult['title'] ?? $queryResult['_pageName'] ?? '',
					$description,
					$queryResult['url'] ?? $title->getCanonicalURL(),
					$queryResult[$dateFields[0]] ?? '',
					$queryResult['author'] ?? '',
					$queryResult['comments'] ?? ''
				);
				if ( isset( $queryResult['id'] ) && !empty( $queryResult['id'] ) ) {
					$item->setUniqueId( $queryResult['id'] );
				} else {
					$item->setUniqueId( $title->getCanonicalURL(), true );
				}
				$items[$queryResult[$dateFields[0]]] = $item;
			}
		}

		// Output feed content.
		$feed->outHeader();
		foreach ( $items as $item ) {
			$feed->outItem( $item );
		}
		$feed->outFooter();
	}
}

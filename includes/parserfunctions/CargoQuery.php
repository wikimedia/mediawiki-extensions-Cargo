<?php
/**
 * CargoQuery - class for the #cargo_query parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
use MediaWiki\MediaWikiServices;

class CargoQuery {

	/**
	 * Handles the #cargo_query parser function - calls a query on the
	 * Cargo data stored in the database.
	 *
	 * @param Parser $parser
	 * @return string|array Error message string, or an array holding output text and format flags
	 */
	public static function run( $parser ) {
		global $wgCargoIgnoreBacklinks;

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tablesStr = null;
		$fieldsStr = null;
		$whereStr = null;
		$joinOnStr = null;
		$groupByStr = null;
		$havingStr = null;
		$orderByStr = null;
		$limitStr = null;
		$offsetStr = null;
		$noHTML = false;
		$format = 'auto'; // default
		$displayParams = [];

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) == 1 ) {
				if ( $param == 'no html' ) {
					$noHTML = true;
				}
				continue;
			}
			if ( count( $parts ) > 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == 'tables' || $key == 'table' ) {
				$tablesStr = $value;
			} elseif ( $key == 'fields' ) {
				$fieldsStr = $value;
			} elseif ( $key == 'where' ) {
				$whereStr = $value;
			} elseif ( $key == 'join on' ) {
				$joinOnStr = $value;
			} elseif ( $key == 'group by' ) {
				$groupByStr = $value;
			} elseif ( $key == 'having' ) {
				$havingStr = $value;
			} elseif ( $key == 'order by' ) {
				$orderByStr = $value;
			} elseif ( $key == 'limit' ) {
				$limitStr = $value;
			} elseif ( $key == 'offset' ) {
				$offsetStr = $value;
			} elseif ( $key == 'format' ) {
				$format = $value;
			} else {
				// We'll assume it's going to the formatter.
				$displayParams[$key] = $value;
			}
		}
		// Special handling.
		if ( $format == 'dynamic table' && $orderByStr != null ) {
			$displayParams['order by'] = $orderByStr;
		}

		try {
			$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );
			// If this is a non-grouped query, make a 2nd query just
			// for _pageID (since the original query won't always
			// have a _pageID field) in order to populate the
			// cargo_backlinks table.
			// Also remove the limit from this 2nd query so that it
			// can include all results.
			// Fetch results title only if "cargo_backlinks" table exists
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbr = $lb->getConnection( DB_REPLICA );
			if ( !$wgCargoIgnoreBacklinks && !$sqlQuery->isAggregating() && $dbr->tableExists( 'cargo_backlinks' ) ) {
				$newFieldsStr = $fieldsStr;
				// $fieldsToCollectForPageIDs allows us to
				// collect all those special fields' values in
				// the results
				$fieldsToCollectForPageIDs = [];
				foreach ( $sqlQuery->mAliasedTableNames as $alias => $table ) {
					// Ignore helper tables.
					if ( strpos( $table, '__' ) !== false ) {
						continue;
					}
					$fieldFullName = "cargo_backlink_page_id_$alias";
					$fieldsToCollectForPageIDs[] = $fieldFullName;
					$newFieldsStr = "$alias._pageID=$fieldFullName, " . $newFieldsStr;
				}
				$sqlQueryJustForResultsTitle = CargoSQLQuery::newFromValues(
					$tablesStr, $newFieldsStr, $whereStr, $joinOnStr,
					$groupByStr, $havingStr, $orderByStr, '', $offsetStr
				);
				$queryResultsJustForResultsTitle = $sqlQueryJustForResultsTitle->run();
			}
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		$pageIDsForBacklinks = [];
		if ( isset( $queryResultsJustForResultsTitle ) ) {
			// Collect all special _pageID entries.
			foreach ( $fieldsToCollectForPageIDs as $fieldToCollectForPageIds ) {
				$pageIDsForBacklinks = array_merge( $pageIDsForBacklinks, array_column( $queryResultsJustForResultsTitle, $fieldToCollectForPageIds ) );
			}
			$pageIDsForBacklinks = array_unique( $pageIDsForBacklinks );
		}

		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$queryDisplayer->mFormat = $format;
		$queryDisplayer->mDisplayParams = $displayParams;
		$queryDisplayer->mParser = $parser;
		$formatter = $queryDisplayer->getFormatter( $parser->getOutput(), $parser );

		// Let the format run the query itself, if it wants to.
		if ( $formatter->isDeferred() ) {
			// @TODO - fix this inefficiency. Right now a
			// CargoSQLQuery object is constructed three times for
			// deferred formats: the first two times here and the
			// 3rd by Special:CargoExport. It's the first
			// construction that involves a bunch of text
			// processing, and is unneeded.
			// However, this first CargoSQLQuery is passed to
			// the CargoQueryDisplayer, which in turn uses it
			// to figure out the formatting class, so that we
			// know whether it is a deferred class or not. The
			// class is based in part on the set of fields in the
			// query, so in theory (though not in practice),
			// whether or not it's deferred could depend on the
			// fields in the query, making the first 'Query
			// necessary. There has to be some better way, though.
			$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );
			$text = $formatter->queryAndDisplay( [ $sqlQuery ], $displayParams );
			self::setBackLinks( $parser, $pageIDsForBacklinks );
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		}

		// If the query limit was set to 0, no need to run the query -
		// all we need to do is show the "more results" link, then exit.
		if ( $sqlQuery->mQueryLimit == 0 ) {
			$text = $queryDisplayer->viewMoreResultsLink( true );
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		}

		try {
			$queryResults = $sqlQuery->run();
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		// Finally, do the display.
		$text = $queryDisplayer->displayQueryResults( $formatter, $queryResults );
		// If there are no results, then - given that we already know
		// that the limit was not set to 0 - we just need to display an
		// automatic message, so there's no need for special parsing.
		if ( count( $queryResults ) == 0 ) {
			return $text;
		}
		// No errors? Let's save our reverse links.
		self::setBackLinks( $parser, $pageIDsForBacklinks );

		// The 'template' format gets special parsing, because
		// it can be used to display a larger component, like a table,
		// which means that everything needs to be parsed together
		// instead of one instance at a time. Also, the template will
		// contain wikitext, not HTML.
		$displayHTML = ( !$noHTML && $format != 'template' );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink( $displayHTML );
		}

		if ( $displayHTML ) {
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		} else {
			return [ $text, 'noparse' => false ];
		}
	}

	/**
	 * Store the list of page IDs referenced by this query in the parser output.
	 * @param Parser $parser
	 * @param int[] $backlinkPageIds List of referenced page IDs to store.
	 */
	private static function setBacklinks( Parser $parser, array $backlinkPageIds ): void {
		$parserOutput = $parser->getOutput();

		// MW 1.38 compatibility
		if ( method_exists( $parserOutput, 'appendExtensionData' ) ) {
			foreach ( $backlinkPageIds as $pageId ) {
				$parserOutput->appendExtensionData( CargoBackLinks::BACKLINKS_DATA_KEY, $pageId );
			}
		} else {
			$backlinks = (array)$parserOutput->getExtensionData( CargoBackLinks::BACKLINKS_DATA_KEY );
			foreach ( $backlinkPageIds as $pageId ) {
				$backlinks[$pageId] = true;
			}

			$parserOutput->setExtensionData(
				CargoBackLinks::BACKLINKS_DATA_KEY,
				$backlinks
			);
		}
	}

}

<?php
/**
 * CargoQuery - class for the #cargo_query parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQuery {

	/**
	 * Handles the #cargo_query parser function - calls a query on the
	 * Cargo data stored in the database.
	 *
	 * @param Parser $parser
	 * @return string
	 */
	public static function run( &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tablesStr = null;
		$fieldsStr = null;
		$whereStr = null;
		$joinOnStr = null;
		$groupByStr = null;
		$orderByStr = null;
		$limitStr = null;
		$format = 'auto'; // default
		$displayParams = array();

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
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
			} elseif ( $key == 'order by' ) {
				$orderByStr = $value;
			} elseif ( $key == 'limit' ) {
				$limitStr = $value;
			} elseif ( $key == 'format' ) {
				$format = $value;
			} else {
				// We'll assume it's going to the formatter.
				$displayParams[$key] = $value;
			}
		}

		$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $orderByStr, $limitStr );
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$queryDisplayer->mFormat = $format;
		$queryDisplayer->mDisplayParams = $displayParams;
		$queryDisplayer->mParser = $parser;
		$formatter = $queryDisplayer->getFormatter( $parser->getOutput() );

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
			$sqlQuery = CargoSQLQuery::newFromValues2( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
					$groupByStr, $orderByStr, $limitStr );
			$text = $formatter->queryAndDisplay( array( $sqlQuery ), $displayParams );
			return $parser->insertStripItem( $text, $parser->mStripState );
		}

		try {
			$queryResults = $sqlQuery->run();
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		// Finally, do the display.
		$text = $queryDisplayer->displayQueryResults( $formatter, $queryResults );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink();
		}

		return $parser->insertStripItem( $text, $parser->mStripState );
	}

}

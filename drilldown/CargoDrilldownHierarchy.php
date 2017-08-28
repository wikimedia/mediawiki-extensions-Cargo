<?php
/**
 * A class for counting the occurences of hierarchy field values in the Cargo tables for displaying in drilldown.
 *
 * @author Feroz Ahmad
 * @ingroup Cargo
 */

class CargoDrilldownHierarchy extends CargoHierarchyTree {
	public $mWithinTreeMatchCount = 0;
	public $mExactRootMatchCount = 0;

	static function computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters ) {
		$cdb = CargoUtils::getDB();
		list( $tableNames, $conds, $joinConds ) = $f->getQueryParts( $fullTextSearchTerm, $appliedFilters );
		if ( $f->fieldDescription->mIsList ) {
			$fieldTableName = $f->tableName . '__' . $f->name;
			$countColumnName = $cdb->tableName( $fieldTableName ) . "._rowID";
			if ( !in_array( $fieldTableName, $tableNames ) ) {
				$tableNames[] = $fieldTableName;
			}
			$fieldColumnName = '_value';
			if ( !array_key_exists( $fieldTableName, $joinConds ) ) {
				$joinConds[$fieldTableName] = CargoUtils::joinOfMainAndFieldTable( $cdb, $f->tableName, $fieldTableName );
			}
		} else {
			$fieldColumnName = $f->name;
			$fieldTableName = $f->tableName;
			$countColumnName = $cdb->tableName( $fieldTableName ) . "._ID";
		}

		$countClause = "COUNT(DISTINCT($countColumnName)) AS total";

		$hierarchyTableName = $f->tableName . '__' . $f->name . '__hierarchy';
		$cargoHierarchyTableName = $cdb->tableName( $hierarchyTableName );
		if ( !in_array( $hierarchyTableName, $tableNames ) ) {
			$tableNames[] = $hierarchyTableName;
		}

		if ( !array_key_exists( $hierarchyTableName, $joinConds ) ) {
			$joinConds[$hierarchyTableName] = CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
				$fieldTableName, $fieldColumnName, $hierarchyTableName );
		}
		$withinTreeHierarchyConds = array();
		$exactRootHierarchyConds = array();
		$withinTreeHierarchyConds[] = "$cargoHierarchyTableName._left >= $node->mLeft";
		$withinTreeHierarchyConds[] = "$cargoHierarchyTableName._right <= $node->mRight";
		$exactRootHierarchyConds[] = "$cargoHierarchyTableName._left = $node->mLeft";
		// within hierarchy tree value count
		$res = $cdb->select( $tableNames, array( $countClause ), array_merge( $conds, $withinTreeHierarchyConds ),
			null, null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$node->mWithinTreeMatchCount = $row['total'];
		$cdb->freeResult( $res );
		// exact hierarchy node value count
		$res = $cdb->select( $tableNames, array( $countClause ), array_merge( $conds, $exactRootHierarchyConds ),
			null, null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$node->mExactRootMatchCount = $row['total'];;
		$cdb->freeResult( $res );
	}

	/**
	 * Fill up (set the value) the count data members of nodes of the tree represented by node used
	 * for calling this function. Also return an array of distinct values of the field and their counts.
	 */
	static function computeNodeCountForTreeByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters ) {
		$filter_values = array();
		$stack = new SplStack();
		// preorder traversal of the tree
		$stack->push( $node );
		while ( !$stack->isEmpty() ) {
			$node = $stack->pop();
			CargoDrilldownHierarchy::computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters );
			if ( $node->mLeft !== 1 ) {
				// check if its not __pseudo_root__ node, then only add count
				$filter_values[$node->mRootValue] = $node->mWithinTreeMatchCount;
			}
			if ( count( $node->mChildren ) > 0 ) {
				if ( $node->mLeft !== 1 ) {
					$filter_values[$node->mRootValue . " only"] = $node->mWithinTreeMatchCount;
				}
				for ( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
					$stack->push( $node->mChildren[$i] );
				}
			}
		}
		return $filter_values;
	}

	/*
	* Finds maximum permissible depth for listing values in Drilldown filter line such that total values appearing on the Filter line
	* is less than or equal to $wgCargoMaxVisibleHierarchyDrilldownValues
	*/
	static function findMaxDrilldownDepth( $node ) {
		global $wgCargoMaxVisibleHierarchyDrilldownValues;
		if ( !isset( $wgCargoMaxVisibleHierarchyDrilldownValues ) || !is_int( $wgCargoMaxVisibleHierarchyDrilldownValues ) || $wgCargoMaxVisibleHierarchyDrilldownValues < 0 ) {
			return PHP_INT_MAX;
		}
		$maxDepth = 0;
		$nodeCount = 0;
		$queue = new SplQueue();
		$queue->enqueue( $node );
		$queue->enqueue( null );
		while ( !$queue->isEmpty() ) {
			$node = $queue->dequeue();
			if ( $node === null ) {
				if ( !$queue->isEmpty() ) {
					$maxDepth++;
					$queue->enqueue( null );
				}
			} else {
				if ( count( $node->mChildren ) > 0 && $node->mExactRootMatchCount > 0 ) {
					// we will go one level deeper and print "nodevalue_only (x)" in filter line - so count it
					$nodeCount++;
				}
				foreach ( $node->mChildren as $child ) {
					if ( $child->mWithinTreeMatchCount > 0 ) {
						if ( $nodeCount >= $wgCargoMaxVisibleHierarchyDrilldownValues ) {
							break(2);
						}
						$queue->enqueue( $child );
						$nodeCount++;
					}
				}
			}
		}
		return max(1, $maxDepth);
	}
}
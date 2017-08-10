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
			$countArg = "_rowID";
			$fieldTableName = $f->tableName . '__' . $f->name;
			$tableNames[] = $fieldTableName;
			$fieldName = '_value';
			$joinConds[$fieldTableName] = CargoUtils::joinOfMainAndFieldTable( $cdb, $f->tableName, $fieldTableName );
		} else {
			$countArg = "_ID";
			$fieldName = $f->name;
			$fieldTableName = $f->tableName;
		}

		$countClause = "COUNT(DISTINCT($countArg)) AS total";

		$hierarchyTableName = $f->tableName . '__' . $f->name . '__hierarchy';
		$tableNames[] = $hierarchyTableName;

		$joinConds[$hierarchyTableName] = CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
			$fieldTableName, $fieldName, $hierarchyTableName );

		$withinTreeHierarchyConds = array();
		$exactRootHierarchyConds = array();
		$withinTreeHierarchyConds[] = "_left >= $node->mLeft";
		$withinTreeHierarchyConds[] = "_right <= $node->mRight";
		$exactRootHierarchyConds[] = "_left = $node->mLeft";

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
			if ( $node->mLeft !== 1 ) {
				// check if its not __pseudo_root__ node, then only add count
				CargoDrilldownHierarchy::computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters );
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
}
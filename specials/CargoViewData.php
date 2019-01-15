<?php
/**
 * Shows the results of a Cargo query.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoViewData extends SpecialPage {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'ViewData', 'runcargoqueries' );
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$out->addModules( 'ext.cargo.main' );

		$req = $this->getRequest();
		$tablesStr = $req->getVal( 'tables' );
		if ( $tablesStr == '' ) {
			$html = $this->displayInputForm();
			$out->addHTML( $html );
			return;
		}

		try {
			$rep = new ViewDataPage();
		} catch ( MWException $e ) {
			$out->addHTML( CargoUtils::formatError( $e->getMessage() ) );
			return;
		}
		return $rep->execute( $query );
	}

	protected function getGroupName() {
		return 'cargo';
	}

	static function displayInputRow( $labelText, $fieldName, $size ) {
		$label = Html::element( 'label', array( 'for' => $fieldName ), $labelText );
		$row = Html::rawElement( 'td', array( 'class' => 'mw-label' ), $label );
		$input = Html::input( $fieldName, '', 'text', array( 'size' => $size, 'id' => $fieldName ) );
		$row .= Html::rawElement( 'td', array( 'class' => 'mw-input' ), $input );
		return Html::rawElement( 'tr', array( 'class' => 'mw-htmlform-field-HTMLTextField' ), $row );
	}

	function displayInputForm() {
		// Add the name of this special page as a hidden input, in
		// case the wiki doesn't use nice URLs.
		$hiddenTitleInput = Html::hidden( 'title', $this->getPageTitle()->getFullText() );
		$text = <<<END
<form>
$hiddenTitleInput
<table class="cargoViewDataTable">
<tbody>

END;
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-tables' )->text(), 'tables', 100 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-fields' )->text(), 'fields', 100 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-where' )->text(), 'where', 100 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-joinon' )->text(), 'join_on', 100 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-groupby' )->text(), 'group_by', 50 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-having' )->text(), 'having', 50 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-orderby' )->text(), 'order_by', 50 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-limit' )->text(), 'limit', 3 );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-offset' )->text(), 'offset', 3 );
		$formatLabel = wfMessage( 'cargo-viewdata-format' )->text();
		$formatOptionDefault = wfMessage( 'cargo-viewdata-defaultformat' )->text();
		$text .= <<<END
<tr class="mw-htmlform-field-HTMLTextField">
<td class="mw-label">
$formatLabel
</td>
<td class="mw-input">
<select name="format">
<option value="">($formatOptionDefault)</option>

END;
		$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
		foreach ( $formatClasses as $formatName => $formatClass ) {
			$text .= Html::element( 'option', null, $formatName );
		}

		$submitLabel = wfMessage( 'htmlform-submit' )->text();
		$text .= <<<END

</select>
</td>
</tr>
</tbody>
</table>
<br>
<input type="submit" value="$submitLabel" class="mw-ui-button mw-ui-progressive" />
</form>

END;
		return $text;
	}
}

class ViewDataPage extends QueryPage {
	public function __construct( $name = 'ViewData' ) {
		parent::__construct( $name );

		$req = $this->getRequest();
		$tablesStr = $req->getVal( 'tables' );
		$fieldsStr = $req->getVal( 'fields' );
		$whereStr = $req->getVal( 'where' );
		$joinOnStr = $req->getVal( 'join_on' );
		$groupByStr = $req->getVal( 'group_by' );
		$havingStr = $req->getVal( 'having' );
		$orderByStr = $req->getVal( 'order_by' );
		$limitStr = $req->getVal( 'limit' );
		$offsetStr = $req->getVal( 'offset' );

		$this->sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );

		$formatStr = $req->getVal( 'format' );
		$this->format = $formatStr;

		// This is needed for both the results display and the
		// navigation links.
		$this->displayParams = array();
		$queryStringValues = $this->getRequest()->getValues();
		foreach ( $queryStringValues as $key => $value ) {
			// For some reason, getValues() turns all spaces
			// into underlines.
			$paramName = str_replace( '_', ' ', $key );
			if ( !in_array( $paramName,
					array( 'title', 'tables', 'fields', 'join on', 'order by', 'group by', 'having', 'format',
					'offset' ) ) ) {
				$this->displayParams[$paramName] = $value;
			}
			// Special handling.
			if ( $this->format == 'dynamic table' && $paramName == 'order by' ) {
				$this->displayParams['order by'] = $value;
			}

		}
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	// @TODO - declare a getPageHeader() function, to show some
	// information about the query?

	function getRecacheDB() {
		return CargoUtils::getDB();
	}

	function getQueryInfo() {
		$selectOptions = array();
		if ( $this->sqlQuery->mGroupByStr != '' ) {
			$selectOptions['GROUP BY'] = $this->sqlQuery->mGroupByStr;
		}
		if ( $this->sqlQuery->mHavingStr != '' ) {
			$selectOptions['HAVING'] = $this->sqlQuery->mHavingStr;
		}

		// "order by" is handled elsewhere, in getOrderFields().

		// Field aliases need to have quotes placed around them
		// before running the query - though, starting in
		// MW 1.27 (specifically, with
		// https://gerrit.wikimedia.org/r/#/c/286489/),
		// the quotes get added automatically.
		$cdb = CargoUtils::getDB();
		$aliasedFieldNames = array();
		foreach ( $this->sqlQuery->mAliasedFieldNames as $alias => $fieldName ) {
			foreach ( $this->sqlQuery->mAliasedFieldNames as $alias => $fieldName ) {
				if ( version_compare( $GLOBALS['wgVersion'], '1.27', '<' ) ) {
					$alias = '"' . $alias . '"';
				}
				// If it's really a field name, add quotes around it.
				if ( strpos( $fieldName, '(' ) === false && strpos( $fieldName, '.' ) === false &&
					!$cdb->isQuotedIdentifier( $fieldName ) && !CargoUtils::isSQLStringLiteral( $fieldName ) ) {
					$fieldName = $cdb->addIdentifierQuotes( $fieldName );
				}
				$aliasedFieldNames[$alias] = $fieldName;
			}
		}

		$queryInfo = array(
			'tables' => $this->sqlQuery->mAliasedTableNames,
			'fields' => $aliasedFieldNames,
			'options' => $selectOptions
		);
		if ( $this->sqlQuery->mWhereStr != '' ) {
			$queryInfo['conds'] = $this->sqlQuery->mWhereStr;
		}
		if ( !empty( $this->sqlQuery->mJoinConds ) ) {
			$queryInfo['join_conds'] = $this->sqlQuery->mJoinConds;
		}
		return $queryInfo;
	}

	function linkParameters() {
		$possibleParams = array(
			'tables', 'fields', 'where', 'join_on', 'order_by', 'group_by', 'having', 'format'
		);
		$linkParams = array();
		$req = $this->getRequest();
		foreach ( $possibleParams as $possibleParam ) {
			if ( $req->getCheck( $possibleParam ) ) {
				$linkParams[$possibleParam] = $req->getVal( $possibleParam );
			}
		}

		foreach ( $this->displayParams as $key => $value ) {
			$linkParams[$key] = $value;
		}

		return $linkParams;
	}

	function getOrderFields() {
		if ( $this->sqlQuery->mOrderByStr != '' ) {
			return array( $this->sqlQuery->mOrderByStr );
		}
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		// This function needs to be declared, but it is not called.
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param DatabaseBase $dbr Database (read) connection to use
	 * @param int $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 */
	function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		$valuesTable = array();
		for ( $i = 0; $i < $num && $row = $res->fetchObject(); $i++ ) {
			$valuesTable[] = get_object_vars( $row );
		}
		$queryDisplayer = new CargoQueryDisplayer();
		$queryDisplayer->mFieldDescriptions = $this->sqlQuery->mFieldDescriptions;
		$queryDisplayer->mFormat = $this->format;
		$formatter = $queryDisplayer->getFormatter( $out );

		if ( $formatter->isDeferred() ) {
			$displayParams = array();
			$text = $formatter->queryAndDisplay( array( $this->sqlQuery ), $displayParams );
			$out->addHTML( $text );
			return;
		}

		$this->displayParams['offset'] = $offset;
		$queryDisplayer->mDisplayParams = $this->displayParams;
		$html = $queryDisplayer->displayQueryResults( $formatter, $valuesTable );
		$out->addHTML( $html );
	}

}

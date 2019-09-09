<?php
/**
 * Shows the results of a Cargo query.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQueryInterface extends SpecialPage {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'CargoQuery', 'runcargoqueries' );
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->addModules( 'ext.cargo.main' );
		$out->addModules( 'ext.cargo.cargoquery' );

		if ( $req->getCheck( 'tables' ) ) {
			try {
				$rep = new CargoQueryPage();
			} catch ( MWException $e ) {
				$out->addHTML( CargoUtils::formatError( $e->getMessage() ) );
				return;
			}
			$rep->execute( $query );
		}

		$formHTML = $this->displayInputForm();

		if ( $req->getCheck( 'tables' ) ) {
			$modifyQueryMsg = $this->msg( 'cargo-viewdata-modifyquery' );
			$html = <<<END
<div style="max-width: 70em;">
<span style="width: 100%;" class="oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-indicatorElement oo-ui-labelElement oo-ui-buttonWidget">
<a href="#" id="cargo-modifyQuery-toggle" class="oo-ui-buttonElement-button" role="button" tabindex="0" aria-disabled="false" rel="nofollow">
$modifyQueryMsg
<span class="oo-ui-indicatorElement-indicator oo-ui-indicator-down"></span>
</a>
</span>
<div id="cargo-modifyQuery-form">
$formHTML
</div>
</div>

END;
		} else {
			$html = $formHTML;
		}

		$out->addHTML( $html );
	}

	protected function getGroupName() {
		return 'cargo';
	}

	/**
	 * This method is used for generating the input fields
	 * @param string $labelText
	 * @param string $fieldName
	 * @param int $size
	 * @return string
	 */
	static function displayInputRow( $labelText, $fieldName, $size, $tooltip ) {
		global $wgRequest;

		$label = Html::element( 'label', array( 'for' => $fieldName ), $labelText );
		$label .= '&nbsp;' . Html::element( 'button',
			array(
				'class' => 'cargoQueryTooltipIcon',
				'disabled' => true,
				'for' => $fieldName ,
				'data-balloon-length' => 'large',
				'data-balloon' => $tooltip
			), '' ) . '&nbsp;';
		$row = "\n\t" . Html::rawElement( 'td', array( 'class' => 'mw-label' ), $label );
		$input = Html::input( $fieldName, $wgRequest->getVal( $fieldName ), 'text',
			array(
				'class' => 'form-control cargo-query-input',
				'multiple' => 'true',
				'size' => $size . ' !important',
				'id' => $fieldName
			) );
		$row .= "\n\t" . Html::rawElement( 'td', array( 'class' => 'mw-input' ), $input );
		return Html::rawElement( 'tr', array( 'class' => 'mw-htmlform-field-HTMLTextField' ), $row ) . "\n";
	}

	static function displayTextArea( $labelText, $fieldName, $size, $tooltip ) {
		global $wgRequest;

		$label = Html::element( 'label', array( 'for' => $fieldName ), $labelText );
		$label .= '&nbsp;' . Html::element( 'button',
			array(
				'class' => 'cargoQueryTooltipIcon',
				'disabled' => true,
				'for' => $fieldName ,
				'data-balloon-length' => 'large',
				'data-balloon' => $tooltip
			), '' ) . '&nbsp;';
		$row = "\n\t" . Html::rawElement( 'td', array( 'class' => 'mw-label' ), $label );
		$input = Html::textarea( $fieldName, $wgRequest->getVal( $fieldName ),
			array(
				'class' => 'form-control cargo-query-textarea',
				'multiple' => 'true',
				'size' => $size . ' !important',
				'id' => $fieldName
			) );
		$row .= "\n\t" . Html::rawElement( 'td', array( 'class' => 'mw-input' ), $input ) . "\n";
		return Html::rawElement( 'tr', array( 'class' => 'mw-htmlform-field-HTMLTextField' ), $row ) . "\n";
	}

	static function displayOrderByInput( $rowNum, $orderByValue, $orderByDirection ) {
		$text = "\n" . '<tr class="mw-htmlform-field-HTMLTextField orderByRow" data-order-by-num=' . $rowNum . '>';
		if ( $rowNum == 0 ) {
			$text .= '<td class="mw-label">' .
				'<label for="order_by">' . wfMessage( 'cargo-viewdata-orderby' )->parse() .
				'&nbsp;&nbsp;<button class="cargoQueryTooltipIcon" type="button" for="order_by" data-balloon-length="large" data-balloon="' .
				wfMessage( 'cargo-viewdata-orderbytooltip' )->parse() .
				'"</button></td>';
		} else {
			$text .= '<td></td>';
		}
		$ascAttribs = array( 'value' => 'ASC' );
		if ( $orderByDirection == 'ASC' ) {
			$ascAttribs['selected'] = true;
		}
		$ascOption = Html::element( 'option', $ascAttribs, 'ASC' );
		$descAttribs = array( 'value' => 'DESC' );
		if ( $orderByDirection == 'DESC' ) {
			$descAttribs['selected'] = true;
		}
		$descOption = Html::element( 'option', $descAttribs, 'DESC' );
		$directionSelect = Html::rawElement( 'select', array( 'name' => 'order_by_options[' . $rowNum . ']' ), $ascOption . $descOption );
		$text .= '<td class="mw-input"><input class="form-control order_by" size="50 !important" name="order_by[' . $rowNum . ']" value="' . $orderByValue . '" />' .
			"&nbsp;&nbsp;$directionSelect&nbsp;&nbsp;<button class=\"";
		$text .= ( $rowNum == 0 ) ? 'addButton' : 'deleteButton';
		$text .= '" type="button"></button></td></tr>';

		return $text;
	}

	function displayInputForm() {
		global $wgCargoDefaultQueryLimit, $wgRequest;

		// Add the name of this special page as a hidden input, in
		// case the wiki doesn't use nice URLs.
		$hiddenTitleInput = Html::hidden( 'title', $this->getPageTitle()->getFullText() );

		$text = <<<END
<form id="queryform">
$hiddenTitleInput
<table class="cargoQueryTable" id="cargoQueryTable" >
<tbody>
END;

		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-tables' )->parse(), 'tables', 100,
			wfMessage( 'cargo-viewdata-tablestooltip', "Cities=city, Countries" )->parse() );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-fields' )->parse(), 'fields', 100,
			wfMessage( 'cargo-viewdata-fieldstooltip', "_pageName", "Cities.Population=P, Countries.Capital" )->parse() );
		$text .= self::displayTextArea( wfMessage( 'cargo-viewdata-where' )->parse(), 'where', 100,
			wfMessage( 'cargo-viewdata-wheretooltip', "Country.Continent = 'North America' AND City.Population > 100000" )->parse() );
		$text .= self::displayTextArea( wfMessage( 'cargo-viewdata-joinon' )->parse(), 'join_on', 100,
			wfMessage( 'cargo-viewdata-joinontooltip', "Cities.Country=Countries._pageName" )->parse() );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-groupby' )->parse(), 'group_by', 100,
			wfMessage( 'cargo-viewdata-groupbytooltip', "Countries.Continent" )->parse() );
		$text .= self::displayTextArea( wfMessage( 'cargo-viewdata-having' )->parse(), 'having', 100,
			wfMessage( 'cargo-viewdata-havingtooltip', "COUNT(*) > 10" )->parse() );
		$orderByValues = $wgRequest->getArray( 'order_by' );
		if ( $orderByValues != null ) {
			$orderByDirections = $wgRequest->getArray( 'order_by_options' );
			$rowNum = 0;
			foreach ( $orderByValues as $i => $curOrderBy ) {
				$text .= self::displayOrderByInput( $rowNum++, $curOrderBy, $orderByDirections[$i] );
			}
		} else {
			$text .= self::displayOrderByInput( 0, null, null );
		}
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-limit' )->parse(), 'limit', 3,
			wfMessage( 'cargo-viewdata-limittooltip', $wgCargoDefaultQueryLimit )->parse() );
		$text .= self::displayInputRow( wfMessage( 'cargo-viewdata-offset' )->parse(), 'offset', 3,
			wfMessage( 'cargo-viewdata-offsettooltip', "0" )->parse() );
		$formatLabel = '<label for="format">' . wfMessage( 'cargo-viewdata-format' )->parse() .
			'&nbsp;&nbsp;<button class="cargoQueryTooltipIcon" type="button" for="format" data-balloon-length="large" data-balloon="' .
			wfMessage( 'cargo-viewdata-formattooltip' )->parse() . '"</button>&nbsp;';
		$formatOptionDefault = wfMessage( 'cargo-viewdata-defaultformat' )->parse();
		$text .= <<<END
<tr class="mw-htmlform-field-HTMLTextField">
<td class="mw-label">
$formatLabel
</td>
<td class="mw-input">
<select name="format" id="format">
<option value="">($formatOptionDefault)</option>

END;
		$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
		foreach ( $formatClasses as $formatName => $formatClass ) {
			$optionAttrs = array();
			if ( $formatName == $wgRequest->getVal( 'format' ) ) {
				$optionAttrs['selected'] = true;
			}
			$text .= Html::element( 'option', $optionAttrs, $formatName );
		}

		$submitLabel = wfMessage( 'htmlform-submit' )->parse();
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

class CargoQueryPage extends QueryPage {
	public function __construct( $name = 'CargoQuery' ) {
		parent::__construct( $name );

		$req = $this->getRequest();
		$tablesStr = trim( $req->getVal( 'tables' ) );
		$fieldsStr = trim( $req->getVal( 'fields' ) );
		$whereStr = trim( $req->getVal( 'where' ) );
		$joinOnStr = trim( $req->getVal( 'join_on' ) );
		$groupByStr = trim( $req->getVal( 'group_by' ) );
		if ( substr( $groupByStr, -1, 1 ) == ',' ) {
			$groupByStr = substr( $groupByStr, 0, -1 ); // Remove last comma for group by
		}
		$havingStr = trim( $req->getVal( 'having' ) );

		$orderByStr = "";
		$orderByValues = $req->getArray( 'order_by' );
		$orderByOptions = $req->getArray( 'order_by_options' );
		foreach ( $orderByValues as $i => $curOrderBy ) {
			if ( $curOrderBy != '' ) {
				$orderByStr .= $curOrderBy . ' ' . $orderByOptions[$i] . ',';
			}
		}
		if ( substr( $orderByStr, -1, 1 ) == ',' ) {
			$orderByStr = substr( $orderByStr, 0, -1 ); // Remove last comma for order by
		}
		$limitStr = trim( $req->getVal( 'limit' ) );
		$offsetStr = trim( $req->getVal( 'offset' ) );

		$this->sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );

		$formatStr = trim( $req->getVal( 'format' ) );
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
		}

		// 'dynamic table' makes its own use of 'order by'.
		if ( $this->format == 'dynamic table' ) {
			$this->displayParams['order by'] = $orderByStr;
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

	/**
	 * Returns an associative array that will be encoded and added to the
	 * paging links
	 * @return array
	 */
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
		return $this->sqlQuery->mOrderBy;
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

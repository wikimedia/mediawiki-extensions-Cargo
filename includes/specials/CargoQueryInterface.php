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
		$this->checkPermissions();

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
	function displayInputRow( $labelText, $fieldName, $size, $tooltip ) {
		$req = $this->getRequest();

		$label = Html::element( 'label', [ 'for' => $fieldName ], $labelText );
		$label .= '&nbsp;' . Html::element( 'button',
			[
				'class' => 'cargoQueryTooltipIcon',
				'disabled' => true,
				'for' => $fieldName ,
				'data-balloon-length' => 'large',
				'data-balloon' => $tooltip
			], '' ) . '&nbsp;';
		$row = "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-label' ], $label );
		$input = Html::input( $fieldName, $req->getVal( $fieldName ), 'text',
			[
				'class' => 'form-control cargo-query-input',
				'multiple' => 'true',
				'size' => $size . ' !important',
				'id' => $fieldName
			] );
		$row .= "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-input' ], $input );
		return Html::rawElement( 'tr', [ 'class' => 'mw-htmlform-field-HTMLTextField' ], $row ) . "\n";
	}

	function displayTextArea( $labelText, $fieldName, $size, $tooltip ) {
		$req = $this->getRequest();

		$label = Html::element( 'label', [ 'for' => $fieldName ], $labelText );
		$label .= '&nbsp;' . Html::element( 'button',
			[
				'class' => 'cargoQueryTooltipIcon',
				'disabled' => true,
				'for' => $fieldName ,
				'data-balloon-length' => 'large',
				'data-balloon' => $tooltip
			], '' ) . '&nbsp;';
		$row = "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-label' ], $label );
		$input = Html::textarea( $fieldName, $req->getVal( $fieldName ),
			[
				'class' => 'form-control cargo-query-textarea',
				'multiple' => 'true',
				'size' => $size . ' !important',
				'id' => $fieldName
			] );
		$row .= "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-input' ], $input ) . "\n";
		return Html::rawElement( 'tr', [ 'class' => 'mw-htmlform-field-HTMLTextField' ], $row ) . "\n";
	}

	function displayOrderByInput( $rowNum, $orderByValue, $orderByDirection ) {
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
		$ascAttribs = [ 'value' => 'ASC' ];
		if ( $orderByDirection == 'ASC' ) {
			$ascAttribs['selected'] = true;
		}
		$ascOption = Html::element( 'option', $ascAttribs, 'ASC' );
		$descAttribs = [ 'value' => 'DESC' ];
		if ( $orderByDirection == 'DESC' ) {
			$descAttribs['selected'] = true;
		}
		$descOption = Html::element( 'option', $descAttribs, 'DESC' );
		$directionSelect = Html::rawElement( 'select', [ 'name' => 'order_by_options[' . $rowNum . ']' ], $ascOption . $descOption );
		$text .= '<td class="mw-input"><input class="form-control order_by" size="50 !important" name="order_by[' . $rowNum . ']" value="' . $orderByValue . '" />' .
			"&nbsp;&nbsp;$directionSelect&nbsp;&nbsp;<button class=\"";
		$text .= ( $rowNum == 0 ) ? 'addButton' : 'deleteButton';
		$text .= '" type="button"></button></td></tr>';

		return $text;
	}

	function displayInputForm() {
		global $wgCargoDefaultQueryLimit;

		$req = $this->getRequest();
		// Add the name of this special page as a hidden input, in
		// case the wiki doesn't use nice URLs.
		$hiddenTitleInput = Html::hidden( 'title', $this->getPageTitle()->getFullText() );

		$text = <<<END
<form id="queryform">
$hiddenTitleInput
<table class="cargoQueryTable" id="cargoQueryTable" >
<tbody>
END;

		$text .= $this->displayInputRow( wfMessage( 'cargo-viewdata-tables' )->parse(), 'tables', 100,
			wfMessage( 'cargo-viewdata-tablestooltip', "Cities=city, Countries" )->parse() );
		$text .= $this->displayInputRow( wfMessage( 'cargo-viewdata-fields' )->parse(), 'fields', 100,
			wfMessage( 'cargo-viewdata-fieldstooltip', "_pageName", "Cities.Population=P, Countries.Capital" )->parse() );
		$text .= $this->displayTextArea( wfMessage( 'cargo-viewdata-where' )->parse(), 'where', 100,
			wfMessage( 'cargo-viewdata-wheretooltip', "Country.Continent = 'North America' AND City.Population > 100000" )->parse() );
		$text .= $this->displayTextArea( wfMessage( 'cargo-viewdata-joinon' )->parse(), 'join_on', 100,
			wfMessage( 'cargo-viewdata-joinontooltip', "Cities.Country=Countries._pageName" )->parse() );
		$text .= $this->displayInputRow( wfMessage( 'cargo-viewdata-groupby' )->parse(), 'group_by', 100,
			wfMessage( 'cargo-viewdata-groupbytooltip', "Countries.Continent" )->parse() );
		$text .= $this->displayTextArea( wfMessage( 'cargo-viewdata-having' )->parse(), 'having', 100,
			wfMessage( 'cargo-viewdata-havingtooltip', "COUNT(*) > 10" )->parse() );
		$orderByValues = $req->getArray( 'order_by' );
		if ( $orderByValues != null ) {
			$orderByDirections = $req->getArray( 'order_by_options' );
			$rowNum = 0;
			foreach ( $orderByValues as $i => $curOrderBy ) {
				$text .= $this->displayOrderByInput( $rowNum++, $curOrderBy, $orderByDirections[$i] );
			}
		} else {
			$text .= $this->displayOrderByInput( 0, null, null );
		}
		$text .= $this->displayInputRow( wfMessage( 'cargo-viewdata-limit' )->parse(), 'limit', 3,
			wfMessage( 'cargo-viewdata-limittooltip', $wgCargoDefaultQueryLimit )->parse() );
		$text .= $this->displayInputRow( wfMessage( 'cargo-viewdata-offset' )->parse(), 'offset', 3,
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
			$optionAttrs = [];
			if ( $formatName == $req->getVal( 'format' ) ) {
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

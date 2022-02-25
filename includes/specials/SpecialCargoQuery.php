<?php
/**
 * Shows the results of a Cargo query.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class SpecialCargoQuery extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CargoQuery', 'runcargoqueries' );
	}

	public function execute( $query ) {
		$this->checkPermissions();

		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();
		$out->enableOOUI();

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
			$html = $this->displayBottomPane( $this->msg( 'cargo-viewdata-modifyquery' ), $formHTML );
			$wikitext = $this->getWikitextForQuery();
			$html .= $this->displayBottomPane( $this->msg( 'cargo-viewdata-viewwikitext' ), $wikitext );
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
	public function displayInputRow( $labelText, $fieldName, $size ) {
		$req = $this->getRequest();

		$label = Html::element( 'label', [ 'for' => $fieldName ], $labelText );
		$row = "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-label' ], $label );
		$input = new OOUI\TextInputWidget( [
			'classes' => [ 'ext-cargo-' . $fieldName ],
			'value' => $req->getVal( $fieldName )
		] );
		$row .= "\n\t" . Html::rawElement( 'td', null, $input );
		return Html::rawElement( 'tr', [ 'class' => 'ext-cargo-tr-' . $fieldName ], $row ) . "\n";
	}

	public function displayTextArea( $labelText, $fieldName, $size ) {
		$req = $this->getRequest();

		$label = Html::element( 'label', [ 'for' => $fieldName ], $labelText );
		$row = "\n\t" . Html::rawElement( 'td', [ 'class' => 'mw-label' ], $label );
		$input = new OOUI\MultilineTextInputWidget( [
			'classes' => [ 'ext-cargo-' . $fieldName ],
			'value' => $req->getVal( $fieldName )
		] );
		$row .= "\n\t" . Html::rawElement( 'td', null, $input ) . "\n";
		return Html::rawElement( 'tr', [ 'class' => 'ext-cargo-tr-' . $fieldName ], $row ) . "\n";
	}

	public function displayOrderByInput( $rowNum, $orderByValue, $orderByDirection ) {
		$text = "\n" . '<tr class="orderByRow" data-order-by-num=' . $rowNum . '>';
		if ( $rowNum == 0 ) {
			$text .= '<td class="mw-label">' .
				'<label for="order_by">' . $this->msg( 'cargo-viewdata-orderby' )->parse() . '</td>';
		} else {
			$text .= '<td></td>';
		}
		$options = [];
		$value = '';
		array_push( $options, [ 'data' => 'ASC', 'label' => 'ASC' ] );
		if ( $orderByDirection == 'ASC' ) {
			$value = 'ASC';
		}
		array_push( $options, [ 'data' => 'DESC', 'label' => 'DESC' ] );
		if ( $orderByDirection == 'DESC' ) {
			$value = 'DESC';
		}
		$directionSelect = new OOUI\DropdownInputWidget( [
			'options' => $options,
			'id' => 'order_by_options[' . $rowNum . ']',
			'value' => $value,
			'name' => 'order_by_options[' . $rowNum . ']'
		] );
		$orderByInput = new OOUI\TextInputWidget( [
			'id' => 'order_by[' . $rowNum . ']',
			'value' => $orderByValue
		] );
		$button = new OOUI\ButtonWidget( [
			'id' => ( $rowNum == 0 ) ? 'addButton' : 'deleteButton',
			'icon' => ( $rowNum == 0 ) ? 'add' : 'subtract'
		] );
		$orderByRow = new OOUI\HorizontalLayout( [
			'items' => [
				$orderByInput,
				$directionSelect,
				$button,
			]
		] );
		$text .= '<td>' . $orderByRow;

		return $text;
	}

	public function displayInputForm() {
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

		$text .= $this->displayInputRow( $this->msg( 'cargo-viewdata-tables' )->parse(), 'tables', 100 );
		$text .= $this->displayInputRow( $this->msg( 'cargo-viewdata-fields' )->parse(), 'fields', 100 );
		$text .= $this->displayTextArea( $this->msg( 'cargo-viewdata-where' )->parse(), 'where', 100 );
		$text .= $this->displayTextArea( $this->msg( 'cargo-viewdata-joinon' )->parse(), 'join_on', 100 );
		$text .= $this->displayInputRow( $this->msg( 'cargo-viewdata-groupby' )->parse(), 'group_by', 100 );
		$text .= $this->displayTextArea( $this->msg( 'cargo-viewdata-having' )->parse(), 'having', 100 );
		$orderByValues = $req->getArray( 'order_by' );
		if ( $orderByValues != null ) {
			$orderByDirections = $req->getArray( 'order_by_options' );
			$rowNum = 0;
			foreach ( $orderByValues as $i => $curOrderBy ) {
				$orderByDir = ( $orderByDirections == null ) ? null : $orderByDirections[$i];
				$text .= $this->displayOrderByInput( $rowNum++, $curOrderBy, $orderByDir );
			}
		} else {
			$text .= $this->displayOrderByInput( 0, null, null );
		}
		$text .= $this->displayInputRow( $this->msg( 'cargo-viewdata-limit' )->parse(), 'limit', 3 );
		$text .= $this->displayInputRow( $this->msg( 'cargo-viewdata-offset' )->parse(), 'offset', 3 );
		$formatLabel = '<label for="format">' . $this->msg( 'cargo-viewdata-format' )->parse();
		$options = [];
		$formatOptionDefault = $this->msg( 'cargo-viewdata-defaultformat' )->parse();
		array_push( $options, [ 'data' => '', 'label' => '(' . $formatOptionDefault . ')' ] );
		$value = '';
		$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
		foreach ( $formatClasses as $formatName => $formatClass ) {
			if ( $formatName == $req->getVal( 'format' ) ) {
				$value = $formatName;
			}
			array_push( $options, [ 'data' => $formatName, 'label' => $formatName ] );
		}
		$formatDropdown = new OOUI\DropdownInputWIdget( [
			'options' => $options,
			'name' => 'format',
			'id' => 'format',
			'value' => $value
		] );
		$text .= <<<END
<tr class="ext-cargo-tr-format">
<td class="mw-label">
$formatLabel
</td>
<td>$formatDropdown

END;
		$submitButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'htmlform-submit' )->parse(),
			'type' => 'submit',
			'flags' => [ 'primary', 'progressive' ]
		] );
		$text .= <<<END

</select>
</td>
</tr>
</tbody>
</table>
<br>
$submitButton
</form>

END;
		return $text;
	}

	public function getWikitextForQuery() {
		$req = $this->getRequest();

		$wikitext = "{{#cargo_query:\n";
		$vals = $req->getValues();
		$firstParam = true;
		foreach ( $vals as $key => $val ) {
			if ( $key == 'title' || $key == 'order_by_options' ) {
				continue;
			}
			$key = str_replace( '_', ' ', $key );
			if ( $key == 'order by' ) {
				$orderByVal = '';
				foreach ( $val as $i => $orderByField ) {
					if ( $orderByField == '' ) {
						continue;
					}
					if ( array_key_exists( 'order_by_options', $vals ) ) {
						$option = $vals['order_by_options'][$i];
					} else {
						$option = '';
					}
					$orderByVal .= $orderByField . ' ' . $option . ', ';
				}
				$val = $orderByVal;
			}
			$val = trim( $val );
			$val = trim( $val, ',' );
			if ( $val == '' ) {
				continue;
			}
			if ( $firstParam ) {
				$firstParam = false;
			} else {
				$wikitext .= '|';
			}
			$wikitext .= "$key=$val\n";
		}
		$wikitext .= "}}";

		return '<pre>' . htmlspecialchars( $wikitext ) . '</pre>';
	}

	private function displayBottomPane( $paneName, $paneText ) {
		$html = <<<END
<div style="max-width: 70em;">
<span style="width: 100%;" class="oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-indicatorElement oo-ui-labelElement oo-ui-buttonWidget">
<a href="#" class="specialCargoQuery-extraPane-toggle oo-ui-buttonElement-button" role="button" tabindex="0" aria-disabled="false" rel="nofollow">
$paneName
<span class="oo-ui-indicatorElement-indicator oo-ui-indicator-down"></span>
</a>
</span>
<div class="specialCargoQuery-extraPane">
$paneText
</div>
</div>

END;
		return $html;
	}

}

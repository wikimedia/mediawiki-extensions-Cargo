<?php
/**
 * Handles the 'pagevalues' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoPageValuesAction extends Action {
	/**
	 * Return the name of the action this object responds to.
	 * @return String lowercase
	 */
	public function getName() {
		return 'pagevalues';
	}

	/**
	 * The main action entry point. Do all output for display and send it
	 * to the context output.
	 * $this->getOutput(), etc.
	 */
	public function show() {
		$title = $this->page->getTitle();
		$pageValuesPage = new CargoPageValues( $title );
		$pageValuesPage->execute();
	}

	/**
	 * Add the "Page values" link to the toolbox.
	 *
	 * @param BaseTemplate $skinTemplate
	 * @param array &$toolbox
	 * @return bool
	 */
	public static function addLink( BaseTemplate $skinTemplate, array &$toolbox ) {
		$title = $skinTemplate->getSkin()->getTitle();
		// This function doesn't usually get called for special pages,
		// but sometimes it is.
		if ( $title->isSpecialPage() ) {
			return true;
		}

		$toolbox['cargo-pagevalues'] = [
			'msg' => 'pagevalues',
			'href' => $title->getLocalUrl( [ 'action' => 'pagevalues' ] ),
			'id' => 't-cargopagevalueslink',
			'rel' => 'cargo-pagevalues'
		];

		return true;
	}

}

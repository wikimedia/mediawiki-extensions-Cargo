<?php
/**
 * Handles the 'pagevalues' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoPageValuesAction {
	/**
	 * Return the name of the action this object responds to
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
	public static function show( $action, Article $article ) {
		$title = $article->getTitle();

		if ( $action == 'pagevalues' ) {
			$pageValuesPage = new CargoPageValues( $title );
			$pageValuesPage->execute();
			return false;
		}

		return true;
	}

	/**
	 * Add the "Page values" link to the toolbox
	 *
	 * @param BaseTemplate $skinTemplate
	 * @param array $toolbox
	 * @return boolean
	 */
	public static function addLink( BaseTemplate $skinTemplate, array &$toolbox ) {
		$title = $skinTemplate->getSkin()->getTitle();
		// This function doesn't usually get called for special pages, 
		// but sometimes it is.
		if ( $title->isSpecialPage() ) {
			return true;
		}

		$toolbox['cargo-pagevalues'] = array(
			'msg' => 'pagevalues',
			'href' => $title->getLocalUrl( array( 'action' => 'pagevalues' ) ),
			'id' => 't-cargopagevalueslink',
			'rel' => 'cargo-pagevalues'
		);

		return true;
	}
}

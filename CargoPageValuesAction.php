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
	public function getName(){
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

	public static function addLink( $skinTemplate, &$toolbox) {
		$toolbox['cargo-pagevalues'] = array(
			'text' => $skinTemplate->getSkin()->getContext()->msg( 'pagevalues' )->text(),
			'href' => $skinTemplate->getSkin()->getTitle()->getLocalUrl( array( 'action' => 'pagevalues' ) ),
			'id' => 't-cargopagevalueslink',
			'rel' => 'cargo-pagevalues'
		);

		return true;
	}
}

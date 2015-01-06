<?php
/**
 * Handles the 'recreatedata' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecreateDataAction {
	/**
	 * Return the name of the action this object responds to
	 * @return String lowercase
	 */
	public function getName(){
		return 'recreatedata';
	}

	/**
	 * The main action entry point. Do all output for display and send it to the context
	 * output.
	 * $this->getOutput(), etc.
	 */
	public static function show( $action, Article $article ) {
		$title = $article->getTitle();

		// These tabs should only exist for template pages, that
		// either call (or called) #cargo_declare, or call
		// #cargo_attach.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );

		if ( $tableName == '' ) {
			return true;
		}

		if ( $action == 'recreatedata' ) {
			$recreateDataPage = new CargoRecreateData( $title, $tableName, $isDeclared );
			$recreateDataPage->execute();
			return false;
		}

		return true;
	}

	/**
	 * Adds an "action" (i.e., a tab) to edit the current article with
	 * a form
	 */
	static function displayTab( $obj, &$content_actions ) {
		global $wgRequest;

		$title = $obj->getTitle();
		if ( !$title ) {
			return true;
		}

		// Make sure that this is a template page, that it either
		// has (or had) a #cargo_declare call or has a #cargo_attach
		// call, and that the user is allowed to recreate its data.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );
		if ( $tableName == '' ) {
			return true;
		}

		if ( !$title->userCan( 'recreatecargodata' ) ) {
			return true;
		}

		// Check if table already exists, and set tab accordingly.
		if ( CargoUtils::tableExists( $tableName ) ) {
			$recreateDataTabText = wfMessage( 'recreatedata' )->parse();
		} else {
			$recreateDataTabText = wfMessage( 'cargo-createdatatable' )->parse();
		}

		$recreateDataTab = array(
			'class' => ( $wgRequest->getVal( 'action' ) == 'recreatdata' ) ? 'selected' : '',
			'text' => $recreateDataTabText,
			'href' => $title->getLocalURL( 'action=recreatedata' )
		);

		$content_actions['recreatedata'] = $recreateDataTab;

		return true; // always return true, in order not to stop MW's hook processing!
	}

	/**
	 * Like displayTab(), but called with a different hook - this one is
	 * called for the 'Vector' skin, and others.
	 */
	static function displayTab2( $obj, &$links ) {
		// The old '$content_actions' array is thankfully just a
		// sub-array of this one.
		$views_links = $links['actions'];
		self::displayTab( $obj, $views_links );
		$links['actions'] = $views_links;
		return true;

	}

}

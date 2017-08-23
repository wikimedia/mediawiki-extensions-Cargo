<?php
/**
 * Handles the 'recreatedata' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecreateDataAction extends Action {
	/**
	 * Return the name of the action this object responds to
	 * @return String lowercase
	 */
	public function getName() {
		return 'recreatedata';
	}

	/**
	 * The main action entry point. Do all output for display and send it
	 * to the context output.
	 * $this->getOutput(), etc.
	 */
	public function show() {
		$title = $this->page->getTitle();

		// These tabs should only exist for template pages, that
		// either call (or called) #cargo_declare, or call
		// #cargo_attach.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );

		if ( $tableName == '' ) {
			// @TODO - display an error message here.
			return;
		}

		$recreateDataPage = new CargoRecreateData( $title, $tableName, $isDeclared );
		$recreateDataPage->execute();
	}

	/**
	 * Adds an "action" (i.e., a tab) to recreate the current article's data
	 *
	 * @param SkinTemplate $obj
	 * @param array $content_actions
	 * @return boolean
	 */
	static function displayTab( SkinTemplate $obj, array &$content_actions ) {
		$title = $obj->getTitle();
		if ( !$title || $title->getNamespace() !== NS_TEMPLATE ||
			!$title->userCan( 'recreatecargodata' ) ) {
			return true;
		}
		$request = $obj->getRequest();

		// Make sure that this is a template page, that it either
		// has (or had) a #cargo_declare call or has a #cargo_attach
		// call, and that the user is allowed to recreate its data.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );
		if ( $tableName == '' ) {
			return true;
		}

		// Check if table already exists, and set tab accordingly.
		$cdb = CargoUtils::getDB();
		if ( $cdb->tableExists( $tableName ) ) {
			$recreateDataTabMsg = 'recreatedata';
		} else {
			$recreateDataTabMsg = 'cargo-createdatatable';
		}

		$recreateDataTab = array(
			'class' => ( $request->getVal( 'action' ) == 'recreatedata' ) ? 'selected' : '',
			'text' => $obj->msg( $recreateDataTabMsg )->parse(),
			'href' => $title->getLocalURL( 'action=recreatedata' )
		);

		$content_actions['recreatedata'] = $recreateDataTab;

		return true;
	}

	/**
	 * Like displayTab(), but called with a different hook - this one is
	 * called for the 'Vector' skin, and others.
	 *
	 * @param SkinTemplate $obj
	 * @param array $links
	 * @return boolean
	 */
	static function displayTab2( SkinTemplate $obj, array &$links ) {
		// The old '$content_actions' array is thankfully just a
		// sub-array of this one.
		$views_links = $links['actions'];
		self::displayTab( $obj, $views_links );
		$links['actions'] = $views_links;
		return true;
	}

}

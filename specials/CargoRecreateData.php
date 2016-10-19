<?php
/**
 * Displays an interface to let users recreate data via the Cargo
 * extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecreateData extends UnlistedSpecialPage {
	public $mTemplateTitle;
	public $mTableName;
	public $mIsDeclared;

	function __construct( $templateTitle, $tableName, $isDeclared ) {
		parent::__construct( 'RecreateData', 'recreatecargodata' );
		$this->mTemplateTitle = $templateTitle;
		$this->mTableName = $tableName;
		$this->mIsDeclared = $isDeclared;
	}

	function execute( $query = null ) {
		global $wgUser, $wgScriptPath, $cgScriptPath;

		// Check permissions.
		if ( !$wgUser->isAllowed( 'recreatecargodata' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$out = $this->getOutput();

		$this->setHeaders();

		$cdb = CargoUtils::getDB();
		$tableExists = $cdb->tableExists( $this->mTableName );
		if ( !$tableExists ) {
			$out->setPageTitle( $this->msg( 'cargo-createdatatable' )->parse() );
		}

		if ( empty( $this->mTemplateTitle ) ) {
			// No template.
			// TODO - show an error message.
			return true;
		}

		$out->addModules( 'ext.cargo.recreatedata' );

		$templateData = array();
		$dbw = wfGetDB( DB_MASTER );

		$templateData[] = array(
			'name' => $this->mTemplateTitle->getText(),
			'numPages' => $this->getNumPagesThatCallTemplate( $dbw, $this->mTemplateTitle )
		);

		if ( $this->mIsDeclared ) {
			// Get all attached templates.
			$res = $dbw->select( 'page_props',
				array(
					'pp_page'
				),
				array(
					'pp_value' => $this->mTableName,
					'pp_propname' => 'CargoAttachedTable'
				)
			);
			while ( $row = $dbw->fetchRow( $res ) ) {
				$templateID = $row['pp_page'];
				$attachedTemplateTitle = Title::newFromID( $templateID );
				$numPages = $this->getNumPagesThatCallTemplate( $dbw, $attachedTemplateTitle );
				$attachedTemplateName = $attachedTemplateTitle->getText();
				$templateData[] = array(
					'name' => $attachedTemplateName,
					'numPages' => $numPages
				);
			}
		}

		$ct = SpecialPage::getTitleFor( 'CargoTables' );
		$viewTableURL = $ct->getInternalURL() . '/' . $this->mTableName;

		// Store all the necesssary data on the page.
		$text = Html::element( 'div', array(
				'hidden' => 'true',
				'id' => 'recreateDataData',
				// These two variables are not data-
				// specific, but this seemed like the
				// easiest way to pass them over without
				// interfering with any other pages.
				// (Is this the best way to get the
				// API URL?)
				'apiurl' => $wgScriptPath . "/api.php",
				'cargoscriptpath' => $cgScriptPath,
				'tablename' => $this->mTableName,
				'isdeclared' => $this->mIsDeclared,
				'viewtableurl' => $viewTableURL
			), json_encode( $templateData ) );

		// Simple form.
		$text .= '<div id="recreateDataCanvas">' . "\n";
		$msg = $tableExists ? 'cargo-recreatedata-desc' : 'cargo-recreatedata-createdata';
		$text .= Html::element( 'p', null, $this->msg( $msg )->parse() );
		$text .= Html::element( 'button', array( 'id' => 'cargoSubmit' ), $this->msg( 'ok' )->parse() );
		$text .= "\n</div>";

		$out->addHTML( $text );

		return true;
	}

	function getNumPagesThatCallTemplate( $dbw, $templateTitle ) {
		$res = $dbw->select(
			array( 'page', 'templatelinks' ),
			'COUNT(*) AS total',
			array(
				"tl_from=page_id",
				"tl_namespace" => $templateTitle->getNamespace(),
				"tl_title" => $templateTitle->getDBkey() ),
			__METHOD__,
			array()
		);
		$row = $dbw->fetchRow( $res );
		return intval($row['total']);
	}

}

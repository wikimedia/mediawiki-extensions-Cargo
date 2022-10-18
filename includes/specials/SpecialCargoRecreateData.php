<?php
/**
 * Displays an interface to let users recreate data via the Cargo
 * extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class SpecialCargoRecreateData extends UnlistedSpecialPage {
	public $mTemplateTitle;
	public $mTableName;
	public $mIsDeclared;

	public function __construct( $templateTitle = null, $tableName = null, $isDeclared = false ) {
		parent::__construct( 'RecreateCargoData', 'recreatecargodata' );
		$this->mTemplateTitle = $templateTitle;
		$this->mTableName = $tableName;
		$this->mIsDeclared = $isDeclared;
	}

	public function execute( $query = null ) {
		global $cgScriptPath;

		$this->checkPermissions();

		$out = $this->getOutput();
		$out->enableOOUI();
		$this->setHeaders();

		if ( $this->mTableName == null ) {
			$this->mTableName = $query;
		}
		$tableExists = CargoUtils::tableFullyExists( $this->mTableName );
		if ( !$tableExists ) {
			$out->setPageTitle( $this->msg( 'cargo-createdatatable' )->parse() );
		}

		// Disable page if "replacement table" exists.
		$possibleReplacementTable = $this->mTableName . '__NEXT';
		if ( CargoUtils::tableFullyExists( $this->mTableName ) && CargoUtils::tableFullyExists( $possibleReplacementTable ) ) {
			$text = $this->msg( 'cargo-recreatedata-replacementexists', $this->mTableName, $possibleReplacementTable )->parse();
			$ctURL = SpecialPage::getTitleFor( 'CargoTables' )->getFullURL();
			$viewURL = $ctURL . '/' . $this->mTableName;
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
			$viewReplacementText = $this->msg( 'cargo-cargotables-viewreplacementlink' )->parse();

			$text .= ' (' . Xml::element( 'a', [ 'href' => $viewURL ], $viewReplacementText ) . ')';
			$out->addHTML( $text );
			return true;
		}

		$specialTableNames = CargoUtils::specialTableNames();
		if ( empty( $this->mTemplateTitle ) && !in_array( $this->mTableName, $specialTableNames ) ) {
			// TODO - show an error message.
			return true;
		}

		$out->addModules( 'ext.cargo.recreatedata' );

		$templateData = [];
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->mTemplateTitle === null ) {
			if ( $this->mTableName == '_pageData' ) {
				$conds = null;
			} elseif ( $this->mTableName == '_fileData' ) {
				$conds = 'page_namespace = ' . NS_FILE;
			} elseif ( $this->mTableName == '_bpmnData' ) {
				$conds = 'page_namespace = ' . FD_NS_BPMN;
			} else { // if ( $this->mTableName == '_ganttData' ) {
				$conds = 'page_namespace = ' . FD_NS_GANTT;
			}
			$numTotalPages = $dbw->selectField( 'page', 'COUNT(*)', $conds );
		} else {
			$numTotalPages = null;
			$templateData[] = [
				'name' => $this->mTemplateTitle->getText(),
				'numPages' => $this->getNumPagesThatCallTemplate( $dbw, $this->mTemplateTitle )
			];
		}

		if ( $this->mIsDeclared ) {
			// Get all attached templates.
			$res = $dbw->select( 'page_props',
				[
					'pp_page'
				],
				[
					'pp_value' => $this->mTableName,
					'pp_propname' => 'CargoAttachedTable'
				]
			);
			foreach ( $res as $row ) {
				$templateID = $row->pp_page;
				$attachedTemplateTitle = Title::newFromID( $templateID );
				$numPages = $this->getNumPagesThatCallTemplate( $dbw, $attachedTemplateTitle );
				$attachedTemplateName = $attachedTemplateTitle->getText();
				$templateData[] = [
					'name' => $attachedTemplateName,
					'numPages' => $numPages
				];
			}
		}

		$ct = SpecialPage::getTitleFor( 'CargoTables' );
		$viewTableURL = $ct->getLocalURL() . '/' . $this->mTableName;

		// Store all the necesssary data on the page.
		$text = Html::element( 'div', [
				'hidden' => 'true',
				'id' => 'recreateDataData',
				// 'cargoscriptpath' is not data-
				// specific, but this seemed like the
				// easiest way to pass it over without
				// interfering with any other pages.
				'cargoscriptpath' => $cgScriptPath,
				'tablename' => $this->mTableName,
				'isspecialtable' => ( $this->mTemplateTitle == null ),
				'isdeclared' => $this->mIsDeclared,
				'totalpages' => $numTotalPages,
				'viewtableurl' => $viewTableURL
			], json_encode( $templateData ) );

		// Simple form.
		$text .= '<div id="recreateDataCanvas">' . "\n";
		if ( $tableExists ) {
			$checkBox = new OOUI\FieldLayout(
				new OOUI\CheckboxInputWidget( [
					'name' => 'createReplacement',
					'selected' => true,
					'value' => 1,
				] ),
				[
					'label' => $this->msg( 'cargo-recreatedata-createreplacement' )->parse(),
					'align' => 'inline',
					'infusable' => true,
				]
			);
			$text .= Html::rawElement( 'p', null, $checkBox );
		}

		if ( $this->mTemplateTitle == null ) {
			$msg = $tableExists ? 'cargo-recreatedata-recreatetable' : 'cargo-recreatedata-createtable';
			$text .= Html::element( 'p', null, $this->msg( $msg, $this->mTableName )->parse() );
		} else {
			$msg = $tableExists ? 'cargo-recreatedata-desc' : 'cargo-recreatedata-createdata';
			$text .= Html::element( 'p', null, $this->msg( $msg )->parse() );
		}

		$text .= new OOUI\ButtonInputWidget( [
			'id' => 'cargoSubmit',
			'label' => $this->msg( 'ok' )->parse(),
			'flags' => [ 'primary', 'progressive' ]
		 ] );
		$text .= "\n</div>";

		$out->addHTML( $text );

		return true;
	}

	public function getNumPagesThatCallTemplate( $dbw, $templateTitle ) {
		$conds = [ "tl_from=page_id" ];
		if ( class_exists( 'MediaWiki\CommentFormatter\CommentBatch' ) ) {
			// MW 1.38+
			$conds['tl_target_id'] = $templateTitle->getID();
		} else {
			$conds['tl_namespace'] = $templateTitle->getNamespace();
			$conds['tl_title'] = $templateTitle->getDBkey();
		}

		$res = $dbw->select(
			[ 'page', 'templatelinks' ],
			'COUNT(*) AS total',
			$conds,
			__METHOD__,
			[]
		);
		$row = $res->fetchRow();
		return intval( $row['total'] );
	}

}

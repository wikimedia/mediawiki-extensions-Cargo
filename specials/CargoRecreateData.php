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

		$tableExists = CargoUtils::tableExists( $this->mTableName );
		if ( !$tableExists ) {
			$out->setPageTitle( $this->msg( 'cargo-createdatatable' )->parse() );
		}

		if ( empty( $this->mTemplateTitle ) ) {
			// No template.
			// TODO - show an error message.
			return true;
		}

		$templateData = array();
		$dbr = wfGetDB( DB_SLAVE );

		$templateData[] = array(
			'name' => $this->mTemplateTitle->getText(),
			'numPages' => $this->getNumPagesThatCallTemplate( $dbr, $this->mTemplateTitle )
		);

		if ( $this->mIsDeclared ) {
			// Get all attached templates.
			$res = $dbr->select( 'page_props',
				array(
					'pp_page'
				),
				array(
					'pp_value' => $this->mTableName,
					'pp_propname' => 'CargoAttachedTable'
				)
			);
			while ( $row = $dbr->fetchRow( $res ) ) {
				$templateID = $row['pp_page'];
				$attachedTemplateTitle = Title::newFromID( $templateID );
				$numPages = $this->getNumPagesThatCallTemplate( $dbr, $attachedTemplateTitle );
				$attachedTemplateName = $attachedTemplateTitle->getText();
				$templateData[] = array(
					'name' => $attachedTemplateName,
					'numPages' => $numPages
				);
				$pagesPerAttachedTemplate[$attachedTemplateName] = $numPages;
			}
		}

		// Is this the best way to get the API URL?
		$apiURL = $wgScriptPath . "/api.php";
		$templateDataJS = json_encode( $templateData );
		$recreateTableDoneMsg = wfMessage( 'cargo-recreatedata-tablecreated', $this->mTableName )->text();
		$recreateDataDoneMsg = wfMessage( 'cargo-recreatedata-success' )->text();

		$jsText = <<<END
<script type="text/javascript">
var apiURL = "$apiURL";
var cargoScriptPath = "$cgScriptPath";
var tableName = "{$this->mTableName}";
var templateData = $templateDataJS;
var recreateTableDoneMsg = '$recreateTableDoneMsg';
var recreateDataDoneMsg = '$recreateDataDoneMsg';
var numTotalPages = 0;
var numTotalPagesHandled = 0;

for ( var i = 0; i < templateData.length; i++ ) {
	numTotalPages += parseInt( templateData[i]['numPages'] );
}


function cargoReplaceRecreateDataForm() {
	$("#recreateDataCanvas").html( "<div id=\"recreateTableProgress\"></div>" );
	$("#recreateDataCanvas").append( "<div id=\"recreateDataProgress\"></div>" );
}

/**
 * Recursive function that uses Ajax to populate a Cargo DB table with the
 * data for one or more templates.
 */
function cargoCreateJobs( templateNum, numPagesHandled, replaceOldRows ) {
	var curTemplate = templateData[templateNum];
	var templateName = curTemplate['name'];
	var numPages = curTemplate['numPages'];
	if ( numTotalPages > 1000 ) {
		var remainingPixels = 100 * numTotalPagesHandled / numTotalPages;
		var progressImage = "<progress value=\"" + remainingPixels + "\" max=\"100\"></progress>";
	} else {
		var progressImage = "<img src=\"" + cargoScriptPath + "/skins/loading.gif\" />";
	}
	$("#recreateDataProgress").html( "<p>" + progressImage + "</p>" );
	var queryStringData = {
		action: "cargorecreatedata",
		table: tableName,
		template: templateName,
		offset: numPagesHandled
	};
	if ( replaceOldRows ) {
		queryStringData['replaceOldRows'] = true;
	}
	$.get(
		apiURL,
		queryStringData
	)
	.done(function( msg ) {
		newNumPagesHandled = Math.min( numPagesHandled + 500, numPages );
		numTotalPagesHandled += newNumPagesHandled - numPagesHandled;
		if ( newNumPagesHandled < numPages ) {
			cargoCreateJobs( templateNum, newNumPagesHandled, replaceOldRows );
		} else {
			if ( templateNum + 1 < templateData.length ) {
				cargoCreateJobs( templateNum + 1, 0, replaceOldRows );
			} else {
				// We're done.
				$("#recreateDataProgress").html( "<p>" + recreateDataDoneMsg + "</p>" );
			}
		}
	});
}

END;

		if ( $this->mIsDeclared ) {
			$jsText .= <<<END
$( "#cargoSubmit" ).click( function() {
	cargoReplaceRecreateDataForm();

	var templateName = templateData[0]['name'];
	$("#recreateTableProgress").html( "<img src=\"" + cargoScriptPath + "/skins/loading.gif\" />" );
	$.get(
		apiURL,
		{ action: "cargorecreatetables", template: templateName }
	)
	.done(function( msg ) {
		$("#recreateTableProgress").html( "<p>" + recreateTableDoneMsg + "</p>" );
		cargoCreateJobs( 0, 0, false );
	});
});
</script>

END;
		} else {
			$jsText .= <<<END
$( "#cargoSubmit" ).click( function() {
	cargoReplaceRecreateDataForm();
	cargoCreateJobs( 0, 0, true );
});
</script>

END;
		}
		$out->addScript( $jsText );

		$formSubmitted = $this->getRequest()->getText( 'submitted' ) == 'yes';
		if ( $formSubmitted ) {
			// Recreate the data!
			$this->recreateData();
			// Redirect to the main template page - we need to
			// add "action=purge" to the URL so that the new
			// "View table" link will show up on the page.
			$out->redirect( $this->mTemplateTitle->getFullURL( array( 'action' => 'purge' ) ) );
			return true;
		}

		// Simple form.
		$text = '<div id="recreateDataCanvas">' . "\n";
		$msg = $tableExists ? 'cargo-recreatedata-desc' : 'cargo-recreatedata-createdata';
		$text .= Html::element( 'p', null, $this->msg( $msg )->parse() );
		$text .= Html::element( 'button', array( 'id' => 'cargoSubmit' ), $this->msg( 'ok' )->parse() );
		$text .= "\n</div>";

		$out->addHTML( $text );

		return true;
	}

	function getNumPagesThatCallTemplate( $dbr, $templateTitle ) {
		$res = $dbr->select(
			array( 'page', 'templatelinks' ),
			'COUNT(*)',
			array(
				"tl_from=page_id",
				"tl_namespace" => $templateTitle->getNamespace(),
				"tl_title" => $templateTitle->getDBkey() ),
			__METHOD__,
			array()
		);
		$row = $dbr->fetchRow( $res );
		return $row[0];
	}

}

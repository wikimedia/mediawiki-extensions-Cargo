/**
 * @author Yaron Koren
 */

var dataDiv = $("div#recreateDataData");

var apiURL = dataDiv.attr("apiurl");
var cargoScriptPath = dataDiv.attr("cargoscriptpath");
var tableName = dataDiv.attr("tablename");
var isDeclared = dataDiv.attr("isdeclared");
var templateData = jQuery.parseJSON( dataDiv.html() );

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
				$("#recreateDataProgress").html( "<p>" + mw.msg( 'cargo-recreatedata-success' ) + "</p>" );
			}
		}
	});
}

jQuery( "#cargoSubmit" ).click( function() {
	cargoReplaceRecreateDataForm();

	if ( isDeclared ) {

		var templateName = templateData[0]['name'];
		$("#recreateTableProgress").html( "<img src=\"" + cargoScriptPath + "/skins/loading.gif\" />" );
		$.get(
			apiURL,
			{ action: "cargorecreatetables", template: templateName }
		)
		.done(function( msg ) {
			$("#recreateTableProgress").html( "<p>" + mw.msg( 'cargo-recreatedata-tablecreated', tableName ) + "</p>" );
			cargoCreateJobs( 0, 0, false );
		});

	} else {
		cargoCreateJobs( 0, 0, true );
	}
});

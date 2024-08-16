/**
 * @author Yaron Koren
 */

( function( $, mw, cargo ) {
	'use strict';

	/**
	 * Class constructor
	 */
	cargo.recreateData = function() {};

	var recreateData = new cargo.recreateData();

	var dataDiv = $("div#recreateDataData");
	var cargoScriptPath = dataDiv.attr("cargoscriptpath");
	var tableName = dataDiv.attr("tablename");
	var isSpecialTable = dataDiv.attr("isspecialtable");
	var isDeclared = dataDiv.attr("isdeclared");
	var numTotalPages = dataDiv.attr("totalpages");
	var viewTableURL = dataDiv.attr("viewtableurl");
	var createReplacement = false;
	var templateData = jQuery.parseJSON( dataDiv.html() );
	var numTotalPagesHandled = 0;

	if ( numTotalPages == null ) {
		numTotalPages = 0;
		for ( var i = 0; i < templateData.length; i++ ) {
			numTotalPages += parseInt( templateData[i].numPages );
		}
	}

	recreateData.replaceForm = function() {
		$("#recreateDataCanvas").html( "<div id=\"recreateTableProgress\"></div>" );
		$("#recreateDataCanvas").append( "<div id=\"recreateDataProgress\"></div>" );
	}

	/**
	 * Recursive function that uses Ajax to populate a Cargo DB table with
	 * the data for one or more templates.
	 */
	recreateData.createJobs = function( templateNum, numPagesHandled, replaceOldRows ) {
		var curTemplate = templateData[templateNum];
		var progressImage = "<img src=\"" + cargoScriptPath + "/resources/images/loading.gif\" />";
		if ( numTotalPages > 1000 ) {
			var remainingPixels = 100 * numTotalPagesHandled / numTotalPages;
			progressImage = "<progress value=\"" + remainingPixels + "\" max=\"100\"></progress>";
		}
		$("#recreateDataProgress").html( "<p>" + progressImage + "</p>" );
		var queryStringData = {
			action: "cargorecreatedata",
			format: "json",
			table: tableName,
			template: curTemplate ? curTemplate.name : '',
			offset: numPagesHandled
		};
		if ( replaceOldRows ) {
			queryStringData.replaceOldRows = true;
		}

		let mwApi = new mw.Api();
		mwApi.postWithToken( 'csrf', queryStringData )
		.done(function( msg ) {
			var curNumPages = curTemplate ? curTemplate.numPages : numTotalPages;
			var newNumPagesHandled = Math.min( numPagesHandled + 500, curNumPages );
			numTotalPagesHandled += newNumPagesHandled - numPagesHandled;
			if ( newNumPagesHandled < curNumPages ) {
				recreateData.createJobs( templateNum, newNumPagesHandled, replaceOldRows );
			} else {
				if ( templateNum + 1 < templateData.length ) {
					recreateData.createJobs( templateNum + 1, 0, replaceOldRows );
				} else {
					// We're done.
					var successTag = $( '<p>' ).text( mw.msg( 'cargo-recreatedata-success' ) );
					$("#recreateDataProgress").html( successTag );
					if ( createReplacement ) {
						viewTableURL += ( viewTableURL.indexOf('?') === -1 ) ? '?' : '&';
						viewTableURL += "_replacement";
					}
					var linkMsg = createReplacement ? 'cargo-cargotables-viewreplacementlink' : 'cargo-cargotables-viewtablelink';
					var viewTableLink = $( '<a>' ).attr( 'href', viewTableURL ).text( mw.msg( linkMsg ) );
					var viewTableTag = $( '<p>' ).html( viewTableLink );
					$("#recreateDataProgress").append( viewTableTag );
				}
			}
		}).fail(function (error) {
			// handle failure
			var failureTag = $( '<p>' ).text( mw.msg( 'cargo-recreatedata-job-creation-failed', tableName ) );
			$("#recreateTableProgress").html( failureTag );
		});
	}

	jQuery( "#cargoSubmit" ).click( function() {
		createReplacement = $("[name=createReplacement]").is( ":checked" );

		recreateData.replaceForm();

		if ( isDeclared || isSpecialTable ) {
			$("#recreateTableProgress").html( "<img src=\"" + cargoScriptPath + "/resources/images/loading.gif\" />" );
			if ( isDeclared ) {
				var queryStringData = {
					action: "cargorecreatetables",
					format: 'json',
					template: templateData[0].name,
				};
			} else {
				var queryStringData = {
					action: "cargorecreatespecialtable",
					table: tableName
				};
			}
			if ( createReplacement ) {
				queryStringData.createReplacement = true;
			}

			let mwApi = new mw.Api();
			mwApi.postWithToken( 'csrf', queryStringData )
			.then(function( msg ) {
				var displayMsg = createReplacement ? 'cargo-recreatedata-replacementcreated' : 'cargo-recreatedata-tablecreated';
				var tableCreatedTag = $( '<p>' ).text(  mw.msg( displayMsg, tableName ) );
				$("#recreateTableProgress").html( tableCreatedTag );
				recreateData.createJobs( 0, 0, false );
			}).fail(function (error) {
				var tableCreationFailedTag = $( '<p>' ).text(  mw.msg( 'cargo-recreatedata-table-creation-failed', tableName ) );
				$("#recreateTableProgress").html( tableCreationFailedTag );
			});
		} else {
			recreateData.createJobs( 0, 0, true );
		}
	});

	// This is not really needed at the moment, since no other JS code
	// is calling this code.
	recreateData.prototype = recreateData;

} )( jQuery, mediaWiki, cargo );
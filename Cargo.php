<?php
/**
 * Initialization file for Cargo.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

// There are bugs in MW 1.25 and 1.26 that make extension.json
// unusable for Cargo - for simplicity's sake, don't load extensions
// unless we're at version 1.27 or higher.
// if ( function_exists( 'wfLoadExtension' ) ) {
if ( version_compare( $GLOBALS['wgVersion'], '1.27', '>=' ) ) {
	wfLoadExtension( 'Cargo' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Cargo'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CargoMagic'] = __DIR__ . '/Cargo.i18n.magic.php';
	$wgExtensionMessagesFiles['CargoAlias'] = __DIR__ . '/Cargo.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Cargo extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
}

// All the rest is for backward compatibility, for MW 1.26 and lower.

define( 'CARGO_VERSION', '1.7' );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Cargo',
	'namemsg' => 'cargo-extensionname',
	'version' => CARGO_VERSION,
	'author' => 'Yaron Koren',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Cargo',
	'descriptionmsg' => 'cargo-desc',
);

$dir = __DIR__ . '/';

// Script path.
$cgScriptPath = $wgScriptPath . '/extensions/Cargo';

$wgJobClasses['cargoPopulateTable'] = 'CargoPopulateTableJob';

$wgHooks['ParserFirstCallInit'][] = 'CargoHooks::registerParserFunctions';
$wgHooks['MakeGlobalVariablesScript'][] = 'CargoHooks::setGlobalJSVariables';
$wgHooks['PageContentSaveComplete'][] = 'CargoHooks::onPageContentSaveComplete';
$wgHooks['ApprovedRevsRevisionApproved'][] = 'CargoHooks::onARRevisionApproved';
$wgHooks['ApprovedRevsRevisionUnapproved'][] = 'CargoHooks::onARRevisionUnapproved';
$wgHooks['TitleMoveComplete'][] = 'CargoHooks::onTitleMoveComplete';
$wgHooks['ArticleDeleteComplete'][] = 'CargoHooks::onArticleDeleteComplete';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CargoHooks::describeDBSchema';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CargoHooks::cargoSchemaUpdates';
// 'SkinTemplateNavigation' replaced 'SkinTemplateTabs' in the Vector skin
$wgHooks['SkinTemplateTabs'][] = 'CargoRecreateDataAction::displayTab';
$wgHooks['SkinTemplateNavigation'][] = 'CargoRecreateDataAction::displayTab2';
$wgHooks['BaseTemplateToolbox'][] = 'CargoPageValuesAction::addLink';
$wgHooks['SkinTemplateNavigation'][] = 'CargoHooks::addPurgeCacheTab';
$wgHooks['PageForms::TemplateFieldStart'][] = 'CargoHooks::addTemplateFieldStart';
$wgHooks['PageForms::TemplateFieldEnd'][] = 'CargoHooks::addTemplateFieldEnd';
$wgHooks['AdminLinks'][] = 'CargoHooks::addToAdminLinks';
$wgHooks['PageSchemasRegisterHandlers'][] = 'CargoPageSchemas::registerClass';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'CargoHooks::onResourceLoaderGetConfigVars';
$wgHooks['ScribuntoExternalLibraries'][] = 'CargoHooks::addLuaLibrary';

$wgMessagesDirs['Cargo'] = $dir . '/i18n';
$wgExtensionMessagesFiles['CargoMagic'] = $dir . '/Cargo.i18n.magic.php';
$wgExtensionMessagesFiles['CargoAlias'] = $dir . '/Cargo.alias.php';

// API modules
$wgAPIModules['cargoquery'] = 'CargoQueryAPI';
$wgAPIModules['cargorecreatetables'] = 'CargoRecreateTablesAPI';
$wgAPIModules['cargorecreatedata'] = 'CargoRecreateDataAPI';
$wgAPIModules['cargoautocomplete'] = 'CargoAutocompleteAPI';

// Register classes and special pages.
$wgAutoloadClasses['CargoHooks'] = $dir . '/Cargo.hooks.php';
$wgAutoloadClasses['CargoUtils'] = $dir . '/includes/CargoUtils.php';
$wgAutoloadClasses['CargoFieldDescription'] = $dir . '/includes/CargoFieldDescription.php';
$wgAutoloadClasses['CargoTableSchema'] = $dir . '/includes/CargoTableSchema.php';
$wgAutoloadClasses['CargoHierarchyTree'] = $dir . '/includes/CargoHierarchyTree.php';
$wgAutoloadClasses['CargoDeclare'] = $dir . '/includes/parserfunctions/CargoDeclare.php';
$wgAutoloadClasses['CargoAttach'] = $dir . '/includes/parserfunctions/CargoAttach.php';
$wgAutoloadClasses['CargoStore'] = $dir . '/includes/parserfunctions/CargoStore.php';
$wgAutoloadClasses['CargoQuery'] = $dir . '/includes/parserfunctions/CargoQuery.php';
$wgAutoloadClasses['CargoCompoundQuery'] = $dir . '/includes/parserfunctions/CargoCompoundQuery.php';
$wgAutoloadClasses['CargoSQLQuery'] = $dir . '/includes/CargoSQLQuery.php';
$wgAutoloadClasses['CargoQueryDisplayer'] = $dir . '/includes/CargoQueryDisplayer.php';
$wgAutoloadClasses['CargoPageData'] = $dir . '/includes/CargoPageData.php';
$wgAutoloadClasses['CargoFileData'] = $dir . '/includes/CargoFileData.php';
$wgAutoloadClasses['CargoRecurringEvent'] = $dir . '/includes/parserfunctions/CargoRecurringEvent.php';
$wgAutoloadClasses['CargoDisplayMap'] = $dir . '/includes/parserfunctions/CargoDisplayMap.php';
$wgAutoloadClasses['CargoPopulateTableJob'] = $dir . '/includes/CargoPopulateTableJob.php';
$wgAutoloadClasses['CargoRecreateDataAction'] = $dir . '/includes/CargoRecreateDataAction.php';
$wgAutoloadClasses['CargoRecreateData'] = $dir . '/specials/CargoRecreateData.php';
$wgSpecialPages['CargoTables'] = 'CargoTables';
$wgAutoloadClasses['CargoTables'] = $dir . '/specials/CargoTables.php';
$wgSpecialPages['DeleteCargoTable'] = 'CargoDeleteCargoTable';
$wgAutoloadClasses['CargoDeleteCargoTable'] = $dir . '/specials/CargoDeleteTable.php';
$wgSpecialPages['SwitchCargoTable'] = 'CargoSwitchCargoTable';
$wgAutoloadClasses['CargoSwitchCargoTable'] = $dir . '/specials/CargoSwitchTable.php';
$wgSpecialPages['ViewData'] = 'CargoViewData';
$wgAutoloadClasses['CargoViewData'] = $dir . '/specials/CargoViewData.php';
$wgAutoloadClasses['ViewDataPage'] = $dir . '/specials/CargoViewData.php';
$wgSpecialPages['CargoExport'] = 'CargoExport';
$wgAutoloadClasses['CargoExport'] = $dir . '/specials/CargoExport.php';
$wgAutoloadClasses['CargoPageValuesAction'] = $dir . '/includes/CargoPageValuesAction.php';
$wgSpecialPages['PageValues'] = 'CargoPageValues';
$wgAutoloadClasses['CargoPageValues'] = $dir . '/specials/CargoPageValues.php';
$wgAutoloadClasses['CargoQueryAPI'] = $dir . '/api/CargoQueryAPI.php';
$wgAutoloadClasses['CargoRecreateTablesAPI'] = $dir . '/api/CargoRecreateTablesAPI.php';
$wgAutoloadClasses['CargoRecreateDataAPI'] = $dir . '/api/CargoRecreateDataAPI.php';
$wgAutoloadClasses['CargoAutocompleteAPI'] = $dir . '/api/CargoAutocompleteAPI.php';
$wgAutoloadClasses['CargoLuaLibrary'] = $dir . '/CargoLua.library.php';

// Display formats
$wgAutoloadClasses['CargoDisplayFormat'] = $dir . '/formats/CargoDisplayFormat.php';
$wgAutoloadClasses['CargoDeferredFormat'] = $dir . '/formats/CargoDeferredFormat.php';
$wgAutoloadClasses['CargoListFormat'] = $dir . '/formats/CargoListFormat.php';
$wgAutoloadClasses['CargoULFormat'] = $dir . '/formats/CargoULFormat.php';
$wgAutoloadClasses['CargoOLFormat'] = $dir . '/formats/CargoOLFormat.php';
$wgAutoloadClasses['CargoTemplateFormat'] = $dir . '/formats/CargoTemplateFormat.php';
$wgAutoloadClasses['CargoOutlineFormat'] = $dir . '/formats/CargoOutlineFormat.php';
$wgAutoloadClasses['CargoOutlineRow'] = $dir . '/formats/CargoOutlineFormat.php';
$wgAutoloadClasses['CargoOutlineTree'] = $dir . '/formats/CargoOutlineFormat.php';
$wgAutoloadClasses['CargoTreeFormat'] = $dir . '/formats/CargoTreeFormat.php';
$wgAutoloadClasses['CargoTreeFormatNode'] = $dir . '/formats/CargoTreeFormat.php';
$wgAutoloadClasses['CargoTreeFormatTree'] = $dir . '/formats/CargoTreeFormat.php';
$wgAutoloadClasses['CargoEmbeddedFormat'] = $dir . '/formats/CargoEmbeddedFormat.php';
$wgAutoloadClasses['CargoCSVFormat'] = $dir . '/formats/CargoCSVFormat.php';
$wgAutoloadClasses['CargoExcelFormat'] = $dir . '/formats/CargoExcelFormat.php';
$wgAutoloadClasses['CargoJSONFormat'] = $dir . '/formats/CargoJSONFormat.php';
$wgAutoloadClasses['CargoTableFormat'] = $dir . '/formats/CargoTableFormat.php';
$wgAutoloadClasses['CargoDynamicTableFormat'] = $dir . '/formats/CargoDynamicTableFormat.php';
$wgAutoloadClasses['CargoMapsFormat'] = $dir . '/formats/CargoMapsFormat.php';
$wgAutoloadClasses['CargoGoogleMapsFormat'] = $dir . '/formats/CargoGoogleMapsFormat.php';
$wgAutoloadClasses['CargoOpenLayersFormat'] = $dir . '/formats/CargoOpenLayersFormat.php';
$wgAutoloadClasses['CargoCalendarFormat'] = $dir . '/formats/CargoCalendarFormat.php';
$wgAutoloadClasses['CargoTimelineFormat'] = $dir . '/formats/CargoTimelineFormat.php';
$wgAutoloadClasses['CargoCategoryFormat'] = $dir . '/formats/CargoCategoryFormat.php';
$wgAutoloadClasses['CargoBarChartFormat'] = $dir . '/formats/CargoBarChartFormat.php';
$wgAutoloadClasses['CargoGalleryFormat'] = $dir . '/formats/CargoGalleryFormat.php';
$wgAutoloadClasses['CargoTagCloudFormat'] = $dir . '/formats/CargoTagCloudFormat.php';
$wgAutoloadClasses['CargoExhibitFormat'] = $dir . '/formats/CargoExhibitFormat.php';
$wgAutoloadClasses['CargoSearchMySQL'] = $dir . '/includes/search/CargoSearchMySQL.php';

$wgAutoloadClasses['CargoPageSchemas'] = $dir . '/includes/CargoPageSchemas.php';

// Drilldown
$wgAutoloadClasses['CargoAppliedFilter'] = $dir . '/drilldown/CargoAppliedFilter.php';
$wgAutoloadClasses['CargoFilter'] = $dir . '/drilldown/CargoFilter.php';
$wgAutoloadClasses['CargoFilterValue'] = $dir . '/drilldown/CargoFilterValue.php';
$wgAutoloadClasses['CargoDrilldownUtils'] = $dir . '/drilldown/CargoDrilldownUtils.php';
$wgAutoloadClasses['CargoDrilldown'] = $dir . '/drilldown/CargoSpecialDrilldown.php';
$wgAutoloadClasses['CargoDrilldownPage'] = $dir . '/drilldown/CargoSpecialDrilldown.php';
$wgAutoloadClasses['CargoDrilldownHierarchy'] = $dir . '/drilldown/CargoDrilldownHierarchy.php';
$wgSpecialPages['Drilldown'] = 'CargoDrilldown';

// Actions
$wgActions['recreatedata'] = 'CargoRecreateDataAction';
$wgActions['pagevalues'] = 'CargoPageValuesAction';

// User rights
$wgAvailableRights[] = 'recreatecargodata';
$wgGroupPermissions['sysop']['recreatecargodata'] = true;
$wgAvailableRights[] = 'deletecargodata';
$wgGroupPermissions['sysop']['deletecargodata'] = true;

// ResourceLoader modules
$wgResourceModules += array(
	'ext.cargo.main' => array(
		'scripts' => 'libs/Cargo.js',
		'styles' => 'Cargo.css',
		'messages' => array(
			'show',
			'hide'
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.recreatedata' => array(
		'scripts' => array(
			'libs/ext.cargo.js',
			'libs/ext.cargo.recreatedata.js',
		),
		'dependencies' => 'mediawiki.jqueryMsg',
		'messages' => array(
			'cargo-recreatedata-tablecreated',
			'cargo-recreatedata-replacementcreated',
			'cargo-recreatedata-success',
			'cargo-cargotables-viewtablelink',
			'cargo-cargotables-viewreplacementlink'
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.drilldown' => array(
		'styles' => array(
			'drilldown/resources/CargoDrilldown.css',
			'drilldown/resources/CargoJQueryUIOverrides.css',
		),
		'scripts' => array(
			'drilldown/resources/CargoDrilldown.js',
		),
		'dependencies' => array(
			'jquery.ui.autocomplete',
			'jquery.ui.button',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.maps' => array(
		'scripts' => array(
			'libs/ext.cargo.maps.js',
			'libs/markerclusterer.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.calendar.jquery1' => array(
		'styles' => array(
			'libs/FullCalendar/2.9.1/fullcalendar.css',
			'libs/FullCalendar/2.9.1/lang-all.js',
			'libs/ext.cargo.calendar.css',
		),
		'scripts' => array(
			'libs/FullCalendar/2.9.1/fullcalendar.js',
			'libs/ext.cargo.calendar.js',
		),
		'dependencies' => array(
			'moment',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.calendar.jquery3' => array(
		'styles' => array(
			'libs/FullCalendar/3.6.2/fullcalendar.css',
			'libs/FullCalendar/3.6.2/locale-all.js',
			'libs/ext.cargo.calendar.css',
		),
		'scripts' => array(
			'libs/FullCalendar/3.6.2/fullcalendar.js',
			'libs/ext.cargo.calendar.js',
		),
		'dependencies' => array(
			'moment',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.timelinebase' => array(
		'scripts' => array(
			'libs/ext.cargo.timeline.js',
			'libs/SimileTimeline/scripts/timeline.js',
			'libs/SimileTimeline/scripts/util/platform.js',
			'libs/SimileTimeline/scripts/util/xmlhttp.js',
			'libs/SimileTimeline/scripts/util/data-structure.js',
			'libs/SimileTimeline/scripts/units.js',
			'libs/SimileTimeline/scripts/sources.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.timeline' => array(
		'styles' => array(
			'libs/SimileTimeline/styles/timeline.css',
			'libs/SimileTimeline/styles/ethers.css',
			'libs/SimileTimeline/styles/events.css',
		),
		'scripts' => array(
			'libs/SimileTimeline/scripts/util/debug.js',
			'libs/SimileTimeline/scripts/util/dom.js',
			'libs/SimileTimeline/scripts/util/graphics.js',
			'libs/SimileTimeline/scripts/util/date-time.js',
			'libs/SimileTimeline/scripts/themes.js',
			'libs/SimileTimeline/scripts/ethers.js',
			'libs/SimileTimeline/scripts/ether-painters.js',
			'libs/SimileTimeline/scripts/labellers.js',
			'libs/SimileTimeline/scripts/layouts.js',
			'libs/SimileTimeline/scripts/painters.js',
			'libs/SimileTimeline/scripts/decorators.js',
		),
		'dependencies' => 'ext.cargo.timelinebase',
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.datatables' => array(
		'styles' => array(
			'libs/DataTables/css/jquery.dataTables.css',
		),
		'scripts' => array(
			'libs/DataTables/js/jquery.dataTables.js',
			'libs/ext.cargo.datatables.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.nvd3' => array(
		'scripts' => array(
			'libs/d3.js',
			'libs/nv.d3.js',
			'libs/ext.cargo.nvd3.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.exhibit' => array(
		'scripts' => array(
			'libs/ext.cargo.exhibit.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
);

$wgCargoFieldTypes = array(
	'Page', 'String', 'Text', 'Integer', 'Float', 'Date', 'Datetime',
	'Boolean', 'Coordinates', 'Wikitext', 'Searchtext', 'File', 'URL',
	'Email'
);
$wgCargoAllowedSQLFunctions = array(
	// Math functions
	'COUNT', 'FLOOR', 'CEIL', 'ROUND',
	'MAX', 'MIN', 'AVG', 'SUM', 'POWER', 'LN', 'LOG',
	// String functions
	'CONCAT', 'GROUP_CONCAT', 'LOWER', 'LCASE', 'UPPER', 'UCASE',
	'SUBSTRING', 'FORMAT',
	// Date functions
	'NOW', 'DATE', 'YEAR', 'MONTH', 'DAYOFMONTH', 'DATE_FORMAT',
	'DATE_ADD', 'DATE_SUB', 'DATEDIFF',
	// @HACK - not a function, and shouldn't have to be here.
	'NEAR'
);

$wgCargoDecimalMark = '.';
$wgCargoDigitGroupingCharacter = ',';
$wgCargoRecurringEventMaxInstances = 100;
$wgCargoDBtype = null;
$wgCargoDBserver = null;
$wgCargoDBname = null;
$wgCargoDBuser = null;
$wgCargoDBpassword = null;
$wgCargoDefaultStringBytes = 300;
$wgCargoDefaultQueryLimit = 100;
$wgCargoMaxQueryLimit = 5000;
$wgCargo24HourTime = false;

$wgCargoDefaultMapService = 'OpenLayers';
$wgCargoGoogleMapsKey = null;
$wgCargoMapClusteringMinimum = 80;

$wgCargoDrilldownUseTabs = false;
// Set these to a positive number for cloud-style display.
$wgCargoDrilldownSmallestFontSize = -1;
$wgCargoDrilldownLargestFontSize = -1;
$wgCargoDrilldownMinValuesForComboBox = 40;
$wgCargoDrilldownNumRangesForNumbers = 5;
$wgCargoMaxVisibleHierarchyDrilldownValues = 30;

$wgCargoPageDataColumns = array();
$wgCargoFileDataColumns = array();
$wgCargoHideNamespaceName = array( NS_FILE );

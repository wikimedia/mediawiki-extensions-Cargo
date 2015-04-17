<?php
/**
 * Initialization file for Cargo.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

define( 'CARGO_VERSION', '0.6.1' );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Cargo',
	'version' => CARGO_VERSION,
	'author' => 'Yaron Koren',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Cargo',
	'descriptionmsg' => 'cargo-desc',
);

$dir = dirname( __FILE__ );

// Script path.
$cgScriptPath = $wgScriptPath . '/extensions/Cargo';

$wgJobClasses['cargoPopulateTable'] = 'CargoPopulateTableJob';

$wgHooks['ParserFirstCallInit'][] = 'cargoRegisterParserFunctions';
$wgHooks['MakeGlobalVariablesScript'][] = 'CargoHooks::setGlobalJSVariables';
$wgHooks['PageContentSaveComplete'][] = 'CargoHooks::onPageContentSaveComplete';
$wgHooks['ApprovedRevsRevisionApproved'][] = 'CargoHooks::onARRevisionApproved';
$wgHooks['ApprovedRevsRevisionUnapproved'][] = 'CargoHooks::onARRevisionUnapproved';
$wgHooks['TitleMoveComplete'][] = 'CargoHooks::onTitleMoveComplete';
$wgHooks['ArticleDeleteComplete'][] = 'CargoHooks::onArticleDeleteComplete';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CargoHooks::describeDBSchema';
// 'SkinTemplateNavigation' replaced 'SkinTemplateTabs' in the Vector skin
$wgHooks['SkinTemplateTabs'][] = 'CargoRecreateDataAction::displayTab';
$wgHooks['SkinTemplateNavigation'][] = 'CargoRecreateDataAction::displayTab2';
$wgHooks['UnknownAction'][] = 'CargoRecreateDataAction::show';
$wgHooks['BaseTemplateToolbox'][] = 'CargoPageValuesAction::addLink';
$wgHooks['UnknownAction'][] = 'CargoPageValuesAction::show';
$wgHooks['SkinTemplateNavigation'][] = 'CargoHooks::addPurgeCacheTab';
$wgHooks['AdminLinks'][] = 'CargoHooks::addToAdminLinks';
$wgHooks['PageSchemasRegisterHandlers'][] = 'CargoPageSchemas::registerClass';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'CargoHooks::onResourceLoaderGetConfigVars';

$wgMessagesDirs['Cargo'] = $dir . '/i18n';
$wgExtensionMessagesFiles['Cargo'] = $dir . '/Cargo.i18n.php';
$wgExtensionMessagesFiles['CargoMagic'] = $dir . '/Cargo.i18n.magic.php';

// API modules
$wgAPIModules['cargoquery'] = 'CargoQueryAPI';
$wgAPIModules['cargorecreatetables'] = 'CargoRecreateTablesAPI';
$wgAPIModules['cargorecreatedata'] = 'CargoRecreateDataAPI';

// Register classes and special pages.
$wgAutoloadClasses['CargoHooks'] = $dir . '/Cargo.hooks.php';
$wgAutoloadClasses['CargoUtils'] = $dir . '/CargoUtils.php';
$wgAutoloadClasses['CargoFieldDescription'] = $dir . '/CargoFieldDescription.php';
$wgAutoloadClasses['CargoTableSchema'] = $dir . '/CargoTableSchema.php';
$wgAutoloadClasses['CargoDeclare'] = $dir . '/parserfunctions/CargoDeclare.php';
$wgAutoloadClasses['CargoAttach'] = $dir . '/parserfunctions/CargoAttach.php';
$wgAutoloadClasses['CargoStore'] = $dir . '/parserfunctions/CargoStore.php';
$wgAutoloadClasses['CargoQuery'] = $dir . '/parserfunctions/CargoQuery.php';
$wgAutoloadClasses['CargoCompoundQuery'] = $dir . '/parserfunctions/CargoCompoundQuery.php';
$wgAutoloadClasses['CargoSQLQuery'] = $dir . '/CargoSQLQuery.php';
$wgAutoloadClasses['CargoQueryDisplayer'] = $dir . '/CargoQueryDisplayer.php';
$wgAutoloadClasses['CargoRecurringEvent'] = $dir . '/parserfunctions/CargoRecurringEvent.php';
$wgAutoloadClasses['CargoDisplayMap'] = $dir . '/parserfunctions/CargoDisplayMap.php';
$wgAutoloadClasses['CargoPopulateTableJob'] = $dir . '/CargoPopulateTableJob.php';
$wgAutoloadClasses['CargoRecreateDataAction'] = $dir . '/CargoRecreateDataAction.php';
$wgAutoloadClasses['CargoRecreateData'] = $dir . '/specials/CargoRecreateData.php';
$wgSpecialPages['CargoTables'] = 'CargoTables';
$wgAutoloadClasses['CargoTables'] = $dir . '/specials/CargoTables.php';
$wgSpecialPages['DeleteCargoTable'] = 'CargoDeleteCargoTable';
$wgAutoloadClasses['CargoDeleteCargoTable'] = $dir . '/specials/CargoDeleteTable.php';
$wgSpecialPages['ViewData'] = 'CargoViewData';
$wgAutoloadClasses['CargoViewData'] = $dir . '/specials/CargoViewData.php';
$wgAutoloadClasses['ViewDataPage'] = $dir . '/specials/CargoViewData.php';
$wgSpecialPages['CargoExport'] = 'CargoExport';
$wgAutoloadClasses['CargoExport'] = $dir . '/specials/CargoExport.php';
$wgAutoloadClasses['CargoPageValuesAction'] = $dir . '/CargoPageValuesAction.php';
$wgSpecialPages['PageValues'] = 'CargoPageValues';
$wgAutoloadClasses['CargoPageValues'] = $dir . '/specials/CargoPageValues.php';
$wgAutoloadClasses['CargoQueryAPI'] = $dir . '/api/CargoQueryAPI.php';
$wgAutoloadClasses['CargoRecreateTablesAPI'] = $dir . '/api/CargoRecreateTablesAPI.php';
$wgAutoloadClasses['CargoRecreateDataAPI'] = $dir . '/api/CargoRecreateDataAPI.php';

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

$wgAutoloadClasses['CargoPageSchemas'] = $dir . '/CargoPageSchemas.php';

// Drilldown
$wgAutoloadClasses['CargoAppliedFilter'] = $dir . '/drilldown/CargoAppliedFilter.php';
$wgAutoloadClasses['CargoFilter'] = $dir . '/drilldown/CargoFilter.php';
$wgAutoloadClasses['CargoFilterValue'] = $dir . '/drilldown/CargoFilterValue.php';
$wgAutoloadClasses['CargoDrilldownUtils'] = $dir . '/drilldown/CargoDrilldownUtils.php';
$wgAutoloadClasses['CargoDrilldown'] = $dir . '/drilldown/CargoSpecialDrilldown.php';
$wgAutoloadClasses['CargoDrilldownPage'] = $dir . '/drilldown/CargoSpecialDrilldown.php';
$wgSpecialPages['Drilldown'] = 'CargoDrilldown';

// User rights
$wgAvailableRights[] = 'recreatecargodata';
$wgGroupPermissions['sysop']['recreatecargodata'] = true;
$wgAvailableRights[] = 'deletecargodata';
$wgGroupPermissions['sysop']['deletecargodata'] = true;

// Page properties
$wgPageProps['CargoTableName'] = "The name of the database table that holds this template's data";
$wgPageProps['CargoFields'] = 'The set of fields stored for this template';

// ResourceLoader modules
$wgResourceModules += array(
	'ext.cargo.main' => array(
		'styles' => 'Cargo.css',
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
		'position' => 'top',
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.maps' => array(
		'scripts' => array(
			'libs/ext.cargo.maps.js',
			'libs/markerclusterer.js',
		),
		'position' => 'top',
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Cargo'
	),
	'ext.cargo.calendar' => array(
		'styles' => array(
			'libs/fullcalendar.css',
			'libs/ext.cargo.calendar.css',
		),
		'scripts' => array(
			'libs/moment.js',
			'libs/fullcalendar.js',
			'libs/ext.cargo.calendar.js',
		),
		'position' => 'top',
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
			'libs/ext.cargo.timeline.js',
			'libs/SimileTimeline/scripts/timeline.js',
			'libs/SimileTimeline/scripts/util/platform.js',
			'libs/SimileTimeline/scripts/util/debug.js',
			'libs/SimileTimeline/scripts/util/xmlhttp.js',
			'libs/SimileTimeline/scripts/util/dom.js',
			'libs/SimileTimeline/scripts/util/graphics.js',
			'libs/SimileTimeline/scripts/util/date-time.js',
			'libs/SimileTimeline/scripts/util/data-structure.js',
			'libs/SimileTimeline/scripts/units.js',
			'libs/SimileTimeline/scripts/themes.js',
			'libs/SimileTimeline/scripts/ethers.js',
			'libs/SimileTimeline/scripts/ether-painters.js',
			'libs/SimileTimeline/scripts/labellers.js',
			'libs/SimileTimeline/scripts/sources.js',
			'libs/SimileTimeline/scripts/layouts.js',
			'libs/SimileTimeline/scripts/painters.js',
			'libs/SimileTimeline/scripts/decorators.js',
		),
		'position' => 'top',
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
		'position' => 'top',
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
);

function cargoRegisterParserFunctions( &$parser ) {
	$parser->setFunctionHook( 'cargo_declare', array( 'CargoDeclare', 'run' ) );
	$parser->setFunctionHook( 'cargo_attach', array( 'CargoAttach', 'run' ) );
	$parser->setFunctionHook( 'cargo_store', array( 'CargoStore', 'run' ) );
	$parser->setFunctionHook( 'cargo_query', array( 'CargoQuery', 'run' ) );
	$parser->setFunctionHook( 'cargo_compound_query', array( 'CargoCompoundQuery', 'run' ) );
	$parser->setFunctionHook( 'recurring_event', array( 'CargoRecurringEvent', 'run' ) );
	$parser->setFunctionHook( 'cargo_display_map', array( 'CargoDisplayMap', 'run' ) );
	return true;
}

$wgCargoFieldTypes = array( 'Page', 'Text', 'Integer', 'Float', 'Date', 'Datetime', 'Boolean', 'Coordinates', 'Wikitext', 'File' );
$wgCargoDecimalMark = '.';
$wgCargoDigitGroupingCharacter = ',';
$wgCargoRecurringEventMaxInstances = 100;
$wgCargoDBtype = null;
$wgCargoDBserver = null;
$wgCargoDBname = null;
$wgCargoDBuser = null;
$wgCargoDBpassword = null;
$wgCargoDefaultQueryLimit = 100;
$wgCargoMaxQueryLimit = 5000;

$wgCargoMapClusteringMinimum = 80;

$wgCargoDrilldownUseTabs = false;
// Set these to a positive number for cloud-style display.
$wgCargoDrilldownSmallestFontSize = -1;
$wgCargoDrilldownLargestFontSize = -1;
$wgCargoDrilldownMinValuesForComboBox = 40;
$wgCargoDrilldownNumRangesForNumbers = 5;

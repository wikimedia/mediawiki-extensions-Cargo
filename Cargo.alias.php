<?php
/**
 * Aliases for the Cargo special pages.
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'CargoExport' => [ 'CargoExport' ],
	'CargoTables' => [ 'CargoTables' ],
	'DeleteCargoTable' => [ 'DeleteCargoTable' ],
	'Drilldown' => [ 'Drilldown' ],
	'CargoTableDiagram' => [ 'CargoTableDiagram' ],
	'PageValues' => [ 'PageValues' ],
	'CargoQuery' => [ 'CargoQuery', 'ViewData' ],
	'SwitchCargoTable' => [ 'SwitchCargoTable' ],
	'RecreateCargoData' => [ 'RecreateCargoData' ],
];

/** Simplified Chinese (中文（简体）) */
$specialPageAliases['zh-hans'] = [
	'CargoExport' => [ 'Cargo导出' ],
	'CargoTables' => [ 'Cargo表' ],
	'DeleteCargoTable' => [ '删除Cargo表' ],
	'Drilldown' => [ '深入分析' ],
	'CargoTableDiagram' => [ 'Cargo图表' ],
	'PageValues' => [ '页面值' ],
	'CargoQuery' => [ 'Cargo查询', '查看数据' ],
	'SwitchCargoTable' => [ '切换Cargo表' ],
	'RecreateCargoData' => [ '重新创建Cargo数据' ],
];

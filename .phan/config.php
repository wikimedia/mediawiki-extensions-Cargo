<?php
$config = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$config['suppress_issue_types'] = array_merge(
	$config['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		'PhanPossiblyUndeclaredVariable',
		'PhanTypeMismatchArgumentNullable',
		'PhanUndeclaredClassInstanceof',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredConstant',
		'PhanUndeclaredExtendedClass',
		'PhanUndeclaredMethod',
		'PhanUndeclaredTypeThrowsType',
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-SQLInjection',
		'SecurityCheck-XSS',
		// Required php8+
		'PhanUnusedVariableCaughtException',
	]
);

return $config;

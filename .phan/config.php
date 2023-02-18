<?php
$config = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Ensure Phan doesn't try to suggest PHP language features not supported
// by the minimum required PHP version of the minimum supported MediaWiki version (1.35).
$config['minimum_target_php_version'] = '7.3.19';

$config['suppress_issue_types'] = array_merge(
	$config['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		// This means that Phan initially won't do much, but it allows for
		// checks to be incrementally fixed and enabled without massive changes.
		'PhanImpossibleCondition',
		'PhanImpossibleTypeComparison',
		'PhanParamSignatureMismatch',
		'PhanPluginInvalidPregRegex',
		'PhanPluginLoopVariableReuse',
		'PhanPluginRedundantAssignment',
		'PhanPossiblyUndeclaredVariable',
		'PhanStaticCallToNonStatic',
		'PhanTypeArraySuspicious',
		'PhanTypeArraySuspiciousNullable',
		'PhanTypeInvalidLeftOperandOfAdd',
		'PhanTypeInvalidLeftOperandOfNumericOp',
		'PhanTypeMismatchArgument',
		'PhanTypeMismatchArgumentInternal',
		'PhanTypeMismatchArgumentNullable',
		'PhanTypeMismatchArgumentNullableInternal',
		'PhanTypeMismatchArgumentProbablyReal',
		'PhanTypeMismatchDimAssignment',
		'PhanTypeMismatchDimFetchNullable',
		'PhanTypeMissingReturn',
		'PhanTypePossiblyInvalidDimOffset',
		'PhanUndeclaredClassInstanceof',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredConstant',
		'PhanUndeclaredExtendedClass',
		'PhanUndeclaredMethod',
		'PhanUndeclaredStaticMethod',
		'PhanUndeclaredTypeThrowsType',
		'PhanUndeclaredVariable',
		'PhanUndeclaredVariableAssignOp',
		'PhanUndeclaredVariableDim',
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-SQLInjection',
		'SecurityCheck-XSS',
	]
);

return $config;

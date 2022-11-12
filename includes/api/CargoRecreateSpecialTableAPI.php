<?php
/**
 * Adds and handles the 'cargorecreatespecialtable' action to the MediaWiki API.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoRecreateSpecialTableAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'recreatecargodata' ) || $user->getBlock() !== null ) {
			$this->dieWithError( [ 'badaccess-groups' ] );
		}

		$params = $this->extractRequestParams();
		$tableStr = $params['table'];
		if ( $tableStr == '' ) {
			$this->dieWithError( 'The table must be specified', 'param_substr' );
		}
		$createReplacement = $params['createReplacement'];

		$tableName = $createReplacement ? $tableStr . '__NEXT' : $tableStr;
		if ( $tableStr == '_pageData' ) {
				$tableSchema = CargoPageData::getTableSchema();
		} elseif ( $tableStr == '_fileData' ) {
				$tableSchema = CargoFileData::getTableSchema();
		} elseif ( $tableStr == '_bpmnData' ) {
				$tableSchema = CargoBPMNData::getTableSchema();
		} elseif ( $tableStr == '_ganttData' ) {
				$tableSchema = CargoGanttData::getTableSchema();
		} else {
			$this->dieWithError( 'Invalid table name', 'param_substr' );
		}
		$tableSchemaString = $tableSchema->toDBString();
		$cdb = CargoUtils::getDB();
		$dbw = wfGetDB( DB_MASTER );
		$success = CargoUtils::createCargoTableOrTables(
			$cdb, $dbw, $tableName, $tableSchema, $tableSchemaString, -1
		);

		// Set top-level elements.
		$result = $this->getResult();
		$result->addValue( null, 'success', true );

		CargoUtils::logTableAction( 'recreatetable', $tableStr, $user );
	}

	protected function getAllowedParams() {
		return [
			'table' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'createReplacement' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
		];
	}

	protected function getParamDescription() {
		return [
			'table' => 'The special table to be recreated',
			'createReplacement' => 'Whether to put data into a replacement table',
		];
	}

	protected function getDescription() {
		return 'An API module to recreate tables for the Cargo extension '
			. '(https://www.mediawiki.org/Extension:Cargo)';
	}

	protected function getExamples() {
		return [
			'api.php?action=cargorecreatespecialtable&table=_pageData'
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

}

<?php
/**
 * Adds the 'cargofields' action to the MediaWiki API
 *
 * @ingroup Cargo
 * @author Sanjay Thiyagarajan
 *
 */
class CargoFieldsAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$tableName = $params['table'];

		if ( $tableName == '' ) {
			$this->dieWithError( 'The table name must be specified', 'param_substr' );
		}

		try {
			$queryResults = CargoUtils::getTableSchemas( [ $tableName ] )[ $tableName ]->mFieldDescriptions;
		} catch ( Exception $e ) {
			$this->dieWithError( $e->getMessage(), 'db_error' );
		}

		// Format data as the API requires it.
		$formattedData = [];
		foreach ( $queryResults as $fieldName => $fieldDescription ) {
			$formattedData[ $fieldName ] = $fieldDescription->toDBArray();
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $formattedData, 'p' );
		$result->addValue( null, $this->getModuleName(), $formattedData );
	}

	protected function getAllowedParams() {
		return [
			'table' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	protected function getDescription() {
		return 'Fetches all the fields in the specified table';
	}

	protected function getParamDescription() {
		return [
			'table' => 'The Cargo database table on which to search'
		];
	}

}

<?php
/**
 * Adds the 'cargotable' action to the MediaWiki API
 *
 * @ingroup Cargo
 * @author Sanjay Thiyagarajan
 *
 */
class CargoTablesAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		try {
			$queryResults = CargoUtils::getTables();
		} catch ( Exception $e ) {
			$this->dieWithError( $e, 'db_error' );
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $queryResults, 'p' );
		$result->addValue( null, $this->getModuleName(), $queryResults );
	}

	protected function getDescription() {
		return 'Fetches all the tables in the system';
	}

}

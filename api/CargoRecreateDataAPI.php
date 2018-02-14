<?php
/**
 * Adds and handles the 'cargorecreatedata' action to the MediaWiki API.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoRecreateDataAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		global $wgUser;

		if ( !$wgUser->isAllowed( 'recreatecargodata' ) || $wgUser->isBlocked() ) {
			CargoUtils::dieWithError( $this, array( 'badaccess-groups' ) );
		}

		$params = $this->extractRequestParams();
		$templateStr = $params['template'];
		$tableStr = $params['table'];

		if ( $templateStr == '' ) {
			CargoUtils::dieWithError( $this, 'The template must be specified', 'param_substr' );
		}

		if ( $tableStr == '' ) {
			CargoUtils::dieWithError( $this, 'The table must be specified', 'param_substr' );
		}

		// Create the jobs.
		$jobParams = array(
			'dbTableName' => $tableStr,
			'replaceOldRows' => $params['replaceOldRows']
		);
		$jobs = array();
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateStr );
		$titlesWithThisTemplate = $templateTitle->getTemplateLinksTo( array(
			'LIMIT' => 500, 'OFFSET' => $params['offset'] ) );
		foreach ( $titlesWithThisTemplate as $titleWithThisTemplate ) {
			$jobs[] = new CargoPopulateTableJob( $titleWithThisTemplate, $jobParams );
		}
		JobQueueGroup::singleton()->push( $jobs );

		// Set top-level elements.
		$result = $this->getResult();
		$result->addValue( null, 'success', true );
	}

	protected function getAllowedParams() {
		return array(
			'template' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'table' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'offset' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DFLT => 0,
			),
			'replaceOldRows' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
		);
	}

	protected function getParamDescription() {
		return array(
			'template' => 'The template whose data to use',
			'table' => 'The Cargo database table to repopulate',
			'offset' => 'Of the pages that call this template, the number at which to start querying',
			'replaceOldRows' => 'Whether to replace old rows for each page while repopulating the table',
		);
	}

	protected function getDescription() {
		return 'An API module to recreate data for the Cargo extension '
			. '(http://www.mediawiki.org/Extension:Cargo)';
	}

	protected function getExamples() {
		return array(
			'api.php?action=cargorecreatedata&template=City&table=Cities'
		);
	}

}

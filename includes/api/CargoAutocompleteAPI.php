<?php
/**
 * @file
 * @ingroup Cargo
 */

use MediaWiki\Language\RawMessage;

/**
 * Adds the 'cargoautocomplete' action to the MediaWiki API.
 *
 * @ingroup Cargo
 *
 * @author Yaron Koren
 */
class CargoAutocompleteAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$substr = $params['substr'];
		$table = $params['table'];
		$field = $params['field'];
		$where = $params['where'];

		if ( $table == '' ) {
			$this->dieWithError( '"table" value must be specified', 'param_table' );
		}

		if ( $field == '' ) {
			$this->dieWithError( '"field" value must be specified', 'param_field' );
		}

		$data = self::getAllValues( $table, $field, $where, $substr );

		// If we got back an error message, exit with that message.
		if ( !is_array( $data ) ) {
			if ( !$data instanceof Message ) {
				$data = ApiMessage::create( new RawMessage( '$1', [ $data ] ), 'unknownerror' );
			}
			$this->dieWithError( $data );
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'p' );
		$result->addValue( null, $this->getModuleName(), $data );
	}

	protected function getAllowedParams() {
		return [
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'substr' => null,
			'table' => null,
			'field' => null,
			'where' => null,
		];
	}

	protected function getParamDescription() {
		return [
			'substr' => 'Search substring',
			'table' => 'Cargo table for which to search values',
			'field' => 'Cargo field for which to search values',
			'where' => 'The "where" clause for the query, based on the other filters specified',
		];
	}

	protected function getDescription() {
		return 'Autocompletion call used by Special:Drilldown, defined by the Cargo extension (https://www.mediawiki.org/Extension:Cargo)';
	}

	protected function getExamples() {
		return [
			'api.php?action=cargoautocomplete&table=Books&field=Author&substr=jo',
			'api.php?action=cargoautocomplete&table=Books&field=Author&where=Genre=comedy+AND+Year_published=1986&substr=jo',
		];
	}

	private static function getAllValues( $table, $field, $where, $substr ) {
		$values = [];

		if ( $substr !== null ) {
			if ( $where != '' ) {
				$where .= " AND ";
			}
			// There's no such global variable at the moment -
			// perhaps there will be in the future.
			// if ( $wgCargoDrilldownAutocompleteOnAllChars ) {
			// $where .= "($field LIKE \"%$substr%\")";
			// } else {
				$where .= "($field LIKE \"$substr%\" OR $field LIKE \"% $substr%\")";
			// }
		}

		$sqlQuery = CargoSQLQuery::newFromValues(
			$table,
			$field,
			$where,
			$joinOn = null,
			$field,
			$having = null,
			$field,
			20,
			$offset = null
		);
		if ( $field[0] != '_' ) {
			$fieldAlias = str_replace( '_', ' ', $field );
		} else {
			$fieldAlias = $field;
		}
		$queryResults = $sqlQuery->run();

		foreach ( $queryResults as $row ) {
			// @TODO - this check should not be necessary.
			$value = $row[$fieldAlias];
			if ( $value != '' ) {
				$values[] = $value;
			}
		}

		return $values;
	}

}

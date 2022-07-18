<?php
/**
 * Displays a sort of "database diagram" showing the relationships between
 * Cargo tables.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTableDiagram extends IncludableSpecialPage {
	public function __construct() {
		parent::__construct( 'CargoTableDiagram' );
	}

	public function execute( $subpage = null ) {
		$out = $this->getOutput();

		$this->setHeaders();

		$out->addModules( 'ext.cargo.diagram' );

		$tableNames = CargoUtils::getTables();
		$userDefinedTables = [];
		foreach ( $tableNames as $tableName ) {
			if ( substr( $tableName, 0, 1 ) !== '_' ) {
				$userDefinedTables[] = $tableName;
			}
		}

		// Create a minimal array of schema data, since we only need
		// a small fraction of the overall schema information.
		$tableSchemas = CargoUtils::getTableSchemas( $userDefinedTables );
		$tableSchemaData = [];
		foreach ( $tableSchemas as $tableName => $tableSchema ) {
			$curTableSchemaData = [];
			foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDesc ) {
				$typeString = $fieldDesc->mType;
				if ( $fieldDesc->mIsList ) {
					// @todo - i18n this?
					$typeString = "List of $typeString";
				}
				$curTableSchemaData[$fieldName] = [
					'type' => $typeString
				];
			}
			$tableSchemaData[$tableName] = $curTableSchemaData;
		}
		$tableSchemasJSON = json_encode( $tableSchemaData );

		$allParentTables = [];
		foreach ( $userDefinedTables as $tableName ) {
			$parentTables = CargoUtils::getParentTables( $tableName );
			if ( is_array( $parentTables ) && count( $parentTables ) > 0 ) {
				$allParentTables[$tableName] = $parentTables;
			}
		}
		$allParentTablesJSON = json_encode( $allParentTables );

		$text = "<div class=\"cargo-table-diagram\" data-table-schemas='$tableSchemasJSON' data-parent-tables='$allParentTablesJSON'><svg class=\"cargo-table-svg\"></svg></div>";

		$out->addHTML( $text );

		return true;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}

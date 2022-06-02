<?php
/**
 * Displays an interface to let users recreate data via the Cargo
 * extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoPageValues extends IncludableSpecialPage {
	public $mTitle;

	public function __construct( $title = null ) {
		parent::__construct( 'PageValues' );

		$this->mTitle = $title;
	}

	public function execute( $subpage = null ) {
		global $wgCargoPageDataColumns, $wgCargoFileDataColumns;

		if ( $subpage ) {
			// Allow inclusion with e.g. {{Special:PageValues/Book}}
			$this->mTitle = Title::newFromText( $subpage );
		}

		// If no title, or a nonexistent title, was set, just exit out.
		// @TODO - display an error message.
		if ( $this->mTitle == null || !$this->mTitle->exists() ) {
			return true;
		}

		$out = $this->getOutput();

		$this->setHeaders();

		$pageName = $this->mTitle->getPrefixedText();
		$out->setPageTitle( $this->msg( 'cargo-pagevaluesfor', $pageName )->text() );

		$text = '';

		$dbr = wfGetDB( DB_REPLICA );

		$tableNames = [];

		// Make _pageData and _fileData the first two tables, if
		// either of them hold any real data.
		if ( count( $wgCargoPageDataColumns ) > 0 ) {
			$tableNames[] = '_pageData';
		}
		if ( count( $wgCargoFileDataColumns ) > 0 ) {
			$tableNames[] = '_fileData';
		}

		$res = $dbr->select(
			'cargo_pages', 'table_name',
			[ 'page_id' => $this->mTitle->getArticleID() ]
		);
		foreach ( $res as $row ) {
			$tableNames[] = $row->table_name;
		}

		$toc = Linker::tocIndent();
		$tocLength = 0;

		foreach ( $tableNames as $tableName ) {
			try {
				$queryResults = $this->getRowsForPageInTable( $tableName );
			} catch ( Exception $e ) {
				// Most likely this is because the _pageData
				// table doesn't exist.
				continue;
			}
			$numRowsOnPage = count( $queryResults );

			// Hide _fileData if it's empty - we do this only for _fileData,
			// as another table having 0 rows can indicate an error, and we'd
			// like to preserve that information for debugging purposes.
			if ( $numRowsOnPage === 0 && $tableName === '_fileData' ) {
				continue;
			}

			$tableLink = $this->getTableLink( $tableName );

			$tableSectionHeader = $this->msg( 'cargo-pagevalues-tablevalues', $tableLink )->text();
			$tableSectionTocDisplay = $this->msg( 'cargo-pagevalues-tablevalues', $tableName )->text();
			$tableSectionAnchor = $this->msg( 'cargo-pagevalues-tablevalues', $tableName )->escaped();
			$tableSectionAnchor = Sanitizer::escapeIdForAttribute( $tableSectionAnchor );

			// We construct the table of contents at the same time
			// as the main text.
			$toc .= Linker::tocLine( $tableSectionAnchor, $tableSectionTocDisplay,
				$this->getLanguage()->formatNum( ++$tocLength ), 1 ) . Linker::tocLineEnd();

			$h2 = Html::rawElement( 'h2', null,
				Html::rawElement( 'span', [ 'class' => 'mw-headline', 'id' => $tableSectionAnchor ], $tableSectionHeader ) );

			$text .= Html::rawElement( 'div', [ 'class' => 'cargo-pagevalues-tableinfo' ],
				$h2 . $this->msg( "cargo-pagevalues-tableinfo-numrows", $numRowsOnPage )
			);

			foreach ( $queryResults as $rowValues ) {
				$tableContents = '';
				$fieldInfo = $this->getInfoForAllFields( $tableName );
				$anyFieldHasAllowedValues = false;
				foreach ( $fieldInfo as $field => $info ) {
					if ( $info['allowed values'] !== '' ) {
						$anyFieldHasAllowedValues = true;
					}
				}
				foreach ( $rowValues as $field => $value ) {
					// @HACK - this check should ideally
					// be done earlier.
					if ( strpos( $field, '__precision' ) !== false ) {
						continue;
					}
					$tableContents .= $this->printRow( $field, $value, $fieldInfo[$field], $anyFieldHasAllowedValues );
				}
				$text .= $this->printTable( $tableContents, $anyFieldHasAllowedValues );
			}
		}

		// Show table of contents only if there are enough sections.
		if ( count( $tableNames ) >= 3 ) {
			$toc = Linker::tocList( $toc );
			$out->addHTML( $toc );
		}

		$out->addHTML( $text );
		$out->addModules( 'ext.cargo.pagevalues' );

		return true;
	}

	private function getTableLink( $tableName ) {
		$originalTableName = str_replace( '__NEXT', '', $tableName );
		$isReplacementTable = substr( $tableName, -6 ) == '__NEXT';
		$viewURL = SpecialPage::getTitleFor( 'CargoTables' )->getFullURL() . "/$originalTableName";
		if ( $isReplacementTable ) {
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
		}

		return Html::element( 'a', [ 'href' => $viewURL ], $tableName );
	}

	/**
	 * Used to get the information about field type and the list
	 * of allowed values (if any) of all fields of a table.
	 *
	 * @param string $tableName
	 */
	private function getInfoForAllFields( $tableName ) {
		$tableSchemas = CargoUtils::getTableSchemas( [ $tableName ] );
		if ( $tableName == '_pageData' ) {
			CargoUtils::addGlobalFieldsToSchema( $tableSchemas[$tableName] );
		}
		$fieldDescriptions = $tableSchemas[$tableName]->mFieldDescriptions;
		$fieldInfo = [];
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldInfo[$fieldName]['field type'] = $fieldDescription->prettyPrintType();
			$delimiter = strlen( $fieldDescription->getDelimiter() ) ? $fieldDescription->getDelimiter() : ',';
			if ( is_array( $fieldDescription->mAllowedValues ) ) {
				$fieldInfo[$fieldName]['allowed values'] = implode( ' &middot; ', $fieldDescription->mAllowedValues );
			} else {
				$fieldInfo[$fieldName]['allowed values'] = '';
			}
		}
		return $fieldInfo;
	}

	public function getRowsForPageInTable( $tableName ) {
		$cdb = CargoUtils::getDB();

		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mAliasedTableNames = [ $tableName => $tableName ];

		$tableSchemas = CargoUtils::getTableSchemas( [ $tableName ] );

		if ( $tableName == '_pageData' ) {
			CargoUtils::addGlobalFieldsToSchema( $tableSchemas[$tableName] );
		}

		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = [];
		foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( $fieldDescription->mIsHidden ) {
				// @TODO - do some custom formatting
			}

			// $fieldAlias = str_replace( '_', ' ', $fieldName );
			$fieldAlias = $fieldName;

			if ( $fieldDescription->mIsList ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} elseif ( $fieldDescription->mType == 'Coordinates' ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} else {
				$aliasedFieldNames[$fieldAlias] = $fieldName;
			}
		}

		$sqlQuery->mAliasedFieldNames = $aliasedFieldNames;
		$sqlQuery->mOrigAliasedFieldNames = $aliasedFieldNames;
		$sqlQuery->setDescriptionsAndTableNamesForFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->mWhereStr = $cdb->addIdentifierQuotes( '_pageID' ) . " = " .
			$this->mTitle->getArticleID();

		$queryResults = $sqlQuery->run();
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );
		return $formattedQueryResults;
	}

	/**
	 * Based on MediaWiki's InfoAction::addRow()
	 */
	public function printRow( $name, $value, $fieldInfo, $fieldHasAnyAllowedValues ) {
		if ( $name == '_fullText' && strlen( $value ) > 300 ) {
			$value = substr( $value, 0, 300 ) . ' ...';
		}
		$text = Html::element( 'td', [ 'class' => 'cargo-pagevalues-table-field' ], $name ) .
			Html::rawElement( 'td', [ 'class' => 'cargo-pagevalues-table-type' ], $fieldInfo['field type'] );
		if ( $fieldHasAnyAllowedValues ) {
			$allowedValuesText = $fieldInfo['allowed values'];
			// Count "middot" as only one character, not eight, when counting the string length.
			$allowedValuesDisplayText = str_replace( '&middot;', '.', $allowedValuesText );
			if ( strlen( $allowedValuesDisplayText ) > 25 ) {
				$allowedValuesText = '<span class="cargoMinimizedText">' . $fieldInfo['allowed values'] . '</span>';
			}
			$text .= Html::rawElement( 'td', [ 'class' => 'cargo-pagevalues-table-allowedvalues' ], $allowedValuesText );
		}
		$text .= Html::rawElement( 'td', [ 'class' => 'cargo-pagevalues-table-value' ], $value );

		return Html::rawElement( 'tr', [], $text );
	}

	/**
	 * Based on MediaWiki's InfoAction::addTable()
	 */
	public function printTable( $tableContents, $anyFieldHasAllowedValues ) {
		$headerRow = '<tr><th>Field</th><th>' . $this->msg( 'cargo-field-type' )->text() . '</th>';
		if ( $anyFieldHasAllowedValues ) {
			$headerRow .= '<th>' . $this->msg( 'cargo-allowed-values' )->text() . '</th>';
		}
		$headerRow .= '<th>Value</th></tr>';
		return Html::rawElement( 'table', [ 'class' => 'wikitable mw-page-info' ],
			$headerRow . $tableContents ) . "\n";
	}

	/**
	 * Don't list this in Special:SpecialPages.
	 */
	public function isListed() {
		return false;
	}
}

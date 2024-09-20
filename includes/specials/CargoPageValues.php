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

		$tableNames = [];

		$cdb = CargoUtils::getDB();
		if ( $cdb->tableExists( '_pageData__NEXT', __METHOD__ ) ) {
			$tableNames[] = '_pageData__NEXT';
		} elseif ( $cdb->tableExists( '_pageData', __METHOD__ ) ) {
			$tableNames[] = '_pageData';
		}
		if ( $cdb->tableExists( '_fileData__NEXT', __METHOD__ ) ) {
			$tableNames[] = '_fileData__NEXT';
		} elseif ( $cdb->tableExists( '_fileData', __METHOD__ ) ) {
			$tableNames[] = '_fileData';
		}

		$dbr = CargoUtils::getMainDBForRead();
		$res = $dbr->select(
			'cargo_pages', 'table_name',
			[ 'page_id' => $this->mTitle->getArticleID() ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$tableNames[] = $row->table_name;
		}

		$toc = self::tocIndent();
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
			if ( $numRowsOnPage === 0 && ( $tableName === '_fileData' || $tableName === '_fileData__NEXT' ) ) {
				continue;
			}

			$tableLink = $this->getTableLink( $tableName );

			$tableSectionHeader = $this->msg( 'cargo-pagevalues-tablevalues' )->rawParams( $tableLink )->escaped();
			$tableSectionTocDisplay = $this->msg( 'cargo-pagevalues-tablevalues', $tableName )->escaped();
			$tableSectionAnchor = $this->msg( 'cargo-pagevalues-tablevalues', $tableName )->escaped();
			$tableSectionAnchor = Sanitizer::escapeIdForAttribute( $tableSectionAnchor );

			// We construct the table of contents at the same time
			// as the main text.
			$toc .= self::tocLine( $tableSectionAnchor, $tableSectionTocDisplay,
				$this->getLanguage()->formatNum( ++$tocLength ), 1 ) . self::tocLineEnd();

			$h2 = Html::rawElement( 'h2', null,
				Html::rawElement( 'span', [ 'class' => 'mw-headline', 'id' => $tableSectionAnchor ], $tableSectionHeader ) );

			$text .= Html::rawElement( 'div', [ 'class' => 'cargo-pagevalues-tableinfo' ],
				$h2 . $this->msg( "cargo-pagevalues-tableinfo-numrows", $numRowsOnPage )
			);

			foreach ( $queryResults as $rowValues ) {
				$tableContents = '';
				$fieldInfo = $this->getInfoForAllFields( $tableName );
				$anyFieldHasAllowedValues = false;
				foreach ( $fieldInfo as $info ) {
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
			$toc = self::tocList( $toc );
			$out->addHTML( $toc );
		}

		$out->addHTML( $text );
		$out->addModules( 'ext.cargo.main' );
		$out->addModuleStyles( 'ext.cargo.pagevalues' );

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
		if ( $tableName == '_pageData' || $tableName == '_pageData__NEXT' ) {
			CargoUtils::addGlobalFieldsToSchema( $tableSchemas[$tableName] );
		}
		$fieldDescriptions = $tableSchemas[$tableName]->mFieldDescriptions;
		$fieldInfo = [];
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldInfo[$fieldName]['field type'] = $fieldDescription->prettyPrintType();
			if ( is_array( $fieldDescription->mAllowedValues ) ) {
				$fieldInfo[$fieldName]['allowed values'] = $fieldDescription->prettyPrintAllowedValues();
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

		if ( $tableName == '_pageData' || $tableName == '_pageData__NEXT' ) {
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
		$headerRow = Html::element( 'th', null, $this->msg( 'cargo-field' )->text() ) .
			Html::element( 'th', null, $this->msg( 'cargo-field-type' )->text() );
		if ( $anyFieldHasAllowedValues ) {
			$headerRow .= Html::element( 'th', null, $this->msg( 'cargo-allowed-values' )->text() );
		}
		$headerRow .= Html::element( 'th', null, $this->msg( 'cargo-value' )->text() );
		$headerRow = Html::rawElement( 'tr', null, $headerRow );
		return Html::rawElement( 'table', [ 'class' => 'wikitable mw-page-info' ],
			$headerRow . $tableContents ) . "\n";
	}

	/**
	 * Add another level to the Table of Contents
	 *
	 * Copied from HandleTOCMarkers::tocIndent(), which is unfortunately private.
	 *
	 * @return string
	 */
	private static function tocIndent() {
		return "\n<ul>\n";
	}

	/**
	 * parameter level defines if we are on an indentation level
	 *
	 * Copied from HandleTOCMarkers::tocLine(), which is unfortunately private.
	 *
	 * @param string $linkAnchor Identifier
	 * @param string $tocline Properly escaped HTML
	 * @param string $tocnumber Unescaped text
	 * @param int $level
	 * @param string|false $sectionIndex
	 * @return string
	 */
	private static function tocLine( $linkAnchor, $tocline, $tocnumber, $level, $sectionIndex = false ) {
		$classes = "toclevel-$level";
		// Parser.php used to suppress tocLine by setting $sectionindex to false.
		// In those circumstances, we can now encounter '' or a "T-" prefixed index
		// for when the section comes from templates.
		if ( $sectionIndex !== false && $sectionIndex !== '' && !str_starts_with( $sectionIndex, "T-" ) ) {
			$classes .= " tocsection-$sectionIndex";
		}
		// <li class="$classes"><a href="#$linkAnchor"><span class="tocnumber">
		// $tocnumber</span> <span class="toctext">$tocline</span></a>
		return Html::openElement( 'li', [ 'class' => $classes ] )
			. Html::rawElement( 'a',
				[ 'href' => "#$linkAnchor" ],
				Html::element( 'span', [ 'class' => 'tocnumber' ], $tocnumber )
					. ' '
					. Html::rawElement( 'span', [ 'class' => 'toctext' ], $tocline )
			);
	}

	/**
	 * End a Table Of Contents line.
	 * tocUnindent() will be used instead if we're ending a line below
	 * the new level.
	 *
	 * Copied from HandleTOCMarkers::tocLineEnd(), which is unfortunately private.
	 *
	 * @return string
	 */
	private static function tocLineEnd() {
		return "</li>\n";
	}

	/**
	 * Wraps the TOC in a div with ARIA navigation role and provides the hide/collapse JavaScript.
	 *
	 * Copied from HandleTOCMarkers::tocList(), which is unfortunately private.
	 *
	 * @param string $toc Html of the Table Of Contents
	 * @param Language|null $lang Language for the toc title, defaults to user language
	 * @return string Full html of the TOC
	 */
	private static function tocList( $toc, Language $lang = null ) {
		$lang ??= RequestContext::getMain()->getLanguage();
		$title = wfMessage( 'toc' )->inLanguage( $lang )->escaped();
		return '<div id="toc" class="toc" role="navigation" aria-labelledby="mw-toc-heading">'
			. Html::element( 'input', [
				'type' => 'checkbox',
				'role' => 'button',
				'id' => 'toctogglecheckbox',
				'class' => 'toctogglecheckbox',
				'style' => 'display:none',
			] )
			. Html::openElement( 'div', [
				'class' => 'toctitle',
				'lang' => $lang->getHtmlCode(),
				'dir' => $lang->getDir(),
			] )
			. '<h2 id="mw-toc-heading">' . $title . '</h2>'
			. '<span class="toctogglespan">'
			. Html::label( '', 'toctogglecheckbox', [
				'class' => 'toctogglelabel',
			] )
			. '</span>'
			. '</div>'
			. $toc
			. "</ul>\n</div>\n";
	}

	/**
	 * Don't list this in Special:SpecialPages.
	 */
	public function isListed() {
		return false;
	}
}

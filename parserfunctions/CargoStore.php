<?php
/**
 * Class for the #cargo_store function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoStore {

	public static $settings = array();

	const DATE_AND_TIME = 0;
	const DATE_ONLY = 1;
	const MONTH_ONLY = 2;
	const YEAR_ONLY = 3;

	/**
	 * Handles the #cargo_set parser function - saves data for one
	 * template call.
	 *
	 * @global string $wgCargoDigitGroupingCharacter
	 * @global string $wgCargoDecimalMark
	 * @param Parser $parser
	 * @throws MWException
	 */
	public static function run( &$parser ) {
		// This function does actual DB modifications - so only proceed
		// is this is called via either a page save or a "recreate
		// data" action for a template that this page calls.
		if ( count( self::$settings ) == 0 ) {
			wfDebugLog( 'cargo', "CargoStore::run() - skipping.\n" );
			return;
		} elseif ( !array_key_exists( 'origin', self::$settings ) ) {
			wfDebugLog( 'cargo', "CargoStore::run() - skipping 2.\n" );
			return;
		}

		// Get page-related information early on, so we can exit
		// quickly if there's a problem.
		$title = $parser->getTitle();
		$pageName = $title->getPrefixedText();
		$pageTitle = $title->getText();
		$pageNamespace = $title->getNamespace();
		$pageID = $title->getArticleID();
		if ( $pageID <= 0 ) {
			// This will most likely happen if the title is a
			// "special" page.
			wfDebugLog( 'cargo', "CargoStore::run() - skipping 3.\n" );
			return;
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		$tableFieldValues = array();

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
			} else {
				$fieldName = $key;
				// Since we don't know whether any empty
				// value is meant to be blank or null, let's
				// go with null.
				if ( $value == '' ) {
					$value = null;
				}
				$fieldValue = $value;
				$tableFieldValues[$fieldName] = $fieldValue;
			}
		}

		if ( $tableName == '' ) {
			return;
		}

		if ( self::$settings['origin'] == 'template' ) {
			// It came from a template "recreate data" action -
			// make sure it passes various criteria.
			if ( self::$settings['dbTableName'] != $tableName ) {
				wfDebugLog( 'cargo', "CargoStore::run() - skipping 4.\n" );
				return;
			}
		}

		// Get the declaration of the table.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', 'table_schema', array( 'main_table' => $tableName ) );
		$row = $dbr->fetchRow( $res );
		if ( $row == '' ) {
			// This table probably has not been created yet -
			// just exit silently.
			wfDebugLog( 'cargo', "CargoStore::run() - skipping 5.\n" );
			return;
		}
		$tableSchema = CargoTableSchema::newFromDBString( $row['table_schema'] );

		foreach ( $tableFieldValues as $fieldName => $fieldValue ) {
			if ( !array_key_exists( $fieldName, $tableSchema->mFieldDescriptions ) ) {
				throw new MWException( "Error: Unknown Cargo field, \"$fieldName\"." );
			}
		}

		// We're still here! Let's add to the DB table(s).
		// First, though, let's do some processing:
		// - remove invalid values, if any
		// - put dates and numbers into correct format
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// If it's null or not set, skip this value.
			if ( !array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			$curValue = $tableFieldValues[$fieldName];
			if ( is_null( $curValue ) ) {
				continue;
			}

			// Change from the format stored in the DB to the
			// "real" one.
			$fieldType = $fieldDescription->mType;
			if ( $fieldDescription->mAllowedValues != null ) {
				$allowedValues = $fieldDescription->mAllowedValues;
				if ( $fieldDescription->mIsList ) {
					$delimiter = $fieldDescription->mDelimiter;
					$individualValues = explode( $delimiter, $curValue );
					$valuesToBeKept = array();
					foreach ( $individualValues as $individualValue ) {
						$realIndividualVal = trim( $individualValue );
						if ( in_array( $realIndividualVal, $allowedValues ) ) {
							$valuesToBeKept[] = $realIndividualVal;
						}
					}
					$tableFieldValues[$fieldName] = implode( $delimiter, $valuesToBeKept );
				} else {
					if ( !in_array( $curValue, $allowedValues ) ) {
						$tableFieldValues[$fieldName] = null;
					}
				}
			}
			if ( $fieldType == 'Date' || $fieldType == 'Datetime' ) {
				$precision = null;
				if ( $curValue == '' ) {
					continue;
				}

				// Special handling if it's just a year. If
				// it's a number and less than 8 digits, assume
				// it's a year (hey, it could be a very large
				// BC year). If it's 8 digits, it's probably a
				// full date in the form YYYYMMDD.
				if ( ctype_digit( $curValue ) && strlen( $curValue ) < 8 ) {
					// Add a fake date - it will get
					// ignored later.
					$curValue = "$curValue-01-01";
					$precision = self::YEAR_ONLY;
				} else {
					// Determine if there's a month but no
					// day. There's no ideal way to do
					// this, so: we'll just look for the
					// total number of spaces, slashes and
					// dashes, and if there's exactly one
					// altogether, we'll/ guess that it's a
					// month only.
					$numSpecialChars = substr_count( $curValue, ' ' ) +
						substr_count( $curValue, '/' ) + substr_count( $curValue, '-' );
					if ( $numSpecialChars == 1 ) {
						// No need to add anything -
						// PHP will set it to the first
						// of the month.
						$precision = self::MONTH_ONLY;
					} else {
						// We have at least a full date.
						if ( $fieldType == 'Date' ) {
							$precision = self::DATE_ONLY;
						}
					}
				}

				$seconds = strtotime( $curValue );
				// If the precision has already been set, then
				// we know it doesn't include a time value -
				// we can set the value already.
				if ( $precision != null ) {
					// Put into YYYY-MM-DD format.
					$tableFieldValues[$fieldName] = date( 'Y-m-d', $seconds );
				} else {
					// It's a Datetime field, which
					// may or may not have a time -
					// check for that now.
					$datePortion = date( 'Y-m-d', $seconds );
					$timePortion = date( 'G:i:s', $seconds );
					// If it's not right at midnight,
					// there's definitely a time there.
					$precision = self::DATE_AND_TIME;
					if ( $timePortion !== '0:00:00' ) {
						$tableFieldValues[$fieldName] = $datePortion . ' ' . $timePortion;
					} else {
						// It's midnight, so chances
						// are good that there was no
						// time specified, but how do
						// we know for sure?
						// Slight @HACK - look for
						// either "00" or "AM" (or "am")
						// in the original date string.
						// If neither one is there,
						// there's probably no time.
						if ( strpos( $curValue, '00' ) === false &&
							strpos( $curValue, 'AM' ) === false &&
							strpos( $curValue, 'am' ) === false ) {
							$precision = self::DATE_ONLY;
						}
						// Either way, we just
						// need the date portion.
						$tableFieldValues[$fieldName] = $datePortion;
					}
				}
				$tableFieldValues[$fieldName . '__precision'] = $precision;
			} elseif ( $fieldType == 'Integer' ) {
				// Remove digit-grouping character.
				global $wgCargoDigitGroupingCharacter;
				$tableFieldValues[$fieldName] = str_replace( $wgCargoDigitGroupingCharacter, '', $curValue );
			} elseif ( $fieldType == 'Float' ) {
				// Remove digit-grouping character, and change
				// decimal mark to '.' if it's anything else.
				global $wgCargoDigitGroupingCharacter;
				global $wgCargoDecimalMark;
				$curValue = str_replace( $wgCargoDigitGroupingCharacter, '', $curValue );
				$curValue = str_replace( $wgCargoDecimalMark, '.', $curValue );
				$tableFieldValues[$fieldName] = $curValue;
			} elseif ( $fieldType == 'Boolean' ) {
				// True = 1, "yes"
				// False = 0, "no"
				$msgForNo = wfMessage( 'htmlform-no' )->text();
				if ( $curValue == '' ) {
					// Do nothing.
				} elseif ( $curValue == 0 || strtolower( $curValue ) == strtolower( $msgForNo ) ) {
					$tableFieldValues[$fieldName] = '0';
				} else {
					$tableFieldValues[$fieldName] = '1';
				}
			}
		}

		// Add the "metadata" field values.
		$tableFieldValues['_pageName'] = $pageName;
		$tableFieldValues['_pageTitle'] = $pageTitle;
		$tableFieldValues['_pageNamespace'] = $pageNamespace;
		$tableFieldValues['_pageID'] = $pageID;

		$cdb = CargoUtils::getDB();

		$res = $cdb->select( $tableName, 'MAX(_ID) AS "ID"' );
		$row = $cdb->fetchRow( $res );
		$curRowID = $row['ID'] + 1;
		$tableFieldValues['_ID'] = $curRowID;

		// For each field that holds a list of values, also add its
		// values to its own table; and rename the actual field.
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldType = $fieldDescription->mType;
			if ( $fieldDescription->mIsList ) {
				$fieldTableName = $tableName . '__' . $fieldName;
				$individualValues = explode( $fieldDescription->mDelimiter, $tableFieldValues[$fieldName] );
				foreach ( $individualValues as $individualValue ) {
					$individualValue = trim( $individualValue );
					// Ignore blank values.
					if ( $individualValue == '' ) {
						continue;
					}
					$fieldValues = array(
						'_rowID' => $curRowID,
						'_value' => $individualValue
					);
					// For coordinates, there are two more
					// fields, for latitude and longitude.
					if ( $fieldType == 'Coordinates' ) {
						list( $latitude, $longitude) = CargoUtils::parseCoordinatesString( $individualValue );
						$fieldValues['_lat'] = $latitude;
						$fieldValues['_lon'] = $longitude;
					}
					$cdb->insert( $fieldTableName, $fieldValues );
				}

				// Now rename the field.
				$tableFieldValues[$fieldName . '__full'] = $tableFieldValues[$fieldName];
				unset( $tableFieldValues[$fieldName] );
			} elseif ( $fieldType == 'Coordinates' ) {
				list( $latitude, $longitude) = CargoUtils::parseCoordinatesString( $tableFieldValues[$fieldName] );
				// Rename the field.
				$tableFieldValues[$fieldName . '__full'] = $tableFieldValues[$fieldName];
				unset( $tableFieldValues[$fieldName] );
				$tableFieldValues[$fieldName . '__lat'] = $latitude;
				$tableFieldValues[$fieldName . '__lon'] = $longitude;
			}
		}

		// Insert the current data into the main table.
		$cdb->insert( $tableName, $tableFieldValues );
		// This close() call, for some reason, is necessary for the
		// subsequent SQL to be called correctly, when jobs are run
		// in the standard way.
		$cdb->close();

		// Finally, add a record of this to the cargo_pages table, if
		// necessary.
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_pages', 'page_id',
			array( 'table_name' => $tableName, 'page_id' => $pageID ) );
		if ( !$row = $dbw->fetchRow( $res ) ) {
			$dbw->insert( 'cargo_pages', array( 'table_name' => $tableName, 'page_id' => $pageID ) );
		}
	}

}

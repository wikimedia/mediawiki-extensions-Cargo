<?php
/**
 * Class for the #cargo_store function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoStore {

	public static $settings = array();

	const FULL_PRECISION = 0;
	const TIME_MISSING = 1;
	const MONTH_ONLY = 2;
	const YEAR_ONLY = 3;

	/**
	 * Gets the template page where this table is defined -
	 * hopefully there's exactly one of them.
	 */
	public static function getTemplateIDForDBTable( $tableName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props', array(
			'pp_page'
			), array(
			'pp_value' => $tableName,
			'pp_propname' => 'CargoTableName'
			)
		);
		if ( !$row = $dbr->fetchRow( $res ) ) {
			return null;
		}
		return $row['pp_page'];
	}

	/**
	 * Parses one half of a set of coordinates into a number.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function coordinatePartToNumber( $coordinateStr ) {
		$degreesSymbols = array( "\x{00B0}", "d" );
		$minutesSymbols = array( "'", "\x{2032}", "\x{00B4}" );
		$secondsSymbols = array( '"', "\x{2033}", "\x{00B4}\x{00B4}" );

		$numDegrees = null;
		$numMinutes = null;
		$numSeconds = null;

		foreach ( $degreesSymbols as $degreesSymbol ) {
			$pattern = '/([\d\.]+)' . $degreesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numDegrees = floatval( $matches[1] );
				break;
			}
		}
		if ( $numDegrees == null ) {
			throw new MWException( "Error: could not parse degrees in \"$coordinateStr\"." );
		}

		foreach ( $minutesSymbols as $minutesSymbol ) {
			$pattern = '/([\d\.]+)' . $minutesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numMinutes = floatval( $matches[1] );
				break;
			}
		}
		if ( $numMinutes == null ) {
			// This might not be an error - the number of minutes
			// might just not have been set.
			$numMinutes = 0;
		}

		foreach ( $secondsSymbols as $secondsSymbol ) {
			$pattern = '/(\d+)' . $secondsSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numSeconds = floatval( $matches[1] );
				break;
			}
		}
		if ( $numSeconds == null ) {
			// This might not be an error - the number of seconds
			// might just not have been set.
			$numSeconds = 0;
		}

		return ( $numDegrees + ( $numMinutes / 60 ) + ( $numSeconds / 3600 ) );
	}

	/**
	 * Parses a coordinate string in (hopefully) any standard format.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function parseCoordinatesString( $coordinatesString ) {
		$coordinatesString = trim( $coordinatesString );
		if ( $coordinatesString == null ) {
			return;
		}

		// This is safe to do, right?
		$coordinatesString = str_replace( array( '[', ']' ), '', $coordinatesString );
		// See if they're separated by commas.
		if ( strpos( $coordinatesString, ',' ) > 0 ) {
			$latAndLonStrings = explode( ',', $coordinatesString );
		} else {
			// If there are no commas, the first half, for the
			// latitude, should end with either 'N' or 'S', so do a
			// little hack to split up the two halves.
			$coordinatesString = str_replace( array( 'N', 'S' ), array( 'N,', 'S,' ), $coordinatesString );
			$latAndLonStrings = explode( ',', $coordinatesString );
		}

		if ( count( $latAndLonStrings ) != 2 ) {
			throw new MWException( "Error parsing coordinates string: \"$coordinatesString\"." );
		}
		list( $latString, $lonString ) = $latAndLonStrings;

		// Handle strings one at a time.
		$latIsNegative = false;
		if ( strpos( $latString, 'S' ) > 0 ) {
			$latIsNegative = true;
		}
		$latString = str_replace( array( 'N', 'S' ), '', $latString );
		if ( is_numeric( $latString ) ) {
			$latNum = floatval( $latString );
		} else {
			$latNum = self::coordinatePartToNumber( $latString );
		}
		if ( $latIsNegative ) {
			$latNum *= -1;
		}

		$lonIsNegative = false;
		if ( strpos( $lonString, 'W' ) > 0 ) {
			$lonIsNegative = true;
		}
		$lonString = str_replace( array( 'E', 'W' ), '', $lonString );
		if ( is_numeric( $lonString ) ) {
			$lonNum = floatval( $lonString );
		} else {
			$lonNum = self::coordinatePartToNumber( $lonString );
		}
		if ( $lonIsNegative ) {
			$lonNum *= -1;
		}

		return array( $latNum, $lonNum );
	}

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
				wfDebugLog( 'cargo', "CargoStore::run() - skipping 3.\n" );
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
			wfDebugLog( 'cargo', "CargoStore::run() - skipping 4.\n" );
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
				if ( $curValue != '' ) {
					// Special handling if it's just a year.
					if ( ctype_digit( $curValue ) ) {
						// Add a fake date - it will
						// get ignored later.
						$curValue = "$curValue-01-01";
						$precision = self::YEAR_ONLY;
					} else {
						// Determine if there's a month
						// but no day. There's no ideal
						// way to do this, so: we'll
						// just look for the total
						// number of spaces, slashes
						// and dashes, and if there's
						// exactly one altogether, we'll
						// guess that it's a month only.
						$numSpecialChars = substr_count( $curValue, ' ' ) +
							substr_count( $curValue, '/' ) + substr_count( $curValue, '-' );
						if ( $numSpecialChars == 1 ) {
							// No need to add
							// anything - PHP will
							// set it to the 1st
							// of the month.
							$precision = self::MONTH_ONLY;
						} else {
							$precision = self::FULL_PRECISION;
						}
					}
					$seconds = strtotime( $curValue );
					if ( $fieldType == 'Date' ) {
						// Put into YYYY-MM-DD format.
						$tableFieldValues[$fieldName] = date( 'Y-m-d', $seconds );
					} else { // ( $fieldType == 'Datetime' )
						// @TODO - check for
						// "time missing" precision.
						$tableFieldValues[$fieldName] = date( 'Y-m-d G:i:s', $seconds );
					}
					$tableFieldValues[$fieldName . '__precision'] = $precision;
				}
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
		$pageName = $parser->getTitle()->getPrefixedText();
		$pageTitle = $parser->getTitle()->getText();
		$pageNamespace = $parser->getTitle()->getNamespace();
		$pageID = $parser->getTitle()->getArticleID();
		$tableFieldValues['_pageName'] = $pageName;
		$tableFieldValues['_pageTitle'] = $pageTitle;
		$tableFieldValues['_pageNamespace'] = $pageNamespace;
		$tableFieldValues['_pageID'] = $pageID;

		$cdb = CargoUtils::getDB();

		$res = $cdb->select( $tableName, 'MAX(_ID) AS ID' );
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
						list( $latitude, $longitude) = self::parseCoordinatesString( $individualValue );
						$fieldValues['_lat'] = $latitude;
						$fieldValues['_lon'] = $longitude;
					}
					$cdb->insert( $fieldTableName, $fieldValues );
				}

				// Now rename the field.
				$tableFieldValues[$fieldName . '__full'] = $tableFieldValues[$fieldName];
				unset( $tableFieldValues[$fieldName] );
			} elseif ( $fieldType == 'Coordinates' ) {
				list( $latitude, $longitude) = self::parseCoordinatesString( $tableFieldValues[$fieldName] );
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

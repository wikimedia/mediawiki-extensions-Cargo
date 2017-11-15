<?php

/**
 * Static functions for dealing with the "_fileData" table.
 *
 * @author Yaron Koren
 */
class CargoFileData {

	/**
	 * Set the schema based on what has been entered in LocalSettings.php.
	 */
	static function getTableSchema() {
		global $wgCargoFileDataColumns;

		$fieldTypes = array();

		if ( in_array( 'mediaType', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_mediaType'] = array( 'String', false );
		}
		if ( in_array( 'path', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_path'] = array( 'String', false );
		}
		if ( in_array( 'lastUploadDate', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_lastUploadDate'] = array( 'Date', false );
		}
		if ( in_array( 'fullText', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_fullText'] = array( 'Searchtext', false );
		}
		if ( in_array( 'numPages', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_numPages'] = array( 'Integer', false );
		}

		$tableSchema = new CargoTableSchema();
		foreach ( $fieldTypes as $field => $fieldVals ) {
			list ( $type, $isList ) = $fieldVals;
			$fieldDesc = new CargoFieldDescription();
			$fieldDesc->mType = $type;
			if ( $isList ) {
				$fieldDesc->mIsList = true;
				$fieldDesc->setDelimiter( '|' );
			}
			$tableSchema->mFieldDescriptions[$field] = $fieldDesc;
		}

		return $tableSchema;
	}

	/**
	 */
	public static function storeValuesForFile( $title ) {
		global $wgCargoFileDataColumns, $wgLocalFileRepo;

		if ( $title == null ) {
			return;
		}

		// Exit if we're not in the File namespace.
		if ( $title->getNamespace() != NS_FILE ) {
			return;
		}

		// If there is no _fileData table, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( array( '_fileData' ) );
		} catch ( MWException $e ) {
			return;
		}

		$repo = new LocalRepo( $wgLocalFileRepo );
		$file = LocalFile::newFromTitle( $title, $repo );

		$fileDataValues = array();

		if ( in_array( 'mediaType', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_mediaType'] = $file->getMimeType();
		}

		if ( in_array( 'path', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_path'] = $file->getLocalRefPath();
		}

		if ( in_array( 'lastUploadDate', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_lastUploadDate'] = $file->getTimestamp();
		}

		if ( in_array( 'fullText', $wgCargoFileDataColumns ) ) {
			global $wgCargoPDFToText;

			if ( $wgCargoPDFToText == '' ) {
				// Display an error message?
			} elseif ( $file->getMimeType() != 'application/pdf' ) {
				// We only handle PDF files.
			} else {
				// Copied in part from the PdfHandler extension.
				$filePath = $file->getLocalRefPath();
				$cmd = wfEscapeShellArg( $wgCargoPDFToText ) . ' '. wfEscapeShellArg( $filePath ) . ' - ';
				$retval = '';
				$txt = wfShellExec( $cmd, $retval );
				if ( $retval == 0 ) {
					$txt = str_replace( "\r\n", "\n", $txt );
					$txt = str_replace( "\f", "\n\n", $txt );
					$fileDataValues['_fullText'] = $txt;
				}
			}
		}

		if ( in_array( 'numPages', $wgCargoFileDataColumns ) ) {
			global $wgCargoPDFInfo;
			if ( $wgCargoPDFInfo == '' ) {
				// Display an error message?
			} elseif ( $file->getMimeType() != 'application/pdf' ) {
				// We only handle PDF files.
			} else {
				$filePath = $file->getLocalRefPath();
				$cmd = wfEscapeShellArg( $wgCargoPDFInfo ) . ' '. wfEscapeShellArg( $filePath );
				$retval = '';
				$txt = wfShellExec( $cmd, $retval );
				if ( $retval == 0 ) {
					$lines = explode( PHP_EOL, $txt );
					$matched = preg_grep( '/^Pages\:/', $lines );
					foreach ( $matched as $line ) {
						$fileDataValues['_numPages'] = intval( trim( substr( $line, 7 ) ) );
					}
				}
			}
		}

		CargoStore::storeAllData( $title, '_fileData', $fileDataValues, $tableSchemas['_fileData'] );
	}

}
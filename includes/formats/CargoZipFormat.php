<?php
/**
 * @author Sanjay Thiyagarajan
 * @ingroup Cargo
 *
 * Generates a zip file consisting all queried files
 */

use MediaWiki\MediaWikiServices;

class CargoZipFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [
			'filename' => [ 'type' => 'string' ],
			'link text' => [ 'type' => 'string' ]
		];
	}

	protected function getFiles( $valuesTable, $fieldDescriptions ) {
		$fileField = null;
		foreach ( $fieldDescriptions as $field => $fieldDesc ) {
			if ( $fieldDesc->mType == 'File' || $fieldDesc->mType == 'Page' ) {
				$fileField = $field;
				break;
			}
		}
		$fileNames = [];
		foreach ( $valuesTable as $row ) {
			if ( array_key_exists( $fileField, $row ) ) {
				$fileNames[] = [
					'title' => $row[$fileField]
				];
			}
		}
		$files = [];
		foreach ( $fileNames as $f ) {
			$title = Title::makeTitleSafe( NS_FILE, $f['title'] );
			if ( $title == null ) {
				continue;
			}
			$files[] = [
				'title' => $title
			];
		}
		return $files;
	}

	/**
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams Unused
	 * @return string HTML
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModules( [ 'ext.cargo.zip' ] );

		$files = self::getFiles( $valuesTable, $fieldDescriptions );

		if ( array_key_exists( 'filename', $displayParams ) && $displayParams['filename'] != '' ) {
			$filename = $displayParams['filename'];
		} else {
			$filename = 'results.zip';
		}

		if ( array_key_exists( 'link text', $displayParams ) && $displayParams['link text'] != '' ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-downloadzip' );
		}

		$text = '<div class="downloadlink" data-fileurls="' . $filename . ' ';

		foreach ( $files as $file ) {
			$filename = explode( ':', $file['title'] );
			$filename = array_pop( $filename );
			if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
				// MediaWiki 1.34+
				$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
			} else {
				$localRepo = RepoGroup::singleton()->getLocalRepo();
			}
			if ( $localRepo->findFile( $filename ) ) {
				$url = $localRepo->findFile( $filename )->getFullUrl();
				$text .= $url . ' ';
			} else {
				throw new MWException( wfMessage( 'cargo-downloadzip-invalidformat' ) );
			}
		}

		$text .= '">' . $linkText . '</div>';

		return $text;
	}
}

<?php

use MediaWiki\Hook\LinksUpdateHook;

class CargoLinksUpdateHandler implements LinksUpdateHook {

	/**
	 * Update backlinks for Cargo queries when a page is saved.
	 * @param LinksUpdate $linksUpdate
	 */
	public function onLinksUpdate( $linksUpdate ): void {
		// MW < 1.38 compatibility
		$pageId = method_exists( $linksUpdate, 'getPageId' ) ? $linksUpdate->getPageId() : $linksUpdate->mId;

		$parserOutput = $linksUpdate->getParserOutput();
		$backlinks = array_keys(
			$parserOutput->getExtensionData( CargoBackLinks::BACKLINKS_DATA_KEY ) ?? []
		);

		if ( count( $backlinks ) > 0 ) {
			CargoBackLinks::setBackLinks( $linksUpdate->getTitle(), $backlinks );
		} else {
			CargoBackLinks::removeBackLinks( $pageId );
		}

		// Don't save ephemeral data into the parser cache.
		$parserOutput->setExtensionData( CargoBackLinks::BACKLINKS_DATA_KEY, null );
	}
}

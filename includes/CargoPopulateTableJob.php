<?php
/**
 * Background job to populate the database table for one template using the
 * data from the call(s) to that template in one page.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoPopulateTableJob extends Job {

	/**
	 *
	 * @param Title $title
	 * @param array|bool $params
	 */
	function __construct( $title, $params = false ) {
		parent::__construct( 'cargoPopulateTable', $title, $params );
	}

	/**
	 * Run a CargoPopulateTable job.
	 *
	 * @return bool success
	 */
	function run() {
		if ( is_null( $this->title ) ) {
			$this->error = "cargoPopulateTable: Invalid title";
			return false;
		}

		$page = WikiPage::factory( $this->title );

		// If it was requested, delete all the existing rows for
		// this page in this Cargo table. This is only necessary
		// if the table wasn't just dropped and recreated.
		if ( $this->params['replaceOldRows'] == true ) {
			$cdb = CargoUtils::getDB();
			$cdb->delete( $this->params['dbTableName'], array( '_pageID' => $page->getID() ) );
		}

		// All we need to do here is set some global variables based
		// on the parameters of this job, then parse the page -
		// the #cargo_store function will take care of the rest.
		CargoStore::$settings['origin'] = 'template';
		CargoStore::$settings['dbTableName'] = $this->params['dbTableName'];
		CargoUtils::parsePageForStorage( $this->title, ContentHandler::getContentText( $page->getContent() ) );

		// We need to unset this, if the job was called via runJobs.php,
		// so that it doesn't affect other (non-Cargo) jobs, like page
		// refreshes.
		unset( CargoStore::$settings['origin'] );

		return true;
	}
}

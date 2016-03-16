<?php

/**
 * We need to create subclasses, instead of just calling the functionality,
 * because both filter() and, more importantly, $searchTerms are currently
 * "protected".
 */
class CargoSearchMySQL extends SearchMySQL {

	function getSearchTerms( $searchString ) {
		$filteredTerm = $this->filter( $searchString );
		$this->parseQuery( $filteredTerm, false );
		return $this->searchTerms;
	}

}

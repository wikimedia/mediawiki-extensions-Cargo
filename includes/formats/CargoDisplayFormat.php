<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDisplayFormat {

	/** @var ParserOutput */
	protected $mOutput;

	public function __construct( $output, $parser = null ) {
		$this->mOutput = $output;
		$this->mParser = $parser;
	}

	public static function allowedParameters() {
		return [];
	}

	public static function isDeferred() {
		return false;
	}

	/**
	 * Apply a Cargo format to a 2D row-based array of values of any origin.
	 *
	 * @author Alexander Mashin
	 * @param Parser $parser
	 * @param array $values A 2D row-based array of values.
	 * @param array $mappings A mapping from ED to Cargo variables.
	 * @param array $params An array of params for {{#cargo_query:}}.
	 *
	 * @return array [ string, 'noparse' => bool, 'isHTML' => bool ].
	 */
	public static function formatArray( Parser $parser, array $values, array $mappings, array $params ): array {
		$format = isset( $params['format'] ) ? $params['format'] : 'list';
		$classes = CargoQueryDisplayer::getAllFormatClasses();
		$class = isset( $classes[$format] ) ? $classes[$format] : 'CargoListFormat';
		$formatter = new $class( $parser->getOutput(), $parser );

		// This cannot yet be called for "deferred" formats, where the
		// processing is done via JavaScript and not PHP - in theory,
		// it could be done, but it would require being able to pass
		// in a data set to the JavaScript, instead of query
		// information.
		if ( $formatter->isDeferred() ) {
			throw new MWException( "formatArray() cannot be called for the $format format because it is a \"deferred format\"." );
		}

		$query_displayer = new CargoQueryDisplayer();
		$field_descriptions = [];
		foreach ( $mappings as $local => $external ) {
			$description = new CargoFieldDescription();
			$description->mType = 'String';
			$field_descriptions[$local] = $description;
		}
		$query_displayer->mFieldDescriptions = $field_descriptions;
		$query_displayer->mFieldTables = [];

		$html = $formatter->display(
			$values,
			$query_displayer->getFormattedQueryResults( $values ),
			$query_displayer->mFieldDescriptions,
			$params
		);
		$no_html = isset( $params['no html'] ) ? $params['no html'] : false;
		return !$no_html && $format !== 'template'
			? [ $html, 'noparse' => true, 'isHTML' => true ]
			: [ $html, 'noparse' => false ];
	}
}

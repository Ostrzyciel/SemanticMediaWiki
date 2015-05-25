<?php

namespace SMW;

use Parser;
use ParserOutput;
use SMW\Annotator\PropertyAnnotatorFactory;
use SMW\Cache\CacheHandler;
use SMW\Factbox\FactboxFactory;
use SMW\MediaWiki\Jobs\JobFactory;
use SMW\MediaWiki\MwCollaboratorFactory;
use SMW\MediaWiki\PageCreator;
use SMW\MediaWiki\TitleCreator;
use SMW\Query\Profiler\QueryProfilerFactory;
use SMW\Maintenance\MaintenanceFactory;
use SMW\Cache\CacheFactory;
use SMWQueryParser as QueryParser;
use Title;

/**
 * Application instances access for internal and external use
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author mwjames
 */
class ApplicationFactory {

	/**
	 * @var ApplicationFactory
	 */
	private static $instance = null;

	/**
	 * @var DependencyBuilder
	 */
	private $builder = null;

	/**
	 * @since 2.0
	 */
	public function __construct( DependencyBuilder $builder = null ) {
		$this->builder = $builder;
	}

	/**
	 * This method returns the global instance of the application factory.
	 *
	 * Reliance on global state is needed at entry points into SMW such as
	 * hook handlers, special pages and jobs, since there we tend to not
	 * have control over the object lifecycle. Pragmatically we might also
	 * want to use this when refactoring legacy code that already has the
	 * global state dependency. For new code very special justification is
	 * required to rely on global state.
	 *
	 * @since 2.0
	 *
	 * @return self
	 */
	public static function getInstance() {

		if ( self::$instance === null ) {
			self::$instance = new self( self::registerBuilder() );
		}

		return self::$instance;
	}

	/**
	 * @since 2.0
	 */
	public static function clear() {

		if ( self::$instance !== null ) {
			self::$instance->getSettings()->clear();
		}

		self::$instance = null;
	}

	/**
	 * @since 2.0
	 *
	 * @param string $objectName
	 * @param callable|array $objectSignature
	 *
	 * @return $this
	 */
	public function registerObject( $objectName, $objectSignature ) {
		$this->builder->getContainer()->registerObject( $objectName, $objectSignature );
		return $this;
	}

	/**
	 * @since 2.0
	 *
	 * @return SerializerFactory
	 */
	public function newSerializerFactory() {
		return new SerializerFactory();
	}

	/**
	 * @since 2.0
	 *
	 * @return FactboxFactory
	 */
	public function newFactboxFactory() {
		return $this->builder->newObject( 'FactboxFactory' );
	}

	/**
	 * @since 2.0
	 *
	 * @return PropertyAnnotatorFactory
	 */
	public function newPropertyAnnotatorFactory() {
		return new PropertyAnnotatorFactory();
	}

	/**
	 * @since 2.0
	 *
	 * @return JobFactory
	 */
	public function newJobFactory() {
		return $this->builder->newObject( 'JobFactory' );
	}

	/**
	 * @since 2.1
	 *
	 * @param Parser $parser
	 *
	 * @return ParserFunctionFactory
	 */
	public function newParserFunctionFactory( Parser $parser ) {
		return new ParserFunctionFactory( $parser );
	}

	/**
	 * @since 2.1
	 *
	 * @return QueryProfilerFactory
	 */
	public function newQueryProfilerFactory() {
		return new QueryProfilerFactory();
	}

	/**
	 * @since 2.2
	 *
	 * @return MaintenanceFactory
	 */
	public function newMaintenanceFactory() {
		return new MaintenanceFactory();
	}

	/**
	 * @since 2.2
	 *
	 * @return CacheFactory
	 */
	public function newCacheFactory() {
		return new CacheFactory( $this->getSettings()->get( 'smwgCacheType' ) );
	}

	/**
	 * @since 2.0
	 *
	 * @return Store
	 */
	public function getStore() {
		return $this->builder->newObject( 'Store' );
	}

	/**
	 * @since 2.0
	 *
	 * @return Settings
	 */
	public function getSettings() {
		return $this->builder->newObject( 'Settings' );
	}

	/**
	 * @since 2.0
	 *
	 * @return TitleCreator
	 */
	public function newTitleCreator() {
		return $this->builder->newObject( 'TitleCreator' );
	}

	/**
	 * @since 2.0
	 *
	 * @return PageCreator
	 */
	public function newPageCreator() {
		return $this->builder->newObject( 'PageCreator' );
	}

	/**
	 * @since 2.0
	 *
	 * @return Cache
	 */
	public function getCache() {
		return $this->builder->newObject( 'Cache' );
	}

	/**
	 * @since 2.0
	 *
	 * @return InTextAnnotationParser
	 */
	public function newInTextAnnotationParser( ParserData $parserData ) {

		$mwCollaboratorFactory = $this->newMwCollaboratorFactory();

		return new InTextAnnotationParser(
			$parserData,
			$mwCollaboratorFactory->newMagicWordFinder(),
			$mwCollaboratorFactory->newRedirectTargetFinder()
		);
	}

	/**
	 * @since 2.0
	 *
	 * @return ParserData
	 */
	public function newParserData( Title $title, ParserOutput $parserOutput ) {
		return $this->builder->newObject( 'ParserData', array(
			'Title'        => $title,
			'ParserOutput' => $parserOutput
		) );
	}

	/**
	 * @since 2.0
	 *
	 * @return ContentParser
	 */
	public function newContentParser( Title $title ) {
		return $this->builder->newObject( 'ContentParser', array(
			'Title' => $title
		) );
	}

	/**
	 * @since 2.1
	 *
	 * @param SemanticData $semanticData
	 *
	 * @return StoreUpdater
	 */
	public function newStoreUpdater( SemanticData $semanticData ) {
		return new StoreUpdater( $this->getStore(), $semanticData );
	}

	/**
	 * @since 2.1
	 *
	 * @return MwCollaboratorFactory
	 */
	public function newMwCollaboratorFactory() {
		return new MwCollaboratorFactory( $this );
	}

	/**
	 * @since 2.1
	 *
	 * @return NamespaceExaminer
	 */
	public function getNamespaceExaminer() {
		return NamespaceExaminer::newFromArray( $this->getSettings()->get( 'smwgNamespacesWithSemanticLinks' ) );
	}

	/**
	 * @since 2.1
	 *
	 * @return QueryParser
	 */
	public function newQueryParser() {
		return new QueryParser();
	}

	private static function registerBuilder( DependencyBuilder $builder = null ) {

		if ( $builder === null ) {
			$builder = new SimpleDependencyBuilder( new SharedDependencyContainer() );
		}

		return $builder;
	}

}

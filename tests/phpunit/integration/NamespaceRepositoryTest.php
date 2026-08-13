<?php

namespace MediaWiki\Extension\NamespaceManager\Tests\Integration;

use MediaWiki\Extension\NamespaceManager\NamespaceRepository;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IDatabase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NamespaceManager\NamespaceRepository
 */
class NamespaceRepositoryTest extends MediaWikiIntegrationTestCase {

	private NamespaceRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		/** @var NamespaceRepository $repository */
		$repository = $this->getServiceContainer()->getService(
			'NamespaceManager.Repository'
		);
		$this->repository = $repository;
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'namespacemanager_namespace' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$this->repository->invalidate();
	}

	protected function tearDown(): void {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'namespacemanager_namespace' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$this->repository->invalidate();
		parent::tearDown();
	}

	public function testReplaceAndLoadRoundTrip(): void {
		$definitions = $this->getDefinitions();

		$this->repository->replaceAll( $definitions );

		$this->assertSame(
			1,
			(int)$this->getServiceContainer()
				->getConnectionProvider()
				->getPrimaryDatabase()
				->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'namespacemanager_namespace' )
				->caller( __METHOD__ )
				->fetchField()
		);
		$this->assertFalse( $this->repository->isEmpty() );
	}

	public function testReplaceWithEmptyListDeletesAllRows(): void {
		$this->repository->replaceAll( $this->getDefinitions() );

		$this->repository->replaceAll( [] );

		$this->assertTrue( $this->repository->isEmpty() );
	}

	public function testGetAllReturnsTheStoredDefinitionsAfterAReplace(): void {
		$repository = $this->newRepository( $this->newCache() );
		$repository->getAll();

		$repository->replaceAll( $this->getDefinitions() );

		$this->assertSame( [ 3000 ], array_column( $repository->getAll(), 'id' ) );
	}

	public function testAnotherProcessSeesTheDefinitionsSavedByAReplace(): void {
		$cacheBag = new HashBagOStuff();
		$writer = $this->newRepository( $this->newCache( $cacheBag ) );
		$reader = $this->newRepository( $this->newCache( $cacheBag ) );
		$reader->getAll();

		$writer->replaceAll( $this->getDefinitions() );

		$this->assertSame( [ 3000 ], array_column( $reader->getAll(), 'id' ) );
	}

	public function testInvalidateForcesTheDefinitionsToBeReadAgain(): void {
		$cacheBag = new HashBagOStuff();
		$writer = $this->newRepository( $this->newCache( $cacheBag ) );
		$reader = $this->newRepository( $this->newCache( $cacheBag ) );
		$writer->replaceAll( $this->getDefinitions() );
		$reader->getAll();

		$writer->replaceAll( [] );

		$this->assertSame( [], $reader->getAll() );
	}

	private function newCache( ?HashBagOStuff $cacheBag = null ): WANObjectCache {
		return new WANObjectCache( [ 'cache' => $cacheBag ?? new HashBagOStuff() ] );
	}

	private function newRepository( WANObjectCache $cache ): NamespaceRepository {
		return new NamespaceRepository(
			$this->getServiceContainer()->getConnectionProvider(),
			$cache,
			new NullLogger()
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function getDefinitions(): array {
		return [
			[
				'id' => 3000,
				'name' => 'Property',
				'content' => true,
				'visualeditor' => false,
				'searchdefault' => true,
				'talksearchdefault' => false,
				'subpages' => false,
				'talksubpages' => true,
				'includable' => true,
				'talkincludable' => false,
				'aliases' => [ 'Properties' ],
				'talkaliases' => [ 'Properties_talk' ],
				'editpermissions' => [],
				'talkeditpermissions' => [ 'propertymanagers' ],
			],
		];
	}
}

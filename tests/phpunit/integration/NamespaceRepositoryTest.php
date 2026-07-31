<?php

namespace MediaWiki\Extension\NamespaceManager\Tests\Integration;

use MediaWiki\Extension\NamespaceManager\NamespaceRepository;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
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

	public function testInvalidationRejectsConcurrentStaleCacheFill(): void {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$mockTime = 1_700_000_000.0;
		$cache->setMockTime( $mockTime );
		$repository = $this->newCacheOnlyRepository( $cache );
		$key = $cache->makeKey( 'namespacemanager', 'definitions' );
		$checkKey = $cache->makeKey( 'namespacemanager', 'definitions', 'check' );
		$options = $this->getCacheOptions( $checkKey );

		$stale = $cache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_WEEK,
			static function () use ( $repository ): string {
				$repository->invalidate();
				return 'stale';
			},
			$options
		);
		$mockTime += 20;
		$regenerations = 0;
		$fresh = $cache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_WEEK,
			static function () use ( &$regenerations ): string {
				$regenerations++;
				return 'fresh';
			},
			$options
		);

		$this->assertSame( 'stale', $stale );
		$this->assertSame( 'fresh', $fresh );
		$this->assertSame( 1, $regenerations );
	}

	public function testInvalidationDoesNotPurgeConcurrentFreshCacheFill(): void {
		$cacheBag = new CallbackHashBagOStuff();
		$writerCache = new WANObjectCache( [ 'cache' => $cacheBag ] );
		$readerCache = new WANObjectCache( [ 'cache' => $cacheBag ] );
		$repository = $this->newCacheOnlyRepository( $writerCache );
		$key = $readerCache->makeKey( 'namespacemanager', 'definitions' );
		$checkKey = $readerCache->makeKey( 'namespacemanager', 'definitions', 'check' );
		$options = $this->getCacheOptions( $checkKey );
		$regenerations = 0;

		$cacheBag->afterNextSet(
			static function () use (
				$readerCache,
				$key,
				$options,
				&$regenerations
			): void {
				$readerCache->getWithSetCallback(
					$key,
					WANObjectCache::TTL_WEEK,
					static function () use ( &$regenerations ): string {
						$regenerations++;
						return 'fresh';
					},
					$options
				);
			}
		);

		$repository->invalidate();
		$value = $readerCache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_WEEK,
			static function () use ( &$regenerations ): string {
				$regenerations++;
				return 'unexpected';
			},
			$options
		);

		$this->assertSame( 'fresh', $value );
		$this->assertSame( 1, $regenerations );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getCacheOptions( string $checkKey ): array {
		return [
			'checkKeys' => [ $checkKey ],
			'hotTTR' => WANObjectCache::TTL_HOUR,
			'lockTSE' => 30,
			'version' => 1,
		];
	}

	private function newCacheOnlyRepository( WANObjectCache $cache ): NamespaceRepository {
		return new NamespaceRepository(
			$this->createMock( IConnectionProvider::class ),
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

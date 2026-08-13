<?php

namespace MediaWiki\Extension\NamespaceManager;

use JsonException;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

class NamespaceRepository {

	private const TABLE = 'namespacemanager_namespace';
	private const CACHE_VERSION = 1;

	private IConnectionProvider $connectionProvider;
	private WANObjectCache $cache;
	private LoggerInterface $logger;
	private bool $loaded = false;

	/** @var array<int,array<string,mixed>> */
	private array $memoizedDefinitions = [];

	public function __construct(
		IConnectionProvider $connectionProvider,
		WANObjectCache $cache,
		LoggerInterface $logger
	) {
		$this->connectionProvider = $connectionProvider;
		$this->cache = $cache;
		$this->logger = $logger;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getAll(): array {
		if ( $this->loaded ) {
			return $this->memoizedDefinitions;
		}

		$key = $this->getCacheKey();
		$definitions = $this->cache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_WEEK,
			fn (): array => $this->loadFromDatabase(),
			[
				'checkKeys' => [ $this->getCheckKey() ],
				'hotTTR' => WANObjectCache::TTL_HOUR,
				'lockTSE' => 30,
			]
		);

		$this->memoizedDefinitions = is_array( $definitions ) ? $definitions : [];
		$this->loaded = true;
		return $this->memoizedDefinitions;
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 */
	public function replaceAll( array $definitions ): void {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__, $dbw::ATOMIC_CANCELABLE );
		try {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( self::TABLE )
				->where( IDatabase::ALL_ROWS )
				->caller( __METHOD__ )
				->execute();

			if ( $definitions !== [] ) {
				$timestamp = $dbw->timestamp();
				$rows = [];
				foreach ( $definitions as $definition ) {
					$rows[] = $this->definitionToRow( $definition, $timestamp );
				}
				$dbw->newInsertQueryBuilder()
					->insertInto( self::TABLE )
					->rows( $rows )
					->caller( __METHOD__ )
					->execute();
			}
			$dbw->endAtomic( __METHOD__ );
		} catch ( \Throwable $e ) {
			$dbw->cancelAtomic( __METHOD__ );
			throw $e;
		}

		// Readers that run before the transaction is committed must not cache the
		// pre-write state for a week, so the cache is purged right away as well.
		$this->invalidate();
		$dbw->onTransactionCommitOrIdle(
			function (): void {
				$this->refresh();
			},
			__METHOD__
		);
	}

	public function isEmpty(): bool {
		$dbr = $this->connectionProvider->getPrimaryDatabase();
		// IConnectionProvider returns DBConnRef here, whose method is omitted from IDatabase.
		// @phan-suppress-next-line PhanUndeclaredMethod
		if ( !$dbr->tableExists( self::TABLE, __METHOD__ ) ) {
			return true;
		}
		return !$dbr->newSelectQueryBuilder()
			->select( '1' )
			->from( self::TABLE )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Purge the cached definitions and repopulate the cache from the database.
	 *
	 * Purging on its own does not guarantee that the very next request sees the new
	 * definitions: check key timestamps only have a one second resolution, and values
	 * cached within the last few milliseconds are reused regardless of any purge. The
	 * stored definitions are therefore written back to the cache so that even a value
	 * that escapes the purge holds the new state.
	 */
	private function refresh(): void {
		$this->invalidate();
		$definitions = $this->loadFromDatabase();
		$this->cache->set(
			$this->getCacheKey(),
			$definitions,
			WANObjectCache::TTL_WEEK
		);
		$this->memoizedDefinitions = $definitions;
		$this->loaded = true;
	}

	public function invalidate(): void {
		$this->cache->touchCheckKey( $this->getCheckKey() );
		$this->loaded = false;
		$this->memoizedDefinitions = [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function loadFromDatabase(): array {
		// Namespace definitions affect title interpretation and must never disappear
		// temporarily because a replica is behind the primary.
		$dbr = $this->connectionProvider->getPrimaryDatabase();
		// IConnectionProvider returns DBConnRef here, whose method is omitted from IDatabase.
		// @phan-suppress-next-line PhanUndeclaredMethod
		if ( !$dbr->tableExists( self::TABLE, __METHOD__ ) ) {
			$this->logger->warning(
				'NamespaceManager table is unavailable; no custom namespaces were loaded.'
			);
			return [];
		}

		$rows = $dbr->newSelectQueryBuilder()
			->select( [
				'nsm_id',
				'nsm_name',
				'nsm_talk_name',
				'nsm_content',
				'nsm_visual_editor',
				'nsm_search_default',
				'nsm_talk_search_default',
				'nsm_subpages',
				'nsm_talk_subpages',
				'nsm_includable',
				'nsm_talk_includable',
				'nsm_aliases',
				'nsm_talk_aliases',
				'nsm_edit_permissions',
				'nsm_talk_edit_permissions',
			] )
			->from( self::TABLE )
			->orderBy( 'nsm_id' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$definitions = [];
		foreach ( $rows as $row ) {
			$definition = [
				'id' => (int)$row->nsm_id,
				'name' => (string)$row->nsm_name,
				'content' => (bool)$row->nsm_content,
				'visualeditor' => (bool)$row->nsm_visual_editor,
				'searchdefault' => (bool)$row->nsm_search_default,
				'talksearchdefault' => (bool)$row->nsm_talk_search_default,
				'subpages' => (bool)$row->nsm_subpages,
				'talksubpages' => (bool)$row->nsm_talk_subpages,
				'includable' => (bool)$row->nsm_includable,
				'talkincludable' => (bool)$row->nsm_talk_includable,
				'aliases' => $this->decodeList( (string)$row->nsm_aliases ),
				'talkaliases' => $this->decodeList( (string)$row->nsm_talk_aliases ),
				'editpermissions' => $this->decodeList( (string)$row->nsm_edit_permissions ),
				'talkeditpermissions' => $this->decodeList(
					(string)$row->nsm_talk_edit_permissions
				),
			];
			if ( $row->nsm_talk_name !== null ) {
				$definition['talkname'] = (string)$row->nsm_talk_name;
			}
			$definitions[] = $definition;
		}
		return $definitions;
	}

	/**
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>
	 */
	private function definitionToRow( array $definition, string $timestamp ): array {
		return [
			'nsm_id' => $definition['id'],
			'nsm_name' => $definition['name'],
			'nsm_talk_name' => $definition['talkname'] ?? null,
			'nsm_content' => (int)$definition['content'],
			'nsm_visual_editor' => (int)$definition['visualeditor'],
			'nsm_search_default' => (int)$definition['searchdefault'],
			'nsm_talk_search_default' => (int)$definition['talksearchdefault'],
			'nsm_subpages' => (int)$definition['subpages'],
			'nsm_talk_subpages' => (int)$definition['talksubpages'],
			'nsm_includable' => (int)$definition['includable'],
			'nsm_talk_includable' => (int)$definition['talkincludable'],
			'nsm_aliases' => json_encode( $definition['aliases'], JSON_THROW_ON_ERROR ),
			'nsm_talk_aliases' => json_encode( $definition['talkaliases'], JSON_THROW_ON_ERROR ),
			'nsm_edit_permissions' => json_encode(
				$definition['editpermissions'],
				JSON_THROW_ON_ERROR
			),
			'nsm_talk_edit_permissions' => json_encode(
				$definition['talkeditpermissions'],
				JSON_THROW_ON_ERROR
			),
			'nsm_updated' => $timestamp,
		];
	}

	/**
	 * @return string[]
	 * @throws JsonException
	 */
	private function decodeList( string $json ): array {
		$value = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		if ( !is_array( $value ) ) {
			throw new JsonException( 'Stored namespace list is not an array.' );
		}
		return array_values( array_map( 'strval', $value ) );
	}

	private function getCheckKey(): string {
		return $this->cache->makeKey( 'namespacemanager', 'definitions', 'check' );
	}

	private function getCacheKey(): string {
		// The version is part of the key rather than a "version" cache option so that
		// the cached value can be replaced with a plain set() in refresh().
		return $this->cache->makeKey(
			'namespacemanager',
			'definitions',
			'v' . self::CACHE_VERSION
		);
	}
}

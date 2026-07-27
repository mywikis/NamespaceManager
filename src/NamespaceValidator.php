<?php

namespace MediaWiki\Extension\NamespaceManager;

use MediaWiki\Language\Language;

class NamespaceValidator {

	public const MIN_ID = 3000;
	public const MAX_ID = 4998;

	private const BOOLEAN_DEFAULTS = [
		'content' => false,
		'visualeditor' => false,
		'searchdefault' => false,
		'talksearchdefault' => false,
		'subpages' => false,
		'talksubpages' => false,
		'includable' => true,
		'talkincludable' => true,
	];

	private const ARRAY_FIELDS = [
		'aliases',
		'talkaliases',
		'editpermissions',
		'talkeditpermissions',
	];

	private Language $contentLanguage;

	public function __construct( Language $contentLanguage ) {
		$this->contentLanguage = $contentLanguage;
	}

	/**
	 * @param mixed $definitions
	 * @param array<int,string> $reservedNamespaces
	 * @param array<string,int> $reservedNames
	 * @return array<int,array<string,mixed>>
	 * @throws ValidationException
	 */
	public function validate(
		$definitions,
		array $reservedNamespaces = [],
		array $reservedNames = []
	): array {
		if ( !is_array( $definitions ) || !array_is_list( $definitions ) ) {
			throw new ValidationException( [
				[ 'index' => -1, 'field' => 'namespaces', 'code' => 'not-a-list' ],
			] );
		}

		$errors = [];
		$normalized = [];
		$usedIds = [];
		$usedNames = [];
		foreach ( $reservedNamespaces as $id => $name ) {
			$usedIds[(int)$id] = true;
			$usedNames[$this->nameKey( $name )] = (int)$id;
		}
		foreach ( $reservedNames as $name => $id ) {
			$usedNames[$this->nameKey( $name )] = $id;
		}

		foreach ( $definitions as $index => $definition ) {
			if ( !is_array( $definition ) ) {
				$errors[] = [ 'index' => $index, 'field' => 'namespace', 'code' => 'not-an-object' ];
				continue;
			}

			$item = $this->normalizeDefinition( $definition, $index, $errors );
			if ( $item === null ) {
				continue;
			}

			$id = $item['id'];
			foreach ( [ $id, $id + 1 ] as $namespaceId ) {
				if ( isset( $usedIds[$namespaceId] ) ) {
					$errors[] = [ 'index' => $index, 'field' => 'id', 'code' => 'duplicate-id' ];
				}
				$usedIds[$namespaceId] = true;
			}

			$names = [
				'name' => [ $item['name'], $id ],
				'talkname' => [ $item['talkname'] ?? $item['name'] . '_talk', $id + 1 ],
			];
			foreach ( $names as $field => [ $name, $targetId ] ) {
				$key = $this->nameKey( $name );
				if ( isset( $usedNames[$key] ) && $usedNames[$key] !== $targetId ) {
					$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'duplicate-name' ];
				}
				$usedNames[$key] = $targetId;
			}
			foreach ( [ 'aliases', 'talkaliases' ] as $field ) {
				$targetId = $field === 'aliases' ? $id : $id + 1;
				foreach ( $item[$field] as $name ) {
					$key = $this->nameKey( $name );
					if ( isset( $usedNames[$key] ) && $usedNames[$key] !== $targetId ) {
						$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'duplicate-name' ];
					}
					$usedNames[$key] = $targetId;
				}
			}

			$normalized[] = $item;
		}

		if ( $errors !== [] ) {
			throw new ValidationException( $errors );
		}

		usort( $normalized, static fn ( array $a, array $b ): int => $a['id'] <=> $b['id'] );
		return $normalized;
	}

	/**
	 * @param array<string,mixed> $definition
	 * @param int $index
	 * @param array<int,array{index:int,field:string,code:string}> &$errors
	 * @return array<string,mixed>|null
	 */
	private function normalizeDefinition( array $definition, int $index, array &$errors ): ?array {
		$id = $definition['id'] ?? null;
		if ( !is_int( $id ) || $id < self::MIN_ID || $id > self::MAX_ID || $id % 2 !== 0 ) {
			$errors[] = [ 'index' => $index, 'field' => 'id', 'code' => 'invalid-id' ];
		}

		$name = $this->normalizeNameField( $definition['name'] ?? null, $index, 'name', $errors );
		$item = [
			'id' => $id,
			'name' => $name,
		];
		$talkName = null;
		if ( array_key_exists( 'talkname', $definition ) ) {
			$talkName = $this->normalizeNameField(
				$definition['talkname'],
				$index,
				'talkname',
				$errors
			);
			$item['talkname'] = $talkName;
		}

		foreach ( self::BOOLEAN_DEFAULTS as $field => $default ) {
			$value = $definition[$field] ?? $default;
			if ( !is_bool( $value ) ) {
				$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'not-boolean' ];
			}
			$item[$field] = is_bool( $value ) ? $value : $default;
		}

		foreach ( self::ARRAY_FIELDS as $field ) {
			$value = $definition[$field] ?? [];
			if ( !is_array( $value ) || !array_is_list( $value ) ) {
				$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'not-a-list' ];
				$value = [];
			}
			$item[$field] = $this->normalizeStringList(
				$value,
				$index,
				$field,
				$field === 'aliases' || $field === 'talkaliases',
				$errors
			);
		}

		$hasValidTalkName = !array_key_exists( 'talkname', $definition ) || $talkName !== null;
		return is_int( $id ) && $name !== null && $hasValidTalkName ? $item : null;
	}

	/**
	 * @param mixed $value
	 * @param int $index
	 * @param string $field
	 * @param array<int,array{index:int,field:string,code:string}> &$errors
	 */
	private function normalizeNameField( $value, int $index, string $field, array &$errors ): ?string {
		if ( !is_string( $value ) || trim( $value ) === '' || str_contains( $value, ':' ) ) {
			$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'invalid-name' ];
			return null;
		}

		$normalized = str_replace( ' ', '_', trim( $value ) );
		if ( strlen( $normalized ) > 255 ) {
			$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'name-too-long' ];
			return null;
		}
		return $normalized;
	}

	/**
	 * @param mixed[] $values
	 * @param int $index
	 * @param string $field
	 * @param bool $normalizeNames
	 * @param array<int,array{index:int,field:string,code:string}> &$errors
	 * @return string[]
	 */
	private function normalizeStringList(
		array $values,
		int $index,
		string $field,
		bool $normalizeNames,
		array &$errors
	): array {
		$result = [];
		foreach ( $values as $value ) {
			if ( !is_string( $value ) || trim( $value ) === '' ) {
				$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'invalid-list-value' ];
				continue;
			}
			$value = trim( $value );
			if ( $normalizeNames ) {
				if ( str_contains( $value, ':' ) ) {
					$errors[] = [ 'index' => $index, 'field' => $field, 'code' => 'invalid-name' ];
					continue;
				}
				$value = str_replace( ' ', '_', $value );
			}
			if ( !in_array( $value, $result, true ) ) {
				$result[] = $value;
			}
		}
		return $result;
	}

	private function nameKey( string $name ): string {
		return $this->contentLanguage->lc( str_replace( ' ', '_', $name ) );
	}
}

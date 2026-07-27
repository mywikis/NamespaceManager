<?php

namespace MediaWiki\Extension\NamespaceManager;

use MediaWiki\Language\Language;
use MediaWiki\Title\NamespaceInfo;

class NamespaceManager {

	private NamespaceValidator $validator;
	private NamespaceRepository $repository;
	private NamespaceInfo $namespaceInfo;
	private Language $contentLanguage;

	public function __construct(
		NamespaceValidator $validator,
		NamespaceRepository $repository,
		NamespaceInfo $namespaceInfo,
		Language $contentLanguage
	) {
		$this->validator = $validator;
		$this->repository = $repository;
		$this->namespaceInfo = $namespaceInfo;
		$this->contentLanguage = $contentLanguage;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getAll(): array {
		return $this->repository->getAll();
	}

	/**
	 * @param mixed $definitions
	 * @return array<int,array<string,mixed>>
	 */
	public function replaceAll( $definitions ): array {
		$normalized = $this->validate( $definitions );
		$this->repository->replaceAll( $normalized );
		return $normalized;
	}

	/**
	 * @param mixed $definitions
	 * @return array<int,array<string,mixed>>
	 */
	public function validate( $definitions ): array {
		$currentDefinitions = $this->repository->getAll();
		$currentIds = [];
		foreach ( $currentDefinitions as $definition ) {
			$currentIds[$definition['id']] = true;
			$currentIds[$definition['id'] + 1] = true;
		}

		$reservedNamespaces = array_filter(
			$this->namespaceInfo->getCanonicalNamespaces(),
			static fn ( int $id ): bool => !isset( $currentIds[$id] ),
			ARRAY_FILTER_USE_KEY
		);
		$reservedNames = [];
		foreach ( $this->contentLanguage->getNamespaceIds() as $name => $id ) {
			if ( !isset( $currentIds[$id] ) ) {
				$reservedNames[$name] = $id;
			}
		}
		return $this->validator->validate(
			$definitions,
			$reservedNamespaces,
			$reservedNames
		);
	}

	public function isEmpty(): bool {
		return $this->repository->isEmpty();
	}
}

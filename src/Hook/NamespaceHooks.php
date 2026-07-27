<?php

namespace MediaWiki\Extension\NamespaceManager\Hook;

use MediaWiki\Extension\NamespaceManager\NamespaceRepository;
use MediaWiki\Hook\CanonicalNamespacesHook;

class NamespaceHooks implements CanonicalNamespacesHook {

	private NamespaceRepository $repository;

	public function __construct( NamespaceRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @inheritDoc
	 */
	public function onCanonicalNamespaces( &$namespaces ): void {
		foreach ( $this->repository->getAll() as $definition ) {
			$id = $definition['id'];
			$namespaces[$id] = $definition['name'];
			$namespaces[$id + 1] = $definition['talkname'] ?? $definition['name'] . '_talk';
		}
	}
}

<?php

namespace MediaWiki\Extension\NamespaceManager;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;

class NamespaceConfiguration {

	private NamespaceRepository $repository;

	public function __construct( NamespaceRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getNamespaceInfoOverrides( Config $config ): array {
		$contentNamespaces = $config->get( MainConfigNames::ContentNamespaces );
		$subpageNamespaces = $config->get( MainConfigNames::NamespacesWithSubpages );
		$nonincludableNamespaces = $config->get( MainConfigNames::NonincludableNamespaces );

		foreach ( $this->repository->getAll() as $definition ) {
			$id = $definition['id'];
			if ( $definition['content'] && !in_array( $id, $contentNamespaces, true ) ) {
				$contentNamespaces[] = $id;
			}
			$subpageNamespaces[$id] = $definition['subpages'];
			$subpageNamespaces[$id + 1] = $definition['talksubpages'];
			if ( !$definition['includable'] &&
				!in_array( $id, $nonincludableNamespaces, true )
			) {
				$nonincludableNamespaces[] = $id;
			}
			if ( !$definition['talkincludable'] &&
				!in_array( $id + 1, $nonincludableNamespaces, true )
			) {
				$nonincludableNamespaces[] = $id + 1;
			}
		}

		return [
			MainConfigNames::ContentNamespaces => $contentNamespaces,
			MainConfigNames::NamespacesWithSubpages => $subpageNamespaces,
			MainConfigNames::NonincludableNamespaces => $nonincludableNamespaces,
		];
	}

	public function applyGlobalConfiguration(): void {
		global $wgNamespaceAliases;
		global $wgNamespacesToBeSearchedDefault;
		global $wgVisualEditorAvailableNamespaces;

		$wgNamespaceAliases ??= [];
		$wgNamespacesToBeSearchedDefault ??= [];
		$wgVisualEditorAvailableNamespaces ??= [];

		foreach ( $this->repository->getAll() as $definition ) {
			$id = $definition['id'];
			foreach ( $definition['aliases'] as $alias ) {
				$wgNamespaceAliases[$alias] = $id;
			}
			foreach ( $definition['talkaliases'] as $alias ) {
				$wgNamespaceAliases[$alias] = $id + 1;
			}
			$wgNamespacesToBeSearchedDefault[$id] = $definition['searchdefault'];
			$wgNamespacesToBeSearchedDefault[$id + 1] = $definition['talksearchdefault'];
			if ( $definition['visualeditor'] ) {
				$wgVisualEditorAvailableNamespaces[$definition['name']] = true;
			}
		}
	}
}

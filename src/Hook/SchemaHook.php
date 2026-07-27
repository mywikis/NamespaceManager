<?php

namespace MediaWiki\Extension\NamespaceManager\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHook implements LoadExtensionSchemaUpdatesHook {

	public static function newFromGlobalState(): self {
		return new self();
	}

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$dbType = $updater->getDB()->getType();
		$schemaFile = dirname( __DIR__, 2 ) . "/sql/$dbType/tables-generated.sql";
		$updater->addExtensionTable( 'namespacemanager_namespace', $schemaFile );
	}
}

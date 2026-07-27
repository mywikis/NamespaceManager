<?php

namespace MediaWiki\Extension\NamespaceManager\Hook;

use ExtensionRegistry;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\NamespaceManager\NamespaceConfiguration;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\NamespaceInfo;

class ServiceBootstrapHook implements MediaWikiServicesHook {

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ): void {
		$services->redefineService(
			'NamespaceInfo',
			static function ( MediaWikiServices $services ): NamespaceInfo {
				/** @var NamespaceConfiguration $configuration */
				$configuration = $services->getService( 'NamespaceManager.Configuration' );
				$mainConfig = $services->getMainConfig();
				$configuration->applyGlobalConfiguration();
				$overrides = $configuration->getNamespaceInfoOverrides( $mainConfig );

				return new NamespaceInfo(
					new ServiceOptions(
						NamespaceInfo::CONSTRUCTOR_OPTIONS,
						$overrides,
						$mainConfig
					),
					$services->getHookContainer(),
					ExtensionRegistry::getInstance()->getAttribute( 'ExtensionNamespaces' ),
					ExtensionRegistry::getInstance()->getAttribute(
						'ImmovableNamespaces'
					)
				);
			}
		);
	}
}

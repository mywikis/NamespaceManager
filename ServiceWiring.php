<?php

use MediaWiki\Extension\NamespaceManager\NamespaceConfiguration;
use MediaWiki\Extension\NamespaceManager\NamespaceManager;
use MediaWiki\Extension\NamespaceManager\NamespaceRepository;
use MediaWiki\Extension\NamespaceManager\NamespaceValidator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'NamespaceManager.Validator' =>
		static function ( MediaWikiServices $services ): NamespaceValidator {
			return new NamespaceValidator( $services->getContentLanguage() );
		},
	'NamespaceManager.Repository' =>
		static function ( MediaWikiServices $services ): NamespaceRepository {
			return new NamespaceRepository(
				$services->getConnectionProvider(),
				$services->getMainWANObjectCache(),
				LoggerFactory::getInstance( 'NamespaceManager' )
			);
		},
	'NamespaceManager.Configuration' =>
		static function ( MediaWikiServices $services ): NamespaceConfiguration {
			return new NamespaceConfiguration(
				$services->getService( 'NamespaceManager.Repository' )
			);
		},
	'NamespaceManager.Manager' =>
		static function ( MediaWikiServices $services ): NamespaceManager {
			return new NamespaceManager(
				$services->getService( 'NamespaceManager.Validator' ),
				$services->getService( 'NamespaceManager.Repository' ),
				$services->getNamespaceInfo(),
				$services->getContentLanguage()
			);
		},
];

<?php

namespace MediaWiki\Extension\NamespaceManager\Maintenance;

use JsonException;
use Maintenance;
use MediaWiki\Extension\NamespaceManager\NamespaceManager;
use MediaWiki\Extension\NamespaceManager\ValidationException;

$maintenancePath = getenv( 'MW_INSTALL_PATH' ) ?: dirname( __DIR__, 3 );
require_once "$maintenancePath/maintenance/Maintenance.php";

class ConvertNamespaceJson extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Convert a NamespaceManager 1.x namespaces.json file into the 2.0 database table.'
		);
		$this->addOption( 'file', 'Path to namespaces.json.', false, true );
		$this->addOption(
			'replace',
			'Replace all existing database definitions after validation.'
		);
		$this->addOption( 'dry-run', 'Validate and report without writing to the database.' );
		$this->requireExtension( 'NamespaceManager' );
	}

	public function execute(): void {
		$services = $this->getServiceContainer();
		$config = $services->getMainConfig();
		$file = $this->getOption( 'file' );
		if ( $file === null ) {
			$file = str_replace(
				'$1',
				$config->get( 'DBname' ),
				$config->get( 'NamespaceManagerDataPath' )
			);
			if ( !str_starts_with( $file, '/' ) ) {
				$file = dirname( __DIR__ ) . '/' . $file;
			}
		}

		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$json = @file_get_contents( $file );
		if ( $json === false ) {
			$this->fatalError( "Could not read namespace definitions from $file." );
		}

		$definitions = [];
		try {
			$definitions = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$this->fatalError( "The namespace file is not valid JSON: {$e->getMessage()}" );
		}

		/** @var NamespaceManager $manager */
		$manager = $services->getService( 'NamespaceManager.Manager' );
		if ( !$this->hasOption( 'dry-run' ) &&
			!$manager->isEmpty() &&
			!$this->hasOption( 'replace' )
		) {
			$this->fatalError(
				'The NamespaceManager table is not empty. Use --replace to overwrite it.'
			);
		}

		$normalized = [];
		try {
			$normalized = $manager->validate( $definitions );
		} catch ( ValidationException $e ) {
			foreach ( $e->getErrors() as $error ) {
				$this->error(
					"Definition {$error['index']}, {$error['field']}: {$error['code']}"
				);
			}
			$this->fatalError( 'The namespace file failed validation.' );
		}

		if ( $this->hasOption( 'dry-run' ) ) {
			$this->output( 'Validated ' . count( $normalized ) . " namespace definitions.\n" );
			return;
		}

		$manager->replaceAll( $normalized );
		$this->output( 'Imported ' . count( $normalized ) . " namespace definitions.\n" );
	}
}

$maintClass = ConvertNamespaceJson::class;
require_once RUN_MAINTENANCE_IF_MAIN;

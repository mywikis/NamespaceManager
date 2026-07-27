<?php

$vendorPaths = [ dirname( __DIR__ ) . '/vendor' ];
$mediaWikiInstallPath = getenv( 'MW_INSTALL_PATH' );
if ( $mediaWikiInstallPath !== false ) {
	array_unshift( $vendorPaths, "$mediaWikiInstallPath/vendor" );
}
$mediaWikiVendorPath = getenv( 'MW_VENDOR_PATH' );
if ( $mediaWikiVendorPath !== false ) {
	array_unshift( $vendorPaths, "$mediaWikiVendorPath/vendor", $mediaWikiVendorPath );
}

$phanConfig = null;
foreach ( $vendorPaths as $vendorPath ) {
	$candidate = "$vendorPath/mediawiki/mediawiki-phan-config/src/config.php";
	if ( file_exists( $candidate ) ) {
		$phanConfig = $candidate;
		break;
	}
}
if ( $phanConfig === null ) {
	throw new RuntimeException( 'Could not locate mediawiki/mediawiki-phan-config.' );
}

$cfg = require $phanConfig;
return $cfg;

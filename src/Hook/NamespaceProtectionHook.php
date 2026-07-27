<?php

namespace MediaWiki\Extension\NamespaceManager\Hook;

use MediaWiki\Extension\NamespaceManager\NamespaceRepository;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;

class NamespaceProtectionHook implements GetUserPermissionsErrorsHook {

	private const EXEMPT_ACTIONS = [
		'create',
		'deletedhistory',
		'deletedtext',
		'read',
		'viewsuppressed',
	];

	private NamespaceRepository $repository;
	private PermissionManager $permissionManager;

	public function __construct(
		NamespaceRepository $repository,
		PermissionManager $permissionManager
	) {
		$this->repository = $repository;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( in_array( $action, self::EXEMPT_ACTIONS, true ) ) {
			return;
		}

		$namespaceId = $title->getNamespace();
		foreach ( $this->repository->getAll() as $definition ) {
			if ( $namespaceId === $definition['id'] ) {
				$rights = $definition['editpermissions'];
			} elseif ( $namespaceId === $definition['id'] + 1 ) {
				$rights = $definition['talkeditpermissions'];
			} else {
				continue;
			}

			if ( $rights !== [] &&
				!$this->permissionManager->userHasAllRights( $user, ...$rights )
			) {
				$result = [ 'namespaceprotected', $title->getNsText(), $action ];
				return false;
			}
			return;
		}
	}
}

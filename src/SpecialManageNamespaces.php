<?php

namespace MediaWiki\Extension\NamespaceManager;

use Html;
use SpecialPage;

class SpecialManageNamespaces extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ManageNamespaces', 'managenamespaces' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$this->checkPermissions();
		$this->setHeaders();

		$output = $this->getOutput();
		$output->setPageTitleMsg( $this->msg( 'managenamespaces-title' ) );
		$output->addWikiMsg( 'managenamespaces-intro' );
		$output->addModuleStyles( 'ext.namespaceManager.manage.styles' );
		$output->addModules( 'ext.namespaceManager.manage' );
		$output->addHTML(
			Html::rawElement(
				'div',
				[ 'id' => 'namespacemanager-app' ],
				Html::element(
					'p',
					[ 'class' => 'namespacemanager-nojs' ],
					$this->msg( 'namespacemanager-javascript-required' )->text()
				)
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName(): string {
		return 'wiki';
	}
}

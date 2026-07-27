<?php

namespace MediaWiki\Extension\NamespaceManager\Api;

use ApiBase;
use ApiMain;
use JsonException;
use MediaWiki\Extension\NamespaceManager\NamespaceManager;
use MediaWiki\Extension\NamespaceManager\ValidationException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ReadOnlyMode;

class ApiNamespaceManager extends ApiBase {

	private NamespaceManager $namespaceManager;
	private ReadOnlyMode $readOnlyMode;

	public function __construct(
		ApiMain $main,
		string $action,
		NamespaceManager $namespaceManager,
		ReadOnlyMode $readOnlyMode
	) {
		parent::__construct( $main, $action );
		$this->namespaceManager = $namespaceManager;
		$this->readOnlyMode = $readOnlyMode;
	}

	public function execute(): void {
		if ( !$this->getAuthority()->isAllowed( 'managenamespaces' ) ) {
			$this->dieWithError(
				[
					'apierror-permissiondenied',
					$this->msg( 'action-managenamespaces' )->text(),
				],
				'permissiondenied'
			);
		}

		$params = $this->extractRequestParams();
		if ( $params['operation'] === 'get' ) {
			$this->getResult()->addValue(
				null,
				'namespacemanager',
				[ 'namespaces' => $this->namespaceManager->getAll() ]
			);
			return;
		}

		if ( $params['namespaces'] === null ) {
			$this->dieWithError(
				'apierror-namespacemanager-missing-namespaces',
				'missingnamespaces'
			);
		}

		if ( $this->readOnlyMode->isReadOnly() ) {
			$this->dieReadOnly();
		}

		try {
			$definitions = json_decode(
				$params['namespaces'],
				true,
				512,
				JSON_THROW_ON_ERROR
			);
			$saved = $this->namespaceManager->replaceAll( $definitions );
		} catch ( JsonException $e ) {
			$this->dieWithError(
				'apierror-namespacemanager-invalid-json',
				'invalidjson'
			);
		} catch ( ValidationException $e ) {
			$this->dieWithError(
				'apierror-namespacemanager-validation',
				'invalidnamespaces',
				[ 'errors' => $e->getErrors() ]
			);
		}

		$this->getResult()->addValue(
			null,
			'namespacemanager',
			[ 'namespaces' => $saved ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return $this->getRequest()->getVal( 'operation' ) === 'save' ? 'csrf' : false;
	}

	public function mustBePosted(): bool {
		return true;
	}

	public function isWriteMode(): bool {
		return $this->getRequest()->getVal( 'operation' ) === 'save';
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams(): array {
		return [
			'operation' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [ 'get', 'save' ],
			],
			'namespaces' => [
				ParamValidator::PARAM_DEFAULT => null,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=namespacemanager&operation=get'
				=> 'apihelp-namespacemanager-example-get',
			'action=namespacemanager&operation=save&namespaces=[]&token=123ABC'
				=> 'apihelp-namespacemanager-example-save',
		];
	}
}

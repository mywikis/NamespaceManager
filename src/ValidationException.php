<?php

namespace MediaWiki\Extension\NamespaceManager;

use RuntimeException;

class ValidationException extends RuntimeException {

	/** @var array<int,array{index:int,field:string,code:string}> */
	private array $errors;

	/**
	 * @param array<int,array{index:int,field:string,code:string}> $errors
	 */
	public function __construct( array $errors ) {
		parent::__construct( 'Namespace definitions failed validation.' );
		$this->errors = $errors;
	}

	/**
	 * @return array<int,array{index:int,field:string,code:string}>
	 */
	public function getErrors(): array {
		return $this->errors;
	}
}

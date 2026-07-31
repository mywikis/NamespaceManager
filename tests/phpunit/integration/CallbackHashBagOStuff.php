<?php

namespace MediaWiki\Extension\NamespaceManager\Tests\Integration;

use Wikimedia\ObjectCache\HashBagOStuff;

class CallbackHashBagOStuff extends HashBagOStuff {

	/** @var callable|null */
	private $afterNextSet;

	public function afterNextSet( callable $callback ): void {
		$this->afterNextSet = $callback;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $exptime
	 * @param int $flags
	 * @return bool
	 */
	protected function doSet( $key, $value, $exptime = 0, $flags = 0 ) {
		$result = parent::doSet( $key, $value, $exptime, $flags );
		if ( $this->afterNextSet !== null ) {
			$callback = $this->afterNextSet;
			$this->afterNextSet = null;
			$callback();
		}
		return $result;
	}
}

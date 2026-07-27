<?php

namespace MediaWiki\Extension\NamespaceManager\Tests\Unit;

use MediaWiki\Extension\NamespaceManager\NamespaceValidator;
use MediaWiki\Extension\NamespaceManager\ValidationException;
use MediaWiki\Language\Language;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\NamespaceManager\NamespaceValidator
 */
class NamespaceValidatorTest extends MediaWikiUnitTestCase {

	public function testNormalizesDefaultsAndNames(): void {
		$validator = $this->newValidator();
		$result = $validator->validate( [
			[
				'id' => 3000,
				'name' => 'Human Resources',
				'aliases' => [ 'HR Pages' ],
			],
		] );

		$this->assertSame( 3000, $result[0]['id'] );
		$this->assertSame( 'Human_Resources', $result[0]['name'] );
		$this->assertArrayNotHasKey( 'talkname', $result[0] );
		$this->assertSame( [ 'HR_Pages' ], $result[0]['aliases'] );
		$this->assertFalse( $result[0]['content'] );
		$this->assertTrue( $result[0]['includable'] );
		$this->assertSame( [], $result[0]['editpermissions'] );
	}

	public function testSortsByNamespaceId(): void {
		$validator = $this->newValidator();
		$result = $validator->validate( [
			[ 'id' => 3004, 'name' => 'Later' ],
			[ 'id' => 3000, 'name' => 'Earlier' ],
		] );

		$this->assertSame( [ 3000, 3004 ], array_column( $result, 'id' ) );
	}

	/**
	 * @dataProvider provideInvalidDefinitions
	 * @param mixed $definitions
	 * @param string $expectedCode
	 */
	public function testRejectsInvalidDefinitions( $definitions, string $expectedCode ): void {
		$validator = $this->newValidator();

		try {
			$validator->validate( $definitions );
			$this->fail( 'ValidationException was not thrown.' );
		} catch ( ValidationException $e ) {
			$this->assertContains( $expectedCode, array_column( $e->getErrors(), 'code' ) );
		}
	}

	/**
	 * @return iterable<string,array{mixed,string}>
	 */
	public static function provideInvalidDefinitions(): iterable {
		yield 'not a list' => [ [ 'id' => 3000 ], 'not-a-list' ];
		yield 'odd ID' => [ [ [ 'id' => 3001, 'name' => 'Odd' ] ], 'invalid-id' ];
		yield 'ID out of range' => [
			[ [ 'id' => 5000, 'name' => 'Too high' ] ],
			'invalid-id',
		];
		yield 'duplicate pair' => [
			[
				[ 'id' => 3000, 'name' => 'First' ],
				[ 'id' => 3000, 'name' => 'Second' ],
			],
			'duplicate-id',
		];
		yield 'duplicate alias' => [
			[
				[ 'id' => 3000, 'name' => 'First', 'aliases' => [ 'Shared' ] ],
				[ 'id' => 3002, 'name' => 'Second', 'aliases' => [ 'Shared' ] ],
			],
			'duplicate-name',
		];
		yield 'subject and talk alias collision' => [
			[
				[
					'id' => 3000,
					'name' => 'First',
					'aliases' => [ 'Shared' ],
					'talkaliases' => [ 'Shared' ],
				],
			],
			'duplicate-name',
		];
		yield 'name exceeds database limit' => [
			[ [ 'id' => 3000, 'name' => str_repeat( 'a', 256 ) ] ],
			'name-too-long',
		];
		yield 'Unicode case collision' => [
			[
				[ 'id' => 3000, 'name' => 'Ärea' ],
				[ 'id' => 3002, 'name' => 'ärea' ],
			],
			'duplicate-name',
		];
	}

	public function testRejectsReservedNamespace(): void {
		$validator = $this->newValidator();
		$this->expectException( ValidationException::class );
		$validator->validate(
			[ [ 'id' => 3000, 'name' => 'First' ] ],
			[ 3000 => 'Reserved' ]
		);
	}

	public function testRejectsLocalizedAlias(): void {
		$validator = $this->newValidator();
		$this->expectException( ValidationException::class );
		$validator->validate(
			[ [ 'id' => 3000, 'name' => 'Image' ] ],
			[],
			[ 'Image' => NS_FILE ]
		);
	}

	private function newValidator(): NamespaceValidator {
		$language = $this->createMock( Language::class );
		$language->method( 'lc' )->willReturnCallback(
			static fn ( string $value ): string => mb_strtolower( $value )
		);
		return new NamespaceValidator( $language );
	}
}

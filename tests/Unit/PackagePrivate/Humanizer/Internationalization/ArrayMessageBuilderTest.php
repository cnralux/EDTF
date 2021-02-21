<?php

declare( strict_types = 1 );

namespace EDTF\Tests\Unit\PackagePrivate\Humanizer\Internationalization;

use EDTF\PackagePrivate\Humanizer\Internationalization\ArrayMessageBuilder;
use EDTF\PackagePrivate\Humanizer\Internationalization\UnknownMessageKey;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EDTF\PackagePrivate\Humanizer\Internationalization\ArrayMessageBuilder
 */
class ArrayMessageBuilderTest extends TestCase {

	public function testWhenMessageKeyIsNotKnown_exceptionIsThrown(): void {
		$builder = new ArrayMessageBuilder( [] );

		$this->expectException( UnknownMessageKey::class );
		$builder->buildMessage( 'unknown-message' );
	}

	public function testSimpleMessage(): void {
		$builder = new ArrayMessageBuilder( [
			'wrong-key' => 'Wrong result',
			'right-key' => 'Right result',
			'another-wrong-key' => 'Another wrong result',
		] );

		$this->assertSame(
			'Right result',
			$builder->buildMessage( 'right-key' )
		);
	}

	public function testParameters(): void {
		$builder = new ArrayMessageBuilder( [
			'right-key' => 'This was written by $2 on $1 ($1)',
		] );

		$this->assertSame(
			'This was written by Jeroen De Dauw on 2021-02-21 (2021-02-21)',
			$builder->buildMessage( 'right-key', '2021-02-21', 'Jeroen De Dauw' )
		);
	}
	
}

<?php

namespace LePhare\Import\Util;

use PHPUnit\Framework\TestCase;

/**
 * @covers \LePhare\Import\Util\Transliterator
 *
 * @internal
 */
class TransliteratorTest extends TestCase
{
    /**
     * @dataProvider provideUrlize
     */
    public function testUrlize(string $input, string $expected): void
    {
        $this->assertEquals($expected, Transliterator::urlize($input));
    }

    public static function provideUrlize(): iterable
    {
        yield ['foo', 'foo'];
        yield ['FOO', 'foo'];
        yield ['Hello World', 'hello-world'];
        yield ['Hello World!', 'hello-world'];
        yield ['hello world', 'hello-world'];
        yield ['hello-world', 'hello-world'];
        yield ['hello_world', 'hello-world'];
        yield ['hello world!', 'hello-world'];
        yield ['helloWorld', 'helloworld'];
        yield ['HelloWorld', 'helloworld'];
        yield ['N° Commande', 'n-commande'];
        yield ['Désignation', 'designation'];
    }
}

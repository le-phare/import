<?php

namespace LePhare\Import\Util;

use Behat\Transliterator\Transliterator as BehatTransliterator;
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
    public function testUrlize(string $input, string $separator, string $expected): void
    {
        $this->assertEquals(BehatTransliterator::urlize($input, $separator), $expected, "Wrong expected value: replace it by Behat's Transliterator output");
        $this->assertEquals($expected, Transliterator::urlize($input, $separator));
    }

    public static function provideUrlize(): iterable
    {
        yield ['foo', '-', 'foo'];
        yield ['FOO', '-', 'foo'];
        yield ['Hello World', '-', 'hello-world'];
        yield ['Hello World!', '-', 'hello-world'];
        yield ['hello world', '-', 'hello-world'];
        yield ['hello-world', '-', 'hello-world'];
        yield ['hello_world', '-', 'hello-world'];
        yield ['hello world!', '-', 'hello-world'];
        yield ['helloWorld', '-', 'helloworld'];
        yield ['HelloWorld', '-', 'helloworld'];
        yield ['N° Commande', '-', 'n-commande'];
        yield ['Désignation', '-', 'designation'];
        yield ["Attention à l'apostrophe", '-', 'attention-a-lapostrophe'];
        yield ["'citation' test", '-', 'citation-test'];
        yield ["'citation'test", '-', 'citationtest'];

        yield ['foo', '_', 'foo'];
        yield ['FOO', '_', 'foo'];
        yield ['Hello World', '_', 'hello_world'];
        yield ['Hello World!', '_', 'hello_world'];
        yield ['hello world', '_', 'hello_world'];
        yield ['hello-world', '_', 'hello_world'];
        yield ['hello_world', '_', 'hello_world'];
        yield ['hello world!', '_', 'hello_world'];
        yield ['helloWorld', '_', 'helloworld'];
        yield ['HelloWorld', '_', 'helloworld'];
        yield ['N° Commande', '_', 'n_commande'];
        yield ['Désignation', '_', 'designation'];
        yield ["Attention à l'apostrophe", '_', 'attention_a_lapostrophe'];
        yield ["'citation' test", '_', 'citation_test'];
        yield ["'citation'test", '_', 'citationtest'];
    }
}

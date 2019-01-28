<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArgumentsComplex
{
    protected $bar;
    protected $foo;

    public function __construct(string $bar, string $foo)
    {
        $this->bar = $bar;
        $this->foo = $foo;
    }

    public function setFooBar(array $foobar)
    {
        $this->bar = $foobar['bar'];
        $this->foo = $foobar['foo'];
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function setFoo(string $foo)
    {
        $this->foo = $foo;
    }

    public function getBar(): string
    {
        return $this->bar;
    }

    public function setBar(string $bar)
    {
        $this->bar = $bar;
    }
}

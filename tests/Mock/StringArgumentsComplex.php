<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArgumentsComplex
{
    protected $bar;
    protected $foo;
    protected $foolist = [];

    public function __construct(string $bar, string $foo = 'bar')
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

    public function addFooToList(string $bar)
    {
        $this->foolist[] = $bar;
    }

    public function getFooList()
    {
        return $this->foolist;
    }
}

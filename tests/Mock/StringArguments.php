<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArguments
{
    protected $foo;
    protected $bar;

    public function __construct(string $foo = 'foo')
    {
        $this->foo = $foo;
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function setFoo(string $foo)
    {
        $this->foo = $foo;
    }

    public function setBar(?string $bar)
    {
        $this->bar = $bar;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public static function factory($foo)
    {
        return new self($foo);
    }
}

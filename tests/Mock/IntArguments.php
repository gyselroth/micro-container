<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class IntArguments
{
    protected $foo;

    public function __construct(int $foo = 0)
    {
        $this->foo = $foo;
    }

    public function getFoo(): int
    {
        return $this->foo;
    }

    public function setFoo(int $foo)
    {
        $this->foo = $foo;
    }
}

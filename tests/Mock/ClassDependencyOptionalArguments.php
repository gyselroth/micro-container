<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class ClassDependencyOptionalArguments
{
    protected $foo;

    public function __construct(StringArguments $foo = null)
    {
        $this->foo = $foo;
    }

    public function setFoo(StringArguments $foo)
    {
        $this->foo = $foo;
    }

    public function getDependency(): ?StringArguments
    {
        return $this->foo;
    }

    public function getFoo(): string
    {
        return $this->foo->getFoo();
    }
}

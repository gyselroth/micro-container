<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class ClassDependencyRequiredArguments
{
    protected $foo;
    protected $bar;

    public function __construct(StringArguments $foo)
    {
        $this->foo = $foo;
    }

    public function getDependency(): StringArguments
    {
        return $this->foo;
    }

    public function getFoo(): string
    {
        return $this->foo->getFoo();
    }

    public function setBar(ClassDependencyOptionalArguments $bar)
    {
        $this->bar = $bar;
    }

    public function getBar()
    {
        return $this->bar;
    }
}

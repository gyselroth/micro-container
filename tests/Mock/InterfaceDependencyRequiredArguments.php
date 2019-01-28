<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class InterfaceDependencyRequiredArguments
{
    protected $foo;

    public function __construct(StringArgumentsInterface $foo)
    {
        $this->foo = $foo;
    }

    public function getDependency(): StringArgumentsInterface
    {
        return $this->foo;
    }

    public function getFoo(): string
    {
        return $this->foo->getFoo();
    }
}

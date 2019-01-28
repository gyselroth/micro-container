<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class ClassDependencySelf
{
    protected $foo;

    public function __construct(self $foo)
    {
        $this->foo = $foo;
    }

    public function getFoo(): self
    {
        return $this->foo;
    }
}

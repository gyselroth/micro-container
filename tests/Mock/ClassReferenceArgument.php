<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class ClassReferenceArgument
{
    protected $foo;

    public function __construct(&$foo = 'bar')
    {
        $this->foo = &$foo;
    }

    public function getFoo()
    {
        return $this->foo;
    }
}

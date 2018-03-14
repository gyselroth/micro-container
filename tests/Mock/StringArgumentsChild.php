<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArgumentsChild extends StringArguments implements StringArgumentsInterface
{
    public function isChild(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);
/**
 * Micro\Container
 *
 * @copyright   Copyright (c) 2018-2026 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArgumentsImplementation extends StringArguments implements StringArgumentsInterface
{
    public function isChild(): bool
    {
        return true;
    }
}

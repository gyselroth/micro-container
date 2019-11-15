<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

use Closure as Callback;

class Closure
{
    protected $value;

    public function set(Callback $test)
    {
        $this->value = $test;
    }

    public function get(): Callback
    {
        return $this->value;
    }
}

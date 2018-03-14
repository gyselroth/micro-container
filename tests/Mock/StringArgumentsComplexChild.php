<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite\Mock;

class StringArgumentsComplexChild extends StringArguments implements StringArgumentsInterface
{
    protected $bar;
    protected $foobar;

    public function __construct(string $foo = 'foo', string $bar = 'bar', string $foobar = 'foobar')
    {
        parent::__construct($foo);
        $this->bar = $bar;
        $this->foobar = $foobar;
    }

    public function getBar(): string
    {
        return $this->bar;
    }

    public function getFoobar(): string
    {
        return $this->foobar;
    }

    public function isChild(): bool
    {
        return true;
    }
}

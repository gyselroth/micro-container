<?php

declare(strict_types=1);

/**
 * Micro
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2015-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFound extends \Exception implements NotFoundExceptionInterface
{
}

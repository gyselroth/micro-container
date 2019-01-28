<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * Container.
     *
     * @var RuntimeContainer
     */
    protected $container;

    /**
     * Create container.
     */
    public function __construct(array $config = [], ?ContainerInterface $parent = null)
    {
        $this->container = new RuntimeContainer($config, $parent, $this);
    }

    /**
     * Build an entry of the container by its name.
     */
    public function make(string $name, array $parameters = [])
    {
        return $this->container->get($name, $parameters);
    }

    /**
     * Get service.
     */
    public function get($name)
    {
        return $this->container->get($name);
    }

    /**
     * Check if service is registered.
     */
    public function has($name): bool
    {
        try {
            $this->container->get($name);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
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
     *
     * @param iterable           $config
     * @param ContainerInterface $parent
     */
    public function __construct(Iterable $config = [], ?ContainerInterface $parent = null)
    {
        $this->container = new RuntimeContainer($config, $parent);
    }

    /**
     * Get service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        return $this->container->get($name);
    }

    /**
     * Check if service is registered.
     *
     * @param string $name
     *
     * @return bool
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

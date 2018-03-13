<?php

declare(strict_types=1);

/**
 * Micro
 *
 * @copyright   Copryright (c) 2015-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use Psr\Container\ContainerInterface;

class Config
{
    /**
     * Config.
     *
     * @var iterable
     */
    protected $config = [];

    /**
     * Compiled config.
     *
     * @var array
     */
    protected $compiled = [];

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create container.
     *
     * @param iterable $config
     */
    public function __construct(Iterable $config, ContainerInterface $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * Get config.
     *
     * @return iterable
     */
    public function getConfig(): Iterable
    {
        return $this->config;
    }

    /**
     * Check if service is known to container config.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /**
     * Get service configuration.
     *
     * @param string $name
     *
     * @return array
     */
    public function get(string $name): array
    {
        if (isset($this->compiled[$name])) {
            $config = $this->compiled[$name];
        } else {
            $this->compiled[$name] = $this->createServiceConfig($name);
            $config = $this->compiled[$name];
        }

        if (!isset($config['use'])) {
            $config['use'] = $name;
        }

        return $config;
    }

    /**
     * Parse env param.
     *
     * @param string $param
     *
     * @return string
     */
    public function getEnv(string $param): string
    {
        if (preg_match_all('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)([^}]*))\)\}#', $param, $matches)) {
            if (4 !== count($matches)) {
                return $param;
            }

            for ($i = 0; $i < 1; ++$i) {
                $param = $this->parseEnv($param, $matches, $i);
            }

            return $param;
        }

        return $param;
    }

    /**
     * Parse env.
     *
     * @param string $param
     * @param array  $variables
     * @param int    $key
     *
     * @return string
     */
    protected function parseEnv(string $param, array $variables, int $key): string
    {
        $env = getenv($variables[1][$key]);
        if (false === $env && !empty($variables[3][$key])) {
            return str_replace($variables[0][$key], $variables[3][$key], $param);
        }
        if (false === $env) {
            throw new Exception\EnvVariableNotFound('env variable '.$variables[1][$key].' required but it is neither set not a default value exists');
        }

        return str_replace($variables[0][$key], $env, $param);
    }

    /**
     * Create service config.
     *
     * @param string $name
     *
     * @return array
     */
    protected function createServiceConfig(string $name): array
    {
        $config = [];
        if ($this->has($name)) {
            $config = $this->config[$name];
        }

        $class = $name;
        if (isset($config['use'])) {
            if (!is_string($config['use'])) {
                throw new Exception\InvalidConfiguration('use must be a string for service '.$name);
            }

            $class = $config['use'];
        }

        if (preg_match('#^\{(.*)\}$#', $class)) {
            return $config;
        }

        return $this->mergeServiceConfig($name, $class, $config);
    }

    /**
     * Find parent classes or interfaces and merge service configurations.
     *
     * @param string $name
     * @param string $class
     * @param array  $config
     *
     * @return array
     */
    protected function mergeServiceConfig(string $name, string $class, array $config): array
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return $config;
        }

        if (isset($this->config[$name]['merge']) && $this->config[$name]['merge'] === false) {
            return $this->config[$name];
        }

        $tree = $this->getConfigTree();
        $parents = array_merge(class_implements($class), class_parents($class));
        foreach ($tree as $parent_config) {
            foreach ($parents as $parent) {
                if (isset($parent_config[$parent])) {
                    $config = array_replace_recursive($config, $parent_config[$parent]);
                }
            }

            if (isset($parent_config[$name])) {
                $config = array_replace_recursive($config, $parent_config[$name]);
            }
        }

        return $config;
    }

    /**
     * Get config tree.
     *
     * @return array
     */
    protected function getConfigTree(): array
    {
        $tree = [$this->getConfig()];
        $parent = $this->container;
        while ($parent = $parent->getParent()) {
            $tree[] = $parent->getConfig()->getConfig();
        }

        return $tree;
    }
}

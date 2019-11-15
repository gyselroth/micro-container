<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

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
     * @var RuntimeContainer
     */
    protected $container;

    /**
     * Create container.
     */
    public function __construct(iterable $config, RuntimeContainer $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * Get config.
     */
    public function getConfig(): iterable
    {
        return $this->config;
    }

    /**
     * Check if service is known to container config.
     */
    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /**
     * Get service configuration.
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
     */
    public function getEnv(string $param, string $type = 'string')
    {
        if (preg_match_all('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)(.*?))\)(?:\(([a-z]+)\))?\}#', $param, $matches)) {
            for ($i = 0; $i < count($matches[0]); ++$i) {
                $param = $this->parseEnv($param, $matches, $i, $type);
            }

            return $param;
        }

        return $param;
    }

    /**
     * Parse env.
     */
    protected function parseEnv(string $param, array $variables, int $key, string $type = 'string')
    {
        $type = $type ?? 'string';
        $value = null;

        $env = getenv($variables[1][$key]);
        if (false === $env && !empty($variables[3][$key])) {
            $value = str_replace($variables[0][$key], $variables[3][$key], $param);
        } elseif (false === $env) {
            throw new Exception\EnvVariableNotFound('env variable '.$variables[1][$key].' required but it is neither set not a default value exists');
        } else {
            $value = str_replace($variables[0][$key], $env, $param);
        }

        if (!empty($variables[4][$key])) {
            $type = $variables[4][$key];
        }

        if ('json' === $type) {
            return json_decode($value, true);
        }

        settype($value, $type);

        return $value;
    }

    /**
     * Create service config.
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

            $class = $config['use'] = $this->getEnv($config['use']);
        }

        if (preg_match('#^\{([^{}]+)\}$#', $class)) {
            $config = array_merge($this->getServiceDefaults(), $config);

            return $config;
        }

        $config = $this->mergeServiceConfig($name, $class, $config);

        if (isset($config['use'])) {
            $class = $config['use'] = $this->getEnv($config['use']);
        }

        if (!class_exists($class)) {
            throw new Exception\InvalidConfiguration('class '.$class.' is either not a class or can not be found');
        }

        return $config;
    }

    /**
     * Get service defaults.
     */
    protected function getServiceDefaults(): array
    {
        return [
            'merge' => true,
            'singleton' => true,
            'lazy' => false,
            'wrap' => false,
            'calls' => [],
            'selects' => [],
        ];
    }

    /**
     * Find parent classes or interfaces and merge service configurations.
     */
    protected function mergeServiceConfig(string $name, string $class, array $config): array
    {
        $config = array_merge($this->getServiceDefaults(), $config);

        if (!class_exists($class) && !interface_exists($class)) {
            return $config;
        }

        if (false === $config['merge']) {
            return $config;
        }

        $tree = $this->getConfigTree();
        $parents = array_merge(class_implements($class), class_parents($class));
        foreach ($tree as $parent_config) {
            foreach ($parents as $parent) {
                if (isset($parent_config[$parent])) {
                    $config = array_replace_recursive($parent_config[$parent], $config);
                }
            }

            if (isset($parent_config[$name])) {
                $config = array_replace_recursive($parent_config[$name], $config);
            }
        }

        return $config;
    }

    /**
     * Get config tree.
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

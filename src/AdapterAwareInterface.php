<?php

declare(strict_types=1);

/**
 * Micro
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2015-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

interface AdapterAwareInterface
{
    /**
     * Get default adapter.
     *
     * @return array
     */
    public function getDefaultAdapter(): array;

    /**
     * Has adapter.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAdapter(string $name): bool;

    /**
     * Inject adapter.
     *
     * @param mixed  $adapter
     * @param string $name
     *
     * @return AdapterAwareInterface
     */
    public function injectAdapter($adapter, ?string $name = null): self;

    /**
     * Get adapter.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getAdapter(string $name);

    /**
     * Get adapters.
     *
     * @param array $adapters
     *
     * @return array
     */
    public function getAdapters(array $adapters = []): array;
}

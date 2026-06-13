<?php
declare(strict_types=1);

namespace FusionDirectory\Container;

/**
 * Minimal PSR-11 compatible container interface.
 * Defines the contract for dependency injection containers.
 */
interface ContainerInterface
{
    /**
     * Retrieves an entry from the container by its identifier.
     *
     * @param string $id Identifier of the entry to look for
     *
     * @throws NotFoundExceptionInterface  No entry was found for this identifier
     * @throws ContainerExceptionInterface Error while retrieving the entry
     *
     * @return mixed Entry
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for
     */
    public function has(string $id): bool;
}

<?php
declare(strict_types=1);

namespace FusionDirectory\Container;

/**
 * Exception thrown when no entry is found in the container.
 */
class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
}

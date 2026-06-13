<?php
declare(strict_types=1);

namespace FusionDirectory\Container;

/**
 * Lightweight dependency injection container.
 *
 * Supports three types of service registration:
 * - Direct instance:  $container->set('foo', $myObject)
 * - Factory callable: $container->factory('foo', fn() => new Foo())
 * - Auto-wiring:      Resolves class dependencies via constructor reflection
 *
 * Usage:
 *   $container = new Container();
 *   $container->set('config', new Config('/etc/fusiondirectory'));
 *   $config = $container->get('config');
 */
class Container implements ContainerInterface
{
    /** @var array<string, mixed> Resolved service instances */
    private array $instances = [];

    /** @var array<string, callable> Factory callables (called once per get) */
    private array $factories = [];

    /** @var array<string, string> Class aliases (alias → FQCN) */
    private array $aliases = [];

    /** @var array<class-string, array<string, string>> Constructor parameter type hints cache */
    private array $reflectionCache = [];

    /**
     * Register a service instance directly.
     *
     * @param string $id   Service identifier
     * @param mixed  $service The service instance
     */
    public function set(string $id, mixed $service): void
    {
        $this->instances[$id] = $service;
        unset($this->factories[$id]);
    }

    /**
     * Register a factory callable. The callable is invoked each time the service is requested.
     *
     * @param string   $id      Service identifier
     * @param callable $factory A callable that returns the service
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Register a class alias. When $id is requested, the alias target is resolved instead.
     *
     * @param string $alias  The alias name
     * @param string $target The target class name or service id
     */
    public function alias(string $alias, string $target): void
    {
        $this->aliases[$alias] = $target;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $id): mixed
    {
        // Resolve aliases
        if (isset($this->aliases[$id])) {
            return $this->get($this->aliases[$id]);
        }

        // Return cached instance
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // Invoke factory
        if (isset($this->factories[$id])) {
            $instance = ($this->factories[$id])($this);
            $this->instances[$id] = $instance;
            return $instance;
        }

        // Auto-wire from class name
        if (class_exists($id)) {
            return $this->autowire($id);
        }

        throw new NotFoundException("Service '{$id}' not found in container");
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return isset($this->aliases[$id])
            || array_key_exists($id, $this->instances)
            || isset($this->factories[$id])
            || class_exists($id);
    }

    /**
     * Auto-wire a class by resolving its constructor dependencies.
     */
    private function autowire(string $className): mixed
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            // No constructor — instantiate directly
            $instance = $reflection->newInstance();
            $this->instances[$className] = $instance;
            return $instance;
        }

        $parameters = $constructor->getParameters();
        $arguments = [];

        foreach ($parameters as $param) {
            $type = $param->getType();

            if ($type === null || $type instanceof \ReflectionNamedType === false) {
                if ($param->isDefaultValueAvailable()) {
                    $arguments[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $arguments[] = null;
                } else {
                    throw new ContainerException(
                        "Cannot auto-wire parameter '\${$param->getName()}' of '{$className}': no type hint and no default value"
                    );
                }
                continue;
            }

            $typeName = $type->getName();

            if ($typeName === 'mixed') {
                if ($param->isDefaultValueAvailable()) {
                    $arguments[] = $param->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot auto-wire parameter '\${$param->getName()}' of '{$className}': mixed type with no default value"
                    );
                }
                continue;
            }

            try {
                $arguments[] = $this->get($typeName);
            } catch (NotFoundException $e) {
                if ($param->isDefaultValueAvailable()) {
                    $arguments[] = $param->getDefaultValue();
                } elseif ($type->allowsNull()) {
                    $arguments[] = null;
                } else {
                    throw new ContainerException(
                        "Cannot auto-wire parameter '\${$param->getName()}' of '{$className}': " . $e->getMessage()
                    );
                }
            }
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->instances[$className] = $instance;
        return $instance;
    }
}

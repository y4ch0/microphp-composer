<?php
/**
 * MicroPHP Framework
 * Minimal dependency-injection container (PSR-11-shaped, no external package).
 *
 * Resolves classes by reflecting on their constructor and recursively
 * resolving typed, non-builtin parameters. Scalar/union/builtin parameters
 * fall back to their default value, or must be supplied explicitly.
 */

namespace MicroPHP;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container
{
    /** @var array<string,Closure|string> Abstract name => concrete class name or factory closure. */
    private array $bindings = [];

    /** @var array<string,true> Abstracts that should be resolved once and cached. */
    private array $singletonFlags = [];

    /** @var array<string,object> Cached singleton instances. */
    private array $instances = [];

    /**
     * Bind an abstract name to a concrete class or factory. Resolved fresh every time.
     *
     * @param string $abstract Interface/class name (or arbitrary string key) to bind.
     * @param Closure|string $concrete Concrete class name, or a factory: fn(Container $c): object.
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->singletonFlags[$abstract], $this->instances[$abstract]);
    }

    /**
     * Bind an abstract name to be resolved once and reused for the lifetime of the container.
     *
     * @param string $abstract Interface/class name (or arbitrary string key) to bind.
     * @param Closure|string|null $concrete Concrete class name or factory. Defaults to $abstract itself.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        $this->singletonFlags[$abstract] = true;
        unset($this->instances[$abstract]);
    }

    /** Register an already-built instance as a singleton. */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->singletonFlags[$abstract] = true;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || class_exists($abstract);
    }

    /**
     * Resolve an abstract to a concrete instance, autowiring constructor dependencies.
     *
     * @param array<string,mixed> $parameters Explicit values keyed by constructor parameter name,
     *                                         used instead of autowiring for that parameter only.
     */
    public function make(string $abstract, array $parameters = []): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if (isset($this->singletonFlags[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function build(string $class, array $parameters): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Container cannot resolve unknown class: {$class}");
        }

        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Container target is not instantiable: {$class}");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            if (array_key_exists($param->getName(), $parameters)) {
                $dependencies[] = $parameters[$param->getName()];
                continue;
            }
            $dependencies[] = $this->resolveParameter($param);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
            return null;
        }

        throw new RuntimeException(
            "Container cannot resolve parameter \${$param->getName()} — no binding, type hint, or default value."
        );
    }
}

<?php

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function add(string $route, callable $handler): self
    {
        $this->routes[trim($route, '/')] = $handler;

        return $this;
    }

    public function dispatch(string $route, callable $notFound): void
    {
        $route = trim($route, '/');
        ($this->routes[$route] ?? $notFound)();
    }
}

<?php
declare(strict_types=1);

namespace Ramingo\Core;

/** Router minimale: metodo + pattern con segnaposto {param}. */
final class Router
{
    /** @var array<int, array{string, string, array{class-string, string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): self
    {
        $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[A-Za-z0-9_\-]+)', $pattern) . '$~';
        $this->routes[] = [$method, $regex, $handler];
        return $this;
    }

    public function dispatch(Request $request, App $app): void
    {
        foreach ($this->routes as [$method, $regex, [$class, $action]]) {
            if ($method !== $request->method || !preg_match($regex, $request->path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            /** @var Controller $controller */
            $controller = new $class($app, $request);
            $controller->before();
            $controller->$action(...$params);
            return;
        }
        (new \Ramingo\Controllers\ErrorController($app, $request))->notFound();
    }
}

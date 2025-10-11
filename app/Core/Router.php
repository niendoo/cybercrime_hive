<?php
namespace App\Core;

/**
 * Router Class
 * Handles URL routing and request dispatching
 */
class Router {
    private $routes = [];

    /**
     * Add a GET route
     */
    public function get($path, $callback) {
        $this->routes['get'][$path] = $callback;
    }

    /**
     * Add a POST route
     */
    public function post($path, $callback) {
        $this->routes['post'][$path] = $callback;
    }

    /**
     * Resolve the route and return handler
     */
    public function resolve($method, $path) {
        $method = strtolower($method);
        $callback = $this->routes[$method][$path] ?? null;

        if (!$callback) {
            throw new \Exception('Route not found');
        }

        if (is_string($callback)) {
            return $this->resolveController($callback);
        }

        return $callback;
    }

    /**
     * Resolve controller string to callable
     */
    private function resolveController($string) {
        [$controller, $action] = explode('@', $string);
        $controller = "App\\Controllers\\{$controller}";

        if (!class_exists($controller)) {
            throw new \Exception("Controller {$controller} not found");
        }

        $controller = new $controller();

        if (!method_exists($controller, $action)) {
            throw new \Exception("Method {$action} not found in controller");
        }

        return [$controller, $action];
    }
}
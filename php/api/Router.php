<?php
/**
 * API Router
 * Centralized API routing for all endpoints
 *
 * @package VisaChatbot\API
 * @version 1.0.0
 */

namespace VisaChatbot\API;

class Router {

    /**
     * Registered routes
     */
    private array $routes = [];

    /**
     * Middleware stack
     */
    private array $middleware = [];

    /**
     * Base path
     */
    private string $basePath = '';

    /**
     * Constructor
     *
     * @param string $basePath
     */
    public function __construct(string $basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Register a GET route
     *
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    public function get(string $path, $handler): self {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    public function post(string $path, $handler): self {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     *
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    public function put(string $path, $handler): self {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    public function delete(string $path, $handler): self {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route for any method
     *
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    public function any(string $path, $handler): self {
        return $this->addRoute('ANY', $path, $handler);
    }

    /**
     * Add a route
     *
     * @param string $method
     * @param string $path
     * @param callable|string $handler
     * @return self
     */
    private function addRoute(string $method, string $path, $handler): self {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        $fullPath = rtrim($fullPath, '/') ?: '/';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'pattern' => $this->pathToPattern($fullPath),
            'handler' => $handler
        ];

        return $this;
    }

    /**
     * Add middleware
     *
     * @param callable $middleware
     * @return self
     */
    public function use(callable $middleware): self {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Convert path to regex pattern
     *
     * @param string $path
     * @return string
     */
    private function pathToPattern(string $path): string {
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch the request
     *
     * @param string|null $method
     * @param string|null $path
     * @return mixed
     */
    public function dispatch(?string $method = null, ?string $path = null) {
        $method = $method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $path ?? $this->getRequestPath();

        // Run middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware($method, $path);
            if ($result === false) {
                return null;
            }
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Filter out numeric keys
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->callHandler($route['handler'], $params);
            }
        }

        // No route found
        return $this->notFound();
    }

    /**
     * Get request path
     *
     * @return string
     */
    private function getRequestPath(): string {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH);
        return $path ?: '/';
    }

    /**
     * Call route handler
     *
     * @param callable|string $handler
     * @param array $params
     * @return mixed
     */
    private function callHandler($handler, array $params) {
        if (is_callable($handler)) {
            return call_user_func_array($handler, [$params]);
        }

        // String handler format: "Controller@method"
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler);
            $controller = new $class();
            return call_user_func_array([$controller, $method], [$params]);
        }

        throw new \RuntimeException("Invalid handler: " . print_r($handler, true));
    }

    /**
     * Handle not found
     *
     * @return void
     */
    private function notFound(): void {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Route not found',
            'code' => 404
        ]);
    }

    /**
     * Send JSON response
     *
     * @param mixed $data
     * @param int $statusCode
     * @return void
     */
    public static function json($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send success response
     *
     * @param mixed $data
     * @param string|null $message
     * @return void
     */
    public static function success($data = null, ?string $message = null): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Send error response
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $details
     * @return void
     */
    public static function error(string $message, int $statusCode = 400, $details = null): void {
        self::json([
            'success' => false,
            'error' => $message,
            'details' => $details
        ], $statusCode);
    }

    /**
     * Get JSON request body
     *
     * @return array
     */
    public static function getJsonBody(): array {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get request parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function param(string $key, $default = null) {
        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Get request header
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function header(string $name, $default = null) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }
}

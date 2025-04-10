<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

namespace Core;

class Router {
  private static $routes = [];
  private static $basePath = '';

  // Set base path for current environment (e.g., subfolder or root)
  public static function setBasePath() {
    $urlPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Get everything before /api to detect the subfolder, if any
    $basePath = strstr($urlPath, '/api', true);
    
    // If there's no subfolder, use an empty string
    if ($basePath === false) {
      self::$basePath = '';
    } else {
      self::$basePath = rtrim($basePath, '/');
    }
  }

  public static function add($method, $pattern, $callback) {
    self::setBasePath();  

    // Adjust the pattern to ignore base path
    $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';
    self::$routes[strtoupper($method)][] = ['pattern' => $pattern, 'callback' => $callback];
  }

  public static function dispatch($method, $uri) {
    $method = strtoupper($method);
    $path = parse_url($uri, PHP_URL_PATH);
    $path = rtrim($path, '/');

    // Only remove the base path before /api, if any
    if (!empty(self::$basePath)) {
      // Ensure the base path is only removed before /api
      if (strpos($path, self::$basePath) === 0) {
        $path = substr($path, strlen(self::$basePath));
      }
    }

    foreach (self::$routes[$method] ?? [] as $route) {
      if (preg_match($route['pattern'], $path, $matches)) {
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return call_user_func_array($route['callback'], $params);
      }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
  }
}

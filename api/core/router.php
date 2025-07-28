<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// core/Router.php

header('Content-Type: application/json');

// Load config and utilities
require_once __DIR__ . '/../config/api.config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

use PhoenixAPI\Auth;
use PhoenixAPI\Response;

// Parse request URL
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Strip query string if present
$cleanUri = strtok($requestUri, '?');

// Match expected pattern: /api/v1/resource
$pattern = '#v1/([^/]+)(?:/([^/]+))?#';

if (!preg_match($pattern, $cleanUri, $matches)) {
  Response::error('Invalid API endpoint', 404);
  exit;
}

$resource = $matches[1] ?? null;
$resourceId = $matches[2] ?? null;

// Verify API key (via Authorization: Bearer {token})
if (!Auth::isValid()) {
  Response::error('Unauthorized', 401);
  exit;
}

// Map resource to route file
$routeFile = __DIR__ . "/../routes/{$resource}.php";
if (!file_exists($routeFile)) {
  Response::error("Endpoint not found: $resource", 404);
  exit;
}

// Pass along resource ID and method via globals or $_GET
if ($resourceId) {
  $_GET['id'] = $resourceId;
}

$_GET['method'] = $requestMethod;

// Include the handler
require_once $routeFile;

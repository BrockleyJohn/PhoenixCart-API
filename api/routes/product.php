<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

require_once __DIR__ . '/../controllers/ProductController.php';

use Core\Router;
use Api\Controllers\ProductController;

Router::add('GET', '/api/product', [ProductController::class, 'index']);
Router::add('GET', '/api/product/{id}', [ProductController::class, 'show']);
Router::add('GET', '/api/product/search/{term}', [ProductController::class, 'search']);


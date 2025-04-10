<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

  chdir('../');
  require_once 'includes/application_top.php';
  
  require_once __DIR__ . '/core/Router.php';
  require_once __DIR__ . '/routes.php';

  use Core\Router;

  header('Content-Type: application/json');

  Router::dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
  
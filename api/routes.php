<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

$routesPath = __DIR__ . '/routes';

foreach (glob($routesPath . '/*.php') as $file) {
  require_once $file;
}

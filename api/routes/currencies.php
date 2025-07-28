<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/currencies.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2));
require_once 'includes/application_top.php';

global $db;

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
  Response::error('Method not allowed', 405);
}

$result = $db->query("
  SELECT *
  FROM currencies
  ORDER BY title ASC
");

if (!$result) {
  Response::error('Failed to fetch currencies: ' . $db->error, 500);
}

$currencies = [];
while ($row = $result->fetch_assoc()) {
  $currencies[] = $row;
}

Response::success([
  'count'     => count($currencies),
  'currencies'=> $currencies
]);

<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/search.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2)); // PhoenixCart root
require_once 'includes/application_top.php';

global $db, $languages_id;

// Request parameters
$method       = $_SERVER['REQUEST_METHOD'];
$q            = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = max(1, (int)($_GET['limit'] ?? 20));
$offset       = ($page - 1) * $limit;
$sort         = $_GET['sort'] ?? 'name_asc';
$language_id  = (int)($_GET['language_id'] ?? $languages_id ?? 1);
$currency     = strtoupper($_GET['currency'] ?? DEFAULT_CURRENCY);

// Sort map
$sortMap = [
  'name_asc'     => 'pd.products_name ASC',
  'name_desc'    => 'pd.products_name DESC',
  'price_asc'    => 'p.products_price ASC',
  'price_desc'   => 'p.products_price DESC',
  'date_asc'     => 'p.products_date_added ASC',
  'date_desc'    => 'p.products_date_added DESC',
  'viewed_asc'   => 'p.products_viewed ASC',
  'viewed_desc'  => 'p.products_viewed DESC'
];
$orderBy = $sortMap[$sort] ?? $sortMap['name_asc'];

if ($method !== 'GET') {
  Response::error('Method not allowed', 405);
}

// Currency lookup
$currency_stmt = $db->prepare("
  SELECT code, symbol_left, symbol_right,
         decimal_point, thousands_point,
         decimal_places, value
  FROM currencies
  WHERE code = ?
  LIMIT 1
");
$currency_stmt->bind_param('s', $currency);
$currency_stmt->execute();
$currency_data = $currency_stmt->get_result()->fetch_assoc();

if (!$currency_data) {
  Response::error("Currency '$currency' not supported", 400);
}

// Total count
$count_sql = "
  SELECT COUNT(*) AS total
  FROM products p
  JOIN products_description pd ON pd.products_id = p.products_id
  WHERE p.products_status = 1 AND pd.language_id = ?";
if ($q !== '') {
  $count_sql .= " AND (pd.products_name LIKE ? OR pd.products_description LIKE ?)";
}

$count_stmt = $db->prepare($count_sql);
if (!$count_stmt) {
  Response::error('Prepare failed (count): ' . $db->error, 500);
}

if ($q !== '') {
  $q_like = '%' . $q . '%';
  $count_stmt->bind_param('sss', $language_id, $q_like, $q_like);
} else {
  $count_stmt->bind_param('i', $language_id);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = (int)$count_result->fetch_assoc()['total'];
$pages = (int)ceil($total / $limit);

// Product data
$data_sql = "
  SELECT p.*, pd.*, s.specials_new_products_price, s.expires_date
  FROM products p
  JOIN products_description pd ON pd.products_id = p.products_id
  LEFT JOIN specials s ON s.products_id = p.products_id
    AND s.status = 1
    AND (s.expires_date IS NULL OR s.expires_date > NOW())
  WHERE p.products_status = 1 AND pd.language_id = ?";
if ($q !== '') {
  $data_sql .= " AND (pd.products_name LIKE ? OR pd.products_description LIKE ?)";
}
$data_sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";

$data_stmt = $db->prepare($data_sql);
if (!$data_stmt) {
  Response::error('Prepare failed (data): ' . $db->error, 500);
}

if ($q !== '') {
  $data_stmt->bind_param('sssii', $language_id, $q_like, $q_like, $limit, $offset);
} else {
  $data_stmt->bind_param('iii', $language_id, $limit, $offset);
}

$data_stmt->execute();
$result = $data_stmt->get_result();

$data = [];
while ($product = $result->fetch_assoc()) {
  $product['prices'] = buildPriceBlock($product, $currency_data);
  $data[] = $product;
}

Response::success([
  'query'      => $q,
  'language'   => $language_id,
  'page'       => $page,
  'limit'      => $limit,
  'count'      => count($data),
  'total'      => $total,
  'pages'      => $pages,
  'has_next'   => $page < $pages,
  'has_prev'   => $page > 1,
  'next_page'  => ($page < $pages) ? $page + 1 : null,
  'prev_page'  => ($page > 1) ? $page - 1 : null,
  'from'       => $offset + 1,
  'to'         => $offset + count($data),
  'sort'       => $sort,
  'currency'   => $currency,
  'items'      => $data
]);

// Price builder
function buildPriceBlock($product, $currency_data) {
  $base     = (float)$product['products_price'];
  $special  = isset($product['specials_new_products_price']) ? (float)$product['specials_new_products_price'] : null;
  $expires  = isset($product['expires_date']) && $product['expires_date'] !== '0000-00-00 00:00:00'
              ? $product['expires_date']
              : null;

  $is_special = ($special !== null && $special < $base);
  $final      = $is_special ? $special : $base;

  $converted  = $final * (float)$currency_data['value'];
  $formatted  = number_format(
    $converted,
    (int)$currency_data['decimal_places'],
    $currency_data['decimal_point'],
    $currency_data['thousands_point']
  );
  $display = trim($currency_data['symbol_left'] . $formatted . $currency_data['symbol_right']);

  return [
    'base'       => $base,
    'special'    => $is_special ? $special : null,
    'final'      => $final,
    'is_special' => $is_special,
    'expires'    => $expires,
    'currency'   => [
      'code'    => $currency_data['code'],
      'symbol'  => trim($currency_data['symbol_left'] . $currency_data['symbol_right']),
      'display' => $display
    ]
  ];
}

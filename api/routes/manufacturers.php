<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/manufacturers.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2));
require_once 'includes/application_top.php';

global $db, $languages_id;

$requestUri     = $_SERVER['REQUEST_URI'] ?? '';
$segments       = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$lastSegment    = end($segments);
$isSingle       = count($segments) >= 3 && is_numeric($lastSegment);
$manufacturerId = $isSingle ? (int)$lastSegment : null;

$method         = $_SERVER['REQUEST_METHOD'];
$language_id    = (int)($_GET['language_id'] ?? $languages_id ?? 1);
$search         = trim($_GET['search'] ?? '');
$sort           = $_GET['sort'] ?? 'name_asc';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = max(1, (int)($_GET['limit'] ?? 25));
$offset         = ($page - 1) * $limit;

$sortMap = [
  'name_asc'         => 'm.manufacturers_name ASC',
  'name_desc'        => 'm.manufacturers_name DESC',
  'date_added_asc'   => 'm.date_added ASC',
  'date_added_desc'  => 'm.date_added DESC'
];
$orderBy = $sortMap[$sort] ?? $sortMap['name_asc'];

if ($method !== 'GET') {
  Response::error('Method not allowed', 405);
}

// Single Manufacturer
if ($isSingle) {
  $stmt = $db->prepare("
    SELECT m.*, mi.*
    FROM manufacturers m
    LEFT JOIN manufacturers_info mi ON mi.manufacturers_id = m.manufacturers_id
      AND mi.languages_id = ?
    WHERE m.manufacturers_id = ?
    LIMIT 1
  ");
  if (!$stmt) {
    Response::error('Prepare failed: ' . $db->error, 500);
  }
  $stmt->bind_param('ii', $language_id, $manufacturerId);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  if (!$row) {
    Response::error('Manufacturer not found', 404);
  }

  // Count products for this manufacturer
  $count_stmt = $db->prepare("
    SELECT COUNT(*) AS product_count
    FROM products
    WHERE products_status = 1 AND manufacturers_id = ?
  ");
  $count_stmt->bind_param('i', $manufacturerId);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $product_count = (int)$count_result->fetch_assoc()['product_count'];

  $row['product_count'] = $product_count;
  Response::success($row);
  return;
}

// Search + List All
$where = "WHERE mi.languages_id = ?";
$params = [$language_id];
$types  = "i";

if ($search !== '') {
  $where .= " AND (
    m.manufacturers_name LIKE ? OR
    mi.manufacturers_description LIKE ? OR
    mi.manufacturers_seo_title LIKE ? OR
    mi.manufacturers_seo_description LIKE ? OR
    mi.manufacturers_url LIKE ?
  )";
  $like   = '%' . $search . '%';
  $params = array_merge($params, array_fill(0, 5, $like));
  $types .= str_repeat("s", 5);
}

// Count total rows
$count_sql = "
  SELECT COUNT(*) AS total
  FROM manufacturers m
  LEFT JOIN manufacturers_info mi ON mi.manufacturers_id = m.manufacturers_id
  $where
";
$count_stmt = $db->prepare($count_sql);
if (!$count_stmt) {
  Response::error('Prepare failed (count): ' . $db->error, 500);
}
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = (int)$count_result->fetch_assoc()['total'];
$pages = (int)ceil($total / $limit);

// Fetch manufacturers
$sql = "
  SELECT m.*, mi.*
  FROM manufacturers m
  LEFT JOIN manufacturers_info mi ON mi.manufacturers_id = m.manufacturers_id
  $where
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
if (!$stmt) {
  Response::error('Prepare failed (listing): ' . $db->error, 500);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  // Count products per manufacturer
  $mid = (int)$row['manufacturers_id'];
  $count_stmt = $db->prepare("
    SELECT COUNT(*) AS product_count
    FROM products
    WHERE products_status = 1 AND manufacturers_id = ?
  ");
  $count_stmt->bind_param('i', $mid);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $row['product_count'] = (int)$count_result->fetch_assoc()['product_count'];
  
  $imageFile = $row['manufacturers_image'] ?? '';
  $imageUrl = $imageFile !== ''
  ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $imageFile
  : null;

  $row['image_url'] = $imageUrl;

  $data[] = $row;
}

Response::success([
  'language_id'   => $language_id,
  'query'         => $search,
  'sort'          => $sort,
  'page'          => $page,
  'limit'         => $limit,
  'count'         => count($data),
  'total'         => $total,
  'pages'         => $pages,
  'has_next'      => $page < $pages,
  'has_prev'      => $page > 1,
  'next_page'     => ($page < $pages) ? $page + 1 : null,
  'prev_page'     => ($page > 1) ? $page - 1 : null,
  'from'          => $offset + 1,
  'to'            => $offset + count($data),
  'manufacturers' => $data
]);

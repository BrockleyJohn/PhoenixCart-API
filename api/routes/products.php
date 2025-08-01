<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/products.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2));
require_once 'includes/application_top.php';

global $db, $languages_id;

$requestUri  = $_SERVER['REQUEST_URI'] ?? '';
$segments    = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$lastSegment = end($segments);
$isSingle    = count($segments) >= 3 && is_numeric($lastSegment);
$productId   = $isSingle ? (int)$lastSegment : null;

$method      = $_SERVER['REQUEST_METHOD'];
$language_id = (int)($_GET['language_id'] ?? $languages_id ?? 1);
$categories_id = (int)($_GET['categories_id'] ?? null);
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = max(1, (int)($_GET['limit'] ?? 25));
$offset      = ($page - 1) * $limit;

if ($method !== 'GET') {
  Response::error('Method not allowed', 405);
}

// Enrich each product 
function enrichProduct(array $product, mysqli $db, int $language_id): array {
  $imageFile = $product['products_image'] ?? '';
  $product['image_url'] = $imageFile !== ''
    ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $imageFile
    : null;

  // Extra images
  $extraImages = [];
  $img_stmt = $db->prepare("
    SELECT image FROM products_images
    WHERE products_id = ?
    ORDER BY sort_order ASC
  ");
  $img_stmt->bind_param('i', $product['products_id']);
  $img_stmt->execute();
  $img_res = $img_stmt->get_result();
  while ($img = $img_res->fetch_assoc()) {
    $filename = $img['image'];
    $extraImages[] = [
      'filename' => $filename,
      'url'      => $filename !== ''
        ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $filename
        : null
    ];
  }
  $product['extra_images'] = $extraImages;

  // Reviews
  $rev_stmt = $db->prepare("
    SELECT COUNT(*) AS review_count, AVG(reviews_rating) AS average_rating
    FROM reviews
    WHERE products_id = ? AND reviews_status = 1
  ");
  $rev_stmt->bind_param('i', $product['products_id']);
  $rev_stmt->execute();
  $rev_res = $rev_stmt->get_result();
  $rev_data = $rev_res->fetch_assoc();
  $product['reviews'] = [
    'review_count'   => (int)$rev_data['review_count'],
    'average_rating' => $rev_data['average_rating'] !== null
      ? round((float)$rev_data['average_rating'], 2)
      : null
  ];

  // Manufacturer
  if (!empty($product['manufacturers_id'])) {
    $man_stmt = $db->prepare("
      SELECT m.manufacturers_id, m.manufacturers_name, m.manufacturers_image
      FROM manufacturers m
      LEFT JOIN manufacturers_info mi ON mi.manufacturers_id = m.manufacturers_id
        AND mi.languages_id = ?
      WHERE m.manufacturers_id = ?
      LIMIT 1
    ");
    $man_stmt->bind_param('ii', $language_id, $product['manufacturers_id']);
    $man_stmt->execute();
    $man_res = $man_stmt->get_result();
    if ($man = $man_res->fetch_assoc()) {
      $imageFile = $man['manufacturers_image'] ?? '';
      $product['manufacturer'] = [
        'manufacturers_id'   => (int)$man['manufacturers_id'],
        'manufacturers_name' => $man['manufacturers_name'],
        'image_url'          => $imageFile !== ''
          ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $imageFile
          : null,
        'link'               => '/manufacturers/' . $man['manufacturers_id']
      ];
    }
  }
  
  // Categories
  $cat_stmt = $db->prepare("
    SELECT c.categories_id, cd.categories_name
    FROM products_to_categories pc
    JOIN categories c ON c.categories_id = pc.categories_id
    JOIN categories_description cd ON cd.categories_id = c.categories_id
      AND cd.language_id = ?
    WHERE pc.products_id = ?
    ORDER BY c.sort_order ASC
  ");
  $cat_stmt->bind_param('ii', $language_id, $product['products_id']);
  $cat_stmt->execute();
  $cat_res = $cat_stmt->get_result();

  $categories = [];
  while ($cat = $cat_res->fetch_assoc()) {
    $categories[] = [
      'id'   => (int)$cat['categories_id'],
      'name' => $cat['categories_name'],
      'link' => '/categories/' . $cat['categories_id']
    ];
  }
  
  $product['categories'] = $categories;
  
  $trail_stmt = $db->prepare("
    SELECT pc.categories_id
    FROM products_to_categories pc
    WHERE pc.products_id = ?
  ");
  $trail_stmt->bind_param('i', $product['products_id']);
  $trail_stmt->execute();
  $trail_res = $trail_stmt->get_result();

  $breadcrumbs = [];

  while ($row = $trail_res->fetch_assoc()) {
    $path = [];
    $catId = (int)$row['categories_id'];

    while ($catId > 0) {
      $cat_info_stmt = $db->prepare("
        SELECT c.categories_id, c.parent_id, cd.categories_name
        FROM categories c
        JOIN categories_description cd ON cd.categories_id = c.categories_id
          AND cd.language_id = ?
        WHERE c.categories_id = ?
        LIMIT 1
      ");
      $cat_info_stmt->bind_param('ii', $language_id, $catId);
      $cat_info_stmt->execute();
      $cat_info_res = $cat_info_stmt->get_result();
      $cat = $cat_info_res->fetch_assoc();

      if (!$cat) {
        break;
      }

      array_unshift($path, [
        'id'   => (int)$cat['categories_id'],
        'name' => $cat['categories_name'],
        'link' => '/categories/' . $cat['categories_id']
      ]);

      $catId = (int)$cat['parent_id'];
    }

    if (!empty($path)) {
      $breadcrumbs[] = $path;
    }
  }

  $product['breadcrumbs'] = $breadcrumbs;

  return $product;
}

// Single Product
if ($isSingle) {
  $stmt = $db->prepare("
    SELECT p.*, pd.*
    FROM products p
    LEFT JOIN products_description pd ON pd.products_id = p.products_id
      AND pd.language_id = ?
    WHERE p.products_status = 1 AND p.products_id = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $language_id, $productId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();

  if (!$row) {
    Response::error('Product not found', 404);
  }

  $row = enrichProduct($row, $db, $language_id);
  Response::success($row);
  return;
}

// Product Listing
$count_stmt = $db->prepare("
  SELECT COUNT(*) AS total
  FROM products
  WHERE products_status = 1
");
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total = (int)$count_res->fetch_assoc()['total'];
$pages = ceil($total / $limit);

if (is_null($categories_id)) {
  $stmt = $db->prepare("
    SELECT p.*, pd.*
    FROM products p
    LEFT JOIN products_description pd ON pd.products_id = p.products_id
      AND pd.language_id = ?
    WHERE p.products_status = 1
    ORDER BY p.products_date_added DESC
    LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('iii', $language_id, $limit, $offset);
} else {
  $stmt = $db->prepare("
    SELECT p.*, pd.*
    FROM products p
    INNER JOIN products_to_categories p2c ON p2c.products_id = p.products_id
      AND p2c.categories_id = ?
    LEFT JOIN products_description pd ON pd.products_id = p.products_id
      AND pd.language_id = ?
    WHERE p.products_status = 1
    ORDER BY p.products_date_added DESC
    LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('iiii', $categories_id, $language_id, $limit, $offset);
}
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
  $data[] = enrichProduct($row, $db, $language_id);
}

Response::success([
  'language_id' => $language_id,
  'page'        => $page,
  'limit'       => $limit,
  'count'       => count($data),
  'total'       => $total,
  'pages'       => $pages,
  'has_next'    => $page < $pages,
  'has_prev'    => $page > 1,
  'next_page'   => $page < $pages ? $page + 1 : null,
  'prev_page'   => $page > 1 ? $page - 1 : null,
  'from'        => $offset + 1,
  'to'          => $offset + count($data),
  'products'    => $data
]);

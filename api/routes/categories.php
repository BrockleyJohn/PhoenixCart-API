<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/categories.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2));
require_once 'includes/application_top.php';

global $db, $languages_id;

$method      = $_SERVER['REQUEST_METHOD'];
$requestUri  = $_SERVER['REQUEST_URI'] ?? '';
$segments    = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$lastSegment = end($segments);
$language_id = (int)($_GET['language_id'] ?? $languages_id ?? 1);

// Identify single-category view
$isSingle    = count($segments) >= 2 && is_numeric($lastSegment);
$categoryId  = $isSingle ? (int)$lastSegment : null;

if ($method !== 'GET') {
  Response::error('Method not allowed', 405);
}

// ================================
// categories/{id} → Detail Mode
// ================================
if ($isSingle) {
  $stmt = $db->prepare("
    SELECT c.*, cd.*
    FROM categories c
    JOIN categories_description cd ON cd.categories_id = c.categories_id
      AND cd.language_id = ?
    WHERE c.categories_id = ?
    LIMIT 1
  ");
  $stmt->bind_param('ii', $language_id, $categoryId);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  if (!$row) {
    Response::error('Category not found', 404);
  }

  $row['link'] = '/categories/' . $row['categories_id'];
  $row['image_url'] = !empty($row['categories_image'])
    ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $row['categories_image']
    : null;

  if ('products' === $_GET['expand'] ?? '') {
    // Fetch products in this category
    $prod_stmt = $db->prepare("
      SELECT p.*, pd.*
      FROM products p
      LEFT JOIN products_description pd ON pd.products_id = p.products_id
        AND pd.language_id = ?
      JOIN products_to_categories ptc ON ptc.products_id = p.products_id
      WHERE ptc.categories_id = ? AND p.products_status = 1
      ORDER BY p.products_date_added DESC
    ");
    $prod_stmt->bind_param('ii', $language_id, $categoryId);
    $prod_stmt->execute();
    $prod_res = $prod_stmt->get_result();

    $products = [];
    while ($product = $prod_res->fetch_assoc()) {
      $product['image_url'] = !empty($product['products_image'])
        ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $product['products_image']
        : null;
      $products[] = $product;
    }
    $row['products'] = $products;
  } else {
    // Product count
    $count_stmt = $db->prepare("
      SELECT COUNT(*) AS product_count
      FROM products_to_categories ptc
      JOIN products p ON p.products_id = ptc.products_id
      WHERE ptc.categories_id = ? AND p.products_status = 1
    ");
    $count_stmt->bind_param('i', $categoryId);
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    $count_row = $count_res->fetch_assoc();
    $row['product_count'] = (int)$count_row['product_count'];
  }
  // Child categories
  $child_stmt = $db->prepare("
    SELECT c.*, cd.*
    FROM categories c
    JOIN categories_description cd ON cd.categories_id = c.categories_id
      AND cd.language_id = ?
    WHERE c.parent_id = ?
    ORDER BY c.sort_order ASC
  ");
  $child_stmt->bind_param('ii', $language_id, $categoryId);
  $child_stmt->execute();
  $child_res = $child_stmt->get_result();

  $children = [];
  while ($child = $child_res->fetch_assoc()) {
    $child['link'] = '/categories/' . $child['categories_id'];
    $child['image_url'] = !empty($child['categories_image'])
      ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $child['categories_image']
      : null;
    $children[] = $child;
  }

  $row['children'] = $children;

  Response::success($row);
  return;
}

// ================================
// categories → Tree Mode
// ================================
$stmt = $db->prepare("
  SELECT c.*, cd.*
  FROM categories c
  JOIN categories_description cd ON cd.categories_id = c.categories_id
    AND cd.language_id = ?
  ORDER BY c.sort_order ASC, cd.categories_name ASC
");
$stmt->bind_param('i', $language_id);
$stmt->execute();
$result = $stmt->get_result();

$allCategories = [];
$childrenMap = [];

while ($row = $result->fetch_assoc()) {
  $catId = (int)$row['categories_id'];
  $parent = (int)$row['parent_id'];

  $row['link'] = '/categories/' . $catId;
  $row['image_url'] = !empty($row['categories_image'])
    ? HTTP_SERVER . DIR_WS_CATALOG . 'images/' . $row['categories_image']
    : null;

  $allCategories[$catId] = $row;

  if (!isset($childrenMap[$parent])) {
    $childrenMap[$parent] = [];
  }
  $childrenMap[$parent][] = $catId;
}

function buildTree($parentId, $all, $map) {
  $tree = [];
  if (!isset($map[$parentId])) return $tree;
  foreach ($map[$parentId] as $childId) {
    $node = $all[$childId];
    $kids = buildTree($childId, $all, $map);
    if (!empty($kids)) {
      $node['children'] = $kids;
    }
    $tree[] = $node;
  }
  return $tree;
}

$tree = buildTree(0, $allCategories, $childrenMap);
Response::success($tree);

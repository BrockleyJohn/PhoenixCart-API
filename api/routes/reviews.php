<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// routes/reviews.php

require_once __DIR__ . '/../core/Response.php';
use PhoenixAPI\Response;

chdir(dirname(__DIR__, 2));
require_once 'includes/application_top.php';

global $db, $languages_id;

// Parse request path for single review mode
$requestUri     = $_SERVER['REQUEST_URI'] ?? '';
$segments       = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$lastSegment    = end($segments);
$isSingle       = count($segments) >= 3 && is_numeric($lastSegment);
$reviewId       = $isSingle ? (int)$lastSegment : null;

$method         = $_SERVER['REQUEST_METHOD'];
$language_id    = (int)($_GET['language_id'] ?? $languages_id ?? 1);

// Handle POST: Submit New Review
if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);

  $products_id    = (int)($input['products_id'] ?? 0);
  $rating         = (int)($input['reviews_rating'] ?? 0);
  $text           = trim($input['reviews_text'] ?? '');
  $customers_id   = isset($input['customers_id']) ? (int)$input['customers_id'] : null;
  $customers_name = trim($input['customers_name'] ?? 'Anonymous');
  $is_anon        = 'n'; // Always enforced

  // Validation
  if (!$products_id || !$rating || $rating < 1 || $rating > 5 || $text === '' || !$customers_id) {
    Response::error('Missing or invalid review fields', 400);
  }

  // Check customer exists
  $checkCustomer = $db->prepare("SELECT customers_id FROM customers WHERE customers_id = ?");
  $checkCustomer->bind_param('i', $customers_id);
  $checkCustomer->execute();
  $customerRes = $checkCustomer->get_result();
  if ($customerRes->num_rows === 0) {
    Response::error('Customer not found. Must be registered.', 403);
  }

  // Check duplicate review
  $checkReview = $db->prepare("
    SELECT reviews_id FROM reviews
    WHERE customers_id = ? AND products_id = ?
  ");
  $checkReview->bind_param('ii', $customers_id, $products_id);
  $checkReview->execute();
  $reviewRes = $checkReview->get_result();
  if ($reviewRes->num_rows > 0) {
    Response::error('You have already reviewed this product.', 409);
  }

  // Insert into reviews
  $stmt = $db->prepare("
    INSERT INTO reviews (
      products_id, customers_id, customers_name,
      reviews_rating, date_added, reviews_status, is_anon
    ) VALUES (?, ?, ?, ?, NOW(), 0, ?)
  ");
  $stmt->bind_param('iisis', $products_id, $customers_id, $customers_name, $rating, $is_anon);
  if (!$stmt->execute()) {
    Response::error('Failed to save review', 500);
  }
  $review_id = $stmt->insert_id;

  // Insert into reviews_description
  $desc_stmt = $db->prepare("
    INSERT INTO reviews_description (
      reviews_id, languages_id, reviews_text
    ) VALUES (?, ?, ?)
  ");
  $desc_stmt->bind_param('iis', $review_id, $language_id, $text);
  if (!$desc_stmt->execute()) {
    Response::error('Failed to save review text', 500);
  }

  Response::success([
    'message'    => 'Review submitted and pending approval',
    'review_id'  => $review_id
  ]);
  return;
}

// Handle GET /reviews/{id}
if ($method === 'GET' && $isSingle) {
  $stmt = $db->prepare("
    SELECT r.*, rd.*
    FROM reviews r
    JOIN reviews_description rd ON rd.reviews_id = r.reviews_id
    WHERE r.reviews_id = ? AND rd.languages_id = ? AND r.reviews_status = 1
    LIMIT 1
  ");
  $stmt->bind_param('ii', $reviewId, $language_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $review = $result->fetch_assoc();

  if (!$review) {
    Response::error('Review not found or not approved', 404);
  }

  Response::success($review);
  return;
}

// Handle GET /reviews
$product_id  = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$sort        = $_GET['sort'] ?? 'date_desc';
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = max(1, (int)($_GET['limit'] ?? 20));
$offset      = ($page - 1) * $limit;

$sortMap = [
  'date_asc'     => 'r.date_added ASC',
  'date_desc'    => 'r.date_added DESC',
  'rating_asc'   => 'r.reviews_rating ASC',
  'rating_desc'  => 'r.reviews_rating DESC'
];
$orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

$where = "WHERE r.reviews_status = 1 AND rd.languages_id = ?";
$params = [$language_id];
$types  = "i";

if ($product_id !== null) {
  $where .= " AND r.products_id = ?";
  $params[] = $product_id;
  $types .= "i";
}

// Count total
$count_sql = "
  SELECT COUNT(*) AS total
  FROM reviews r
  JOIN reviews_description rd ON rd.reviews_id = r.reviews_id
  $where
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$pages = (int)ceil($total / $limit);

// Get paginated reviews
$sql = "
  SELECT r.*, rd.*
  FROM reviews r
  JOIN reviews_description rd ON rd.reviews_id = r.reviews_id
  $where
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

Response::success([
  'product_id'  => $product_id,
  'language_id' => $language_id,
  'sort'        => $sort,
  'page'        => $page,
  'limit'       => $limit,
  'count'       => count($data),
  'total'       => $total,
  'pages'       => $pages,
  'has_next'    => $page < $pages,
  'has_prev'    => $page > 1,
  'next_page'   => ($page < $pages) ? $page + 1 : null,
  'prev_page'   => ($page > 1) ? $page - 1 : null,
  'from'        => $offset + 1,
  'to'          => $offset + count($data),
  'reviews'     => $data
]);

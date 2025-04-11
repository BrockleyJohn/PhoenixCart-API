<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

namespace Api\Controllers;

require_once __DIR__ . '/../core/Response.php';

use Core\Response;

class ProductController {

    public static function index() {
      $products_query = $GLOBALS['db']->query("SELECT p.*, pd.* FROM products_description pd JOIN products p ON pd.products_id = p.products_id WHERE products_status = 1 AND language_id = 1");

      $products = [];
      while ($row = $products_query->fetch_assoc()) {
        $products[] = $row;
      }

      Response::json($products);
    }

    public static function show($id) {
      $id = (int)$id;
      
      $product_query = $GLOBALS['db']->query("SELECT p.*, pd.* FROM products p JOIN products_description pd ON p.products_id = pd.products_id WHERE p.products_status = 1 AND p.products_id = $id AND pd.language_id = 1 LIMIT 1");
      
      if ($product_query->num_rows === 0) {
        return Response::json(['error' => 'Product not found'], 404);
      }

      $product = $product_query->fetch_assoc();
      Response::json($product);
    }

    public static function search($term) {
      $keyword = $GLOBALS['db']->real_escape_string($term); 
      
      $keyword_query = $GLOBALS['db']->query("SELECT p.products_id, pd.*, p.* FROM products p JOIN products_description pd ON p.products_id = pd.products_id WHERE (pd.products_name LIKE '%$keyword%' OR pd.products_description LIKE '%$keyword%') AND p.products_status = 1 AND pd.language_id = 1");

      $products = [];
      while ($row = $keyword_query->fetch_assoc()) {
        $products[] = $row;
      }

      if (empty($products)) {
        return Response::json(['message' => 'No products found for this keyword'], 404);
      }

      Response::json($products);
    }
}


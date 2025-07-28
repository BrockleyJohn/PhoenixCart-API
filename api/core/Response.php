<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// core/Response.php

namespace PhoenixAPI;

class Response {

  public static function success($data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'data'    => $data
    ], JSON_PRETTY_PRINT);
    exit;
  }

  public static function error(string $message = 'Error', int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'error'   => $message,
      'code'    => $code
    ], JSON_PRETTY_PRINT);
    exit;
  }

  public static function raw($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
  }
}

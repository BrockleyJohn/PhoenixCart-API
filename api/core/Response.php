<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2025 Phoenix Cart

  Released under the GNU General Public License
*/

namespace Core;

class Response {
  public static function json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
  }
}

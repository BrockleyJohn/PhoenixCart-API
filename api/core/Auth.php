<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

// core/Auth.php

namespace PhoenixAPI;

class Auth {

  public static function getToken(): ?string {
    $headers = getallheaders();

    // Check header first
    if (isset($headers['Authorization']) &&
        preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
      return $matches[1];
    }

    // ⛳️ Dev-only fallback: allow ?token=
    if (isset($_GET['token'])) {
      return $_GET['token'];
    }

    return null;
  }

  public static function isValid(): bool {
    $token = self::getToken();
    if (!$token) return false;

    // Load config key
    $config = require __DIR__ . '/../config/api.config.php';
    $validKey = $config['api_key'] ?? '';

    return hash_equals($validKey, $token);
  }
}

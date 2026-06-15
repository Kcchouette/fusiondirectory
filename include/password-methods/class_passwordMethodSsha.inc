<?php
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)

  Copyright (C) 2003-2010  Cajus Pollmeier
  Copyright (C) 2011-2019  FusionDirectory

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
*/

/*!
 * \file class_passwordMethodSsha.inc
 * Source code for class passwordMethodSsha
 */

class passwordMethodSsha extends passwordMethod
{
  function __construct ()
  {
  }

  public function is_available (): bool
  {
    return TRUE;
  }

  public function generate_hash (string $pwd, bool $locked = FALSE): string
  {
    $salt = substr(pack('h*', md5(strval(random_int(0, PHP_INT_MAX)))), 0, 8);
    $salt = substr(pack('H*', sha1($salt.$pwd)), 0, 4);
    $pwd  = '{SSHA}'.($locked ? '!' : '').base64_encode(pack('H*', sha1($pwd.$salt)).$salt);
    return $pwd;
  }

  function checkPassword ($pwd, $hash): bool
  {
    $hash = base64_decode(substr($hash, 6));
    $salt = substr($hash, 20);
    $hash = substr($hash, 0, 20);
    $nhash = pack('H*', sha1($pwd . $salt));
    return ($nhash == $hash);
  }

  static function get_hash_name ()
  {
    return 'ssha';
  }
}

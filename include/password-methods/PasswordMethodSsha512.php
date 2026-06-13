<?php
declare(strict_types=1);
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
 * \file class_passwordMethodSsha512.inc
 * Source code for class PasswordMethodSsha512
 */

/*!
 * \brief This class contains all the functions for ssha password method
 * \see passwordMethod
 */
class PasswordMethodSsha512 extends PasswordMethod
{
  /*!
   * \brief passwordMethodSsha512 Constructor
   */
  function __construct ()
  {
  }

  /*!
   * \brief Is available
   *
   * \return TRUE if is avaibable, otherwise return false
   */
  public function is_available (): bool
  {
    return function_exists('hash');
  }

  /*!
   * \brief Generate template hash
   *
   * \param string $pwd Password
   * \param bool $locked Should the password be locked
   *
   * \return string the password hash
   */
  public function generate_hash (string $pwd, bool $locked = FALSE): string
  {
    if (function_exists('hash')) {
      $salt = substr(pack('h*', md5("".random_int(0, PHP_INT_MAX))), 0, 8);
      $salt = substr(pack('H*', sha1($salt.$pwd)), 0, 4);
      $pwd  = '{SSHA512}'.($locked ? '!' : '').base64_encode(hash('sha512', $pwd.$salt, TRUE).$salt);
    } else {
      throw new FusionDirectoryException(MsgPool::missingext('hash'));
    }
    return $pwd;
  }

  function checkPassword ($pwd, $hash): bool
  {
    $hash = base64_decode(substr($hash, 9));
    $salt = substr($hash, 64);
    $hash = substr($hash, 0, 64);
    if (function_exists('hash')) {
      $nhash = hash('sha512', $pwd.$salt, TRUE);
    } else {
      $error = new FusionDirectoryError(MsgPool::missingext('hash'));
      $error->display();
      return FALSE;
    }
    return ($nhash == $hash);
  }

  /*!
   * \brief Get the hash name
   */
  static function get_hash_name ()
  {
    return 'ssha512';
  }
}

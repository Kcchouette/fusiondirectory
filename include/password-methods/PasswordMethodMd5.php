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
 * \file class_passwordMethodMd5.inc
 * Source code for class PasswordMethodMd5
 */

/*!
 * \brief This class contains all the functions for md5 password method
 * \see passwordMethod
 */
class PasswordMethodMd5 extends PasswordMethod
{

  /*!
   * \brief passwordMethodMd5 Constructor
   */
  function __construct ()
  {
  }

  /*!
   * \brief Is available
   *
   * \return TRUE if is available, otherwise return false
   */
  public function isAvailable (): bool
  {
    return function_exists('md5');
  }

  /*!
   * \brief Generate template hash
   *
   * \param string $pwd Password
   * \param bool $locked Should the password be locked
   *
   * \return string the password hash
   */
  public function generateHash (string $pwd, bool $locked = FALSE): string
  {
    // TODO: deprecate MD5 hashing, prefer Argon2id or SHA-512
    return  '{MD5}'.($locked ? '!' : '').base64_encode(pack('H*', md5($pwd)));
  }

  /*!
   * \brief Get the hash name
   */
  static function getHashName ()
  {
    return 'md5';
  }
}

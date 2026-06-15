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
 * \brief LDAP postal address attribute
 *
 * Handles the $-encoded postal address format in LDAP
 */
class PostalAddressAttribute extends TextAreaAttribute
{
  function inputValue ($ldapValue)
  {
    return str_replace(
      ['$', '\24', '\5C'],
      ["\n", '$', '\\'],
      $ldapValue
    );
  }

  function computeLdapValue ()
  {
    return str_replace(
      ["\r\n", "\n", "\r"],
      '$',
      str_replace(
        ['\\', '$'],
        ['\5C', '\24'],
        $this->getValue()
      )
    );
  }
}

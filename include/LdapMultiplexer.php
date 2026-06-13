<?php
declare(strict_types=1);

/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)
  Copyright (C) 2003-2010  Cajus Pollmeier
  Copyright (C) 2011-2016  FusionDirectory

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
 * \file class_ldapMultiplexer.inc
 * Source code for class LdapMultiplexer
 */

 /**
  * This class contains all function to manage ldap multiplexer
  * @method void setSizeLimit ($size)
  * @method void cd ($dir)
  * @method boolean|resource modifyBatch (array $changes)
  * @method string getError ($details = TRUE)
  * @method array getObjectclasses ($force_reload = FALSE)
  * @method string|array fetch (bool $cleanUpNumericIndices = FALSE)
  * @method string|resource search ($filter, $attrs = [], $scope = 'subtree', array $controls = NULL)
  * @method string|resource cat ($dn, $attrs = ["*"], $filter = "(objectclass=*)")
  * @method int count ()
  * @method bool success ()
  * @method true|0|"" rmdirRecursive (string $deletedn)
  * @method void createMissingTrees (string $target, bool $ignoreReferralBases = TRUE)
  * @method mixed add ($attrs)
  */
class LdapMultiplexer
{

  /* Internal stuff */
  protected mixed $object;

  /* Result resource */
  protected mixed $sr;

  /*!
   * \brief LADP multiplexer constructor
   *
   * \param $object Object LDAP
   */
  public function __construct (&$object)
  {
    /* Store object */
    $this->object = $object;

    /* Set result resource */
    $this->sr = $this->object->getSearchResource();
  }

  /*!
   * \brief Call a ldap method with his parameters
   *
   * \param string $methodName The name of the method
   *
   * \param $parameters Parameters for the method
   */
  public function __call ($methodName, $parameters)
  {
    /* Add resource pointer if the mentioned methods are used */
    if (in_array($methodName, ['search','ls','cat','fetch','clearResult','resetResult','count','getDN','rmdirRecursive','createMissingTrees','importSingleEntry','importCompleteLdif','parseResult'])) {
      array_unshift($parameters, $this->sr);
    }

    return call_user_func_array([$this->object, $methodName], $parameters);
  }

  /*
   * \brief Get a member name from a ldap object
   *
   * \param string $memberName
   */
  public function __get ($memberName)
  {
    return $this->object->$memberName;
  }
}

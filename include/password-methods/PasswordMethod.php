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

/*
 * \file class_passwordMethod.inc
 * Source code for class PasswordMethod
 */

/*!
 * \brief This class contains all the basic function for password methods
 */
abstract class PasswordMethod
{
  public bool $display  = FALSE;
  public string $hash     = '';

  protected bool $lockable = TRUE;

  /*!
   * \brief Password method contructor
   *
   * \param string $dn The DN
   * \param object $userTab The user main tab object
   */
  function __construct ($dn = '', $userTab = NULL)
  {
  }

  /*!
   * \brief Get the Hash name
   */
  abstract static function getHashName ();

  /*!
   * \brief Generate template hash
   *
   * \param string $pwd Password
   * \param bool $locked Should the password be locked
   *
   * \return string the password hash
   */
  abstract public function generateHash (string $pwd, bool $locked = FALSE): string;

  /*!
   * \brief Is available
   *
   * \return TRUE
   */
  public function isAvailable (): bool
  {
    return TRUE;
  }

  /*!
   * \brief If we need password
   *
   * \return boolean TRUE
   */
  public function needPassword (): bool
  {
    return TRUE;
  }

  /*!
   * \brief If we can lock the password
   *
   * \return boolean
   */
  public function isLockable (): bool
  {
    return $this->lockable;
  }

  /*!
   * \brief Is locked
   *
   * \param string $dn The DN
   */
  function isLocked ($dn = '', $pwd = ''): bool
  {
    if (!$this->lockable) {
      return FALSE;
    }

    /* Get current password hash */
    if (!empty($dn)) {
      $ldap = config()->getLdapLink();
      $ldap->cd(config()->current['BASE']);
      $ldap->cat($dn, ['userPassword']);
      $attrs = $ldap->fetch();
      if (isset($attrs['userPassword'][0])) {
        $pwd = $attrs['userPassword'][0];
      }
    }
    return preg_match("/^[^\}]*+\}!/", $pwd);
  }

  /*! \brief       Locks an account by adding a '!' as prefix to the password hashes.
   *               This makes login impossible, due to the fact that the hash becomes invalid.
   *               userPassword: {SHA}!q02NKl9IChNwZEAJxzRdmB6E
   *               sambaLMPassword: !EBD223B61F8C259AD3B435B51404EE
   *               sambaNTPassword: !98BB35737013AAF181D0FE9FDA09E
   *
   * \param string $dn
   */
  function lockAccount ($dn = '', bool $lockEverything = TRUE)
  {
    return $this->genericModifyAccount($dn, 'LOCK', $lockEverything);
  }

  /*!
   * \brief Unlocks an account which was locked by 'lockAccount()'.
   *        For details about the locking mechanism see 'lockAccount()'.
   */
  function unlockAccount ($dn = '')
  {
    return $this->genericModifyAccount($dn, 'UNLOCK');
  }

  /*!
   * \brief Unlocks an account which was locked by 'lockAccount()'.
   *        For details about the locking mechanism see 'lockAccount()'.
   */
  private function genericModifyAccount ($dn, string $mode, bool $lockEverything = TRUE)
  {
    if (!$this->lockable) {
      return FALSE;
    }
    if ($mode != 'LOCK' && $mode != 'UNLOCK') {
      throw new FusionDirectoryException('Invalid mode "'.$mode.'"');
    }

    /* Open the user */
    $userObject   = Objects::open($dn, 'user');
    $userMainTab  = $userObject->getBaseObject();

    /* Check if this entry is already (un)locked. */
    if ($userMainTab->attributesAccess['userPassword']->isLocked()) {
      if ($mode == 'LOCK') {
        return TRUE;
      }
    } elseif ($mode == 'UNLOCK') {
      return TRUE;
    }
    /* Fill modification array */
    $modify = [];

    // Only trigger if general lock is set
    if ($lockEverything) {
      foreach ($userObject->by_object as $tab) {
        if ($tab instanceof UserTabLockingAction) {
          // Execute below function if available in each plugin tab to lock what is required to be locked. (webservice etc).
          $tab->fillLockingLDAPAttrs($mode, $modify);
        }
      }
    }



    // Call pre hooks
    $errors = $userMainTab->callHook('PRE'.$mode, [], $ret);
    if (!empty($errors)) {
      Logging::log('error', strtolower($mode), $dn, [], 'error while '.strtolower($mode).'ing: prehook failed');
      MsgDialog::displayChecks($errors);
      return FALSE;
    }

    /* Get current password hash */
    $pwd = $userMainTab->attributesAccess['userPassword']->computeLdapValue();

    // (Un)lock the account by modifying the password hash.
    if ($mode == 'LOCK') {
      /* Lock entry */
      if (empty($pwd)) {
        $pwd = PasswordMethodEmpty::LOCKVALUE;
      } else {
        $pwd = preg_replace("/(^[^\}]+\})(.*$)/",   "\\1!\\2",  $pwd);
      }
    } else {
      /* Unlock entry */
      if ($pwd == PasswordMethodEmpty::LOCKVALUE) {
        $pwd = '';
      } else {
        $pwd = preg_replace("/(^[^\}]+\})!(.*$)/",  "\\1\\2",   $pwd);
      }
    }
    $modify['userPassword'] = $pwd;

    $ldap = config()->getLdapLink();
    $ldap->cd($dn);
    $ldap->modify($modify);

    // Call the password post-lock hook, if defined.
    if ($ldap->success()) {
      Logging::log('security', strtolower($mode), $dn, [], 'successfully '.strtolower($mode).'ed');
      $userClass = new user($dn);
      $errors = $userClass->callHook('POST'.$mode, [], $ret);
      if (!empty($errors)) {
        Logging::log('error', strtolower($mode), $dn, [], 'error while '.strtolower($mode).'ing: posthook failed');
        MsgDialog::displayChecks($errors);
      }
    } else {
      Logging::log('error', strtolower($mode), $dn, [], 'error while '.strtolower($mode).'ing: '.$ldap->getError());
      $error = new FusionDirectoryLdapError($dn, LDAP_MOD, $ldap->getError(), $ldap->getErrno());
      $error->display();
    }
    return $ldap->success();
  }


  /*!
   * \brief This function returns all loaded classes for password encryption
   */
  static function getAvailableMethods (): array
  {
    global $class_mapping;
    $ret  = [];
    $i    = 0;

    if (!Session::is_set('PasswordMethod::getAvailableMethods')) {
      foreach (array_keys($class_mapping) as $class) {
        if (preg_match('/^passwordMethod.+/i', $class)) {
          $test = new $class('');
          if ($test->isAvailable()) {
            $plugs = $test->getHashName();
            if (!is_array($plugs)) {
              $plugs = [$plugs];
            }

            $cfg  = $test->isConfigurable();

            foreach ($plugs as $plugname) {
              $ret['name'][$i]            = $plugname;
              $ret['class'][$i]           = $class;
              $ret['isConfigurable'][$i] = $cfg;
              $ret['object'][$i]          = $test;

              $ret[$i]['name']            = $plugname;
              $ret[$i]['class']           = $class;
              $ret[$i]['object']          = $test;
              $ret[$i]['isConfigurable'] = $cfg;

              $ret[$plugname]             = $class;
              $i++;
            }
          }
        }
      }
      Session::set('PasswordMethod::getAvailableMethods', $ret);
    }
    return Session::get('PasswordMethod::getAvailableMethods');
  }

  /*!
   * \brief Method to check if a password matches a hash
   */
  function checkPassword ($pwd, $hash): bool
  {
    return ($hash == $this->generateHash($pwd));
  }


  /*!
   * \brief Return true if this password method provides a configuration dialog
   */
  function isConfigurable (): bool
  {
    return FALSE;
  }

  /*!
   * \brief Provide a subdialog to configure a password method
   */
  function configure (): string
  {
    return '';
  }


  /*!
   * \brief Save information to LDAP
   *
   * \param string $dn The DN
   */
  function save ($dn)
  {
  }


  /*!
   * \brief Try to find out if it's our hash...
   *
   * \param string $password_hash
   *
   * \param string $dn The DN
   */
  static function getMethod ($password_hash, $dn = ''): passwordMethod
  {
    $methods = PasswordMethod::getAvailableMethods();

    if (isset($methods['class']['PasswordMethodEmpty']) && (PasswordMethodEmpty::_extract_method($password_hash) != '')) {
      /* Test empty method first as it gets priority */
      $method = new PasswordMethodEmpty();
      return $method;
    }

    foreach ($methods['class'] as $class) {
      $method = $class::_extract_method($password_hash);
      if ($method != '') {
        $test = new $class($dn);
        $test->setHash($method);
        return $test;
      }
    }

    $method = new PasswordMethodClear();
    return $method;
  }

  /*!
   * \brief Extract a method
   *
   * \param string $classname The password method class name
   *
   * \param string $password_hash
   */
  static function _extract_method ($password_hash): string
  {
    $hash = static::getHashName();
    if (preg_match("/^\{$hash\}/i", $password_hash)) {
      return $hash;
    }

    return '';
  }

  /*!
   * \brief Make a hash
   *
   * \param string $password The password
   *
   * \param string $hash
   */
  static function makeHash ($password, $hash): string
  {
    $methods  = PasswordMethod::getAvailableMethods();
    $tmp      = new $methods[$hash]();
    $tmp->setHash($hash);
    return $tmp->generateHash($password);
  }

  /*!
   * \brief Set a hash
   *
   * \param string $hash
   */
  function setHash ($hash)
  {
    $this->hash = $hash;
  }


  /*!
   * \brief Get a hash
   */
  function getHash ()
  {
    return $this->hash;
  }

  /*!
   * \brief Test for problematic unicode caracters in password
   *  This can be activated with the keyword strictPasswordRules in the
   *  fusiondirectory.conf
   *
   * \param string $password The password
   */
  static function isHarmless ($password): bool
  {
    if (config()->get_cfg_value('strictPasswordRules') == 'TRUE') {
      // Do we have UTF8 characters in the password?
      return ($password == mb_convert_encoding($password, 'ISO-8859-1', 'UTF-8'));
    }

    return TRUE;
  }
}

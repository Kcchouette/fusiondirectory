<?php
declare(strict_types=1);
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)
  Copyright (C) 2003-2010  Cajus Pollmeier
  Copyright (C) 2003 Alejandro Escanero Blanco <aescanero@chaosdimension.org>
  Copyright (C) 1998  Eric Kilfoil <eric@ipass.net>
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
 * \file class_ldap.inc
 * Source code for Class LDAP
 */

/*!
 * \brief This class contains all ldap function needed to make
 * ldap operations easy
 */

class LDAP
{
   public bool $hascon         = false;
   public bool $reconnect      = false;
   public bool $tls            = false;

  /**
   * Connection identifier
   *
   * @var resource|object|false
   */
   public mixed $cid            = FALSE;

   public array $hasres         = [];
   public array $sr             = [];
   public array $re             = [];
   public string $basedn         = '';

  /* 0 if we are fetching the first entry, otherwise 1 */
   public array $start          = [];

  /* Any error messages to be returned can be put here */
   public string $error          = '';

   public int $srp            = 0;

  /* Information read from slapd.oc.conf */
   public array $objectClasses    = [];
  /* the dn for the bind */
   public string $binddn           = '';
  /* the dn's password for the bind */
   public string $bindpw           = '';
   public string $hostname         = '';
   public bool $follow_referral  = false;
   public array $referrals        = [];

   /* 0, empty or negative values will disable this check */
    public float $max_ldap_query_time  = 0;

   /** @var LdapConnection Connection lifecycle management */
    public LdapConnection $connection;

   /** @var LdapSearch Read/search operations */
    public LdapSearch $search;

   /** @var LdapWriter Write operations */
    public LdapWriter $writer;

   /** @var LdapSerializer LDIF serialization */
    public LdapSerializer $serializer;

   /*!
    * \brief Create a LDAP connection
    *
    * \param string $binddn Bind of the DN
    *
    * \param string $bindpw Bind
    *
    * \param string $hostname The hostname
    *
    * \param boolean $follow_referral FALSE
    *
    * \param boolean $tls FALSE
    */
   function __construct ($binddn, $bindpw, $hostname, $follow_referral = FALSE, $tls = FALSE)
   {
    $this->follow_referral  = $follow_referral;
    $this->tls              = $tls;
    $this->binddn           = $binddn;
    $this->bindpw           = $bindpw;
    $this->hostname         = $hostname;

    /* Initialize facade components */
    $this->connection  = new LdapConnection($this);
    $this->search      = new LdapSearch($this);
    $this->writer      = new LdapWriter($this);
    $this->serializer  = new LdapSerializer($this);

    /* Check if MAX_LDAP_QUERY_TIME is defined */
    if (is_object(config()) && (config()->get_cfg_value("ldapMaxQueryTime") != "")) {
      $str = config()->get_cfg_value("ldapMaxQueryTime");
      $this->max_ldap_query_time = (float)($str);
    }

    $this->connect();
   }

  /*! \brief Remove bogus resources after unserialize
   */
  public function __wakeup ()
  {
    $this->cid    = FALSE;
    $this->hascon = FALSE;
  }

  /*!
   * \brief Initialize a LDAP connection
   *
   * Initializes a LDAP connection.
   *
   * \param string $server The server we are connecting to
   *
   * \param string $base The base of our ldap tree
   *
   * \param string $binddn Default: empty
   *
   * \param string $pass Default: empty
   *
   * \return LDAP object
   */
  public static function init (string $server, string $base, string $binddn = '', string $pass = ''): LDAP
  {

    $ldap = new LDAP($binddn, $pass, $server,
        isset(config()->current['LDAPFOLLOWREFERRALS']) && config()->current['LDAPFOLLOWREFERRALS'] == 'TRUE',
        isset(config()->current['LDAPTLS']) && config()->current['LDAPTLS'] == 'TRUE');

    /* Sadly we've no proper return values here. Use the error message instead. */
    if (!$ldap->success()) {
      throw new FatalError(htmlescape(sprintf(_('FATAL: Error when connecting to LDAP. Server said "%s".'), $ldap->getError())));
    }

    /* Preset connection base to $base and return to caller */
    $ldap->cd($base);
    return $ldap;
  }

  /*!
   *  \brief Error text that must be returned for invalid user or password
   *
   *  This is useful to make sure the same error text is shown whether a user exists or not, when the password is not correct.
   */
  static function invalidCredentialsError (): string
  {
    return _(ldap_err2str(49));
  }

  /* ============================================================
   * Delegates to LdapConnection
   * ============================================================ */

  function connect ()
  {
    $this->connection->connect();
  }

  function rebind ($ldap, $referral)
  {
    $this->connection->rebind($ldap, $referral);
  }

  function reconnect ()
  {
    $this->connection->reconnect();
  }

  function unbind ()
  {
    $this->connection->unbind();
  }

  function disconnect ()
  {
    $this->connection->disconnect();
  }

  function getCredentials ($url, $referrals = NULL)
  {
    return $this->connection->getCredentials($url, $referrals);
  }

  /* ============================================================
   * Delegates to LdapSearch
   * ============================================================ */

  function getSearchResource ()
  {
    return $this->search->getSearchResource();
  }

  function search ($srp, $filter, $attrs = [], $scope = 'subtree', ?array $controls = NULL)
  {
    return $this->search->search($srp, $filter, $attrs, $scope, $controls);
  }

  function cat ($srp, $dn, $attrs = ["*"], $filter = "(objectclass=*)")
  {
    return $this->search->cat($srp, $dn, $attrs, $filter);
  }

  function objectMatchFilter ($dn, $filter)
  {
    return $this->search->objectMatchFilter($dn, $filter);
  }

  function setSizeLimit ($size)
  {
    $this->search->setSizeLimit($size);
  }

  function fetch ($srp, bool $cleanUpNumericIndices = FALSE)
  {
    return $this->search->fetch($srp, $cleanUpNumericIndices);
  }

  function resetResult ($srp)
  {
    $this->search->resetResult($srp);
  }

  function clearResult ($srp)
  {
    $this->search->clearResult($srp);
  }

  function getDN ($srp)
  {
    return $this->search->getDN($srp);
  }

  function count ($srp)
  {
    return $this->search->count($srp);
  }

  function cd ($dir)
  {
    $this->search->cd($dir);
  }

  function getParentDir ($basedn = '')
  {
    return $this->search->getParentDir($basedn);
  }

  function parseResult ($srp): array
  {
    return $this->search->parseResult($srp);
  }

  /* ============================================================
   * Delegates to LdapWriter
   * ============================================================ */

  function rm ($attrs = "", $dn = "")
  {
    return $this->writer->rm($attrs, $dn);
  }

  function modAdd ($attrs = "", $dn = "")
  {
    return $this->writer->modAdd($attrs, $dn);
  }

  function rmdir ($deletedn)
  {
    return $this->writer->rmdir($deletedn);
  }

  function renameDn ($source, $dest)
  {
    return $this->writer->renameDn($source, $dest);
  }

  function rmdirRecursive ($srp, $deletedn)
  {
    return $this->writer->rmdirRecursive($srp, $deletedn);
  }

  function makeReadableErrors ($error, $attrs)
  {
    return $this->writer->makeReadableErrors($error, $attrs);
  }

  function modify (array $attrs)
  {
    return $this->writer->modify($attrs);
  }

  function modifyBatch (array $changes)
  {
    return $this->writer->modifyBatch($changes);
  }

  function add ($attrs)
  {
    return $this->writer->add($attrs);
  }

  function createMissingTrees ($srp, $target, $ignoreReferralBases = TRUE)
  {
    $this->writer->createMissingTrees($srp, $target, $ignoreReferralBases);
  }

  /* ============================================================
   * Delegates to LdapSerializer
   * ============================================================ */

  function generateLdif (string $dn, string $filter = '(objectClass=*)', string $scope = 'sub', int $limit = 0, ?int $wrap = NULL): string
  {
    return $this->serializer->generateLdif($dn, $filter, $scope, $limit, $wrap);
  }

  function dnExists ($dn): bool
  {
    return $this->serializer->dnExists($dn);
  }

  function parseLdif (string $str_attr): array
  {
    return $this->serializer->parseLdif($str_attr);
  }

  function importCompleteLdif ($srp, $str_attr, $JustModify, $DeleteOldEntries)
  {
    return $this->serializer->importCompleteLdif($srp, $str_attr, $JustModify, $DeleteOldEntries);
  }

  protected function importSingleEntry ($srp, $data, $modify, $delete)
  {
    return $this->serializer->importSingleEntry($srp, $data, $modify, $delete);
  }

  /* ============================================================
   * Utility methods kept in LDAP class
   * ============================================================ */

  /*!
   * \brief Get the LDAP additional error
   *
   * \return string containts LDAP_OPT_ERROR_STRING
   */
  function getAdditionalError ()
  {
    $additional_error = '';
    @ldap_get_option($this->cid, LDAP_OPT_ERROR_STRING, $additional_error);
    return $additional_error;
  }

  /*!
   * \brief Success
   *
   * \return boolean TRUE if Success is found in $error, else return FALSE
   */
  function success (): bool
  {
    return (trim($this->error) === 'Success');
  }

  /*!
   * \brief Get the error
   */
  function getError ($details = TRUE): string
  {
    if (($this->error == 'Success') || !$details) {
      return $this->error;
    } else {
      $adderror = $this->getAdditionalError();
      if ($adderror != '') {
        return sprintf(
          _('%s (%s, while operating on "%s" using LDAP server "%s")'),
          $this->error, $adderror, $this->basedn, $this->hostname
        );
      } else {
        return sprintf(
          _('%s (while operating on LDAP server "%s")'),
          $this->error, $this->hostname
        );
      }
    }
  }

  /*!
   * \brief Get the errno
   *
   * Must be run right after the ldap request
   */
  function getErrno (): int
  {
    if ($this->error == 'Success') {
      return 0;
    } else {
      return @ldap_errno($this->cid);
    }
  }

  /*!
   * \brief Check if the search hit the size limit
   *
   * Must be run right after the search
   */
  function hitSizeLimit (): bool
  {
    /* LDAP_SIZELIMIT_EXCEEDED 0x04 */
    return ($this->getErrno() == 0x04);
  }

  /*!
   * \brief Get the object classes
   *
   * \param boolean $force_reload FALSE
   */
  function getObjectclasses ($force_reload = FALSE)
  {
    /* Return the cached results. */
    if (class_available('Session') && Session::is_set('LDAP_CACHE::getObjectclasses') && !$force_reload) {
      return Session::get('LDAP_CACHE::getObjectclasses');
    }

    // Get base to look for schema
    $res    = @ldap_read($this->cid, '', 'objectClass=*', ['subschemaSubentry']);
    $attrs  = @ldap_get_entries($this->cid, $res);
    if (!isset($attrs[0]['subschemasubentry'][0])) {
      return [];
    }

    /* Get list of objectclasses and fill array */
    $nb = $attrs[0]['subschemasubentry'][0];
    $objectclasses = [];
    $res    = ldap_read($this->cid, $nb, 'objectClass=*', ['objectclasses']);
    $attrs  = ldap_get_entries($this->cid, $res);
    if (!isset($attrs[0])) {
      return [];
    }
    foreach ($attrs[0]['objectclasses'] as $val) {
      if (preg_match('/^[0-9]+$/', $val)) {
        continue;
      }
      $name     = 'OID';
      $pattern  = explode(' ', $val);
      $ocname   = preg_replace("/^.* NAME\s+\(*\s*'([^']+)'\s*\)*.*$/", '\\1', $val);
      $objectclasses[$ocname] = [];

      $value = '';
      foreach ($pattern as $chunk) {
        switch ($chunk) {

          case '(':
            $value = '';
            break;

          case ')':
            if ($name != '') {
              $v = $this->value2container($value);
              if (in_array($name, ['MUST', 'MAY']) && !is_array($v)) {
                $v = [$v];
              }
              $objectclasses[$ocname][$name] = $v;
            }
            $name   = '';
            $value  = '';
            break;

          case 'NAME':
          case 'DESC':
          case 'SUP':
          case 'STRUCTURAL':
          case 'ABSTRACT':
          case 'AUXILIARY':
          case 'MUST':
          case 'MAY':
            if ($name != '') {
              $v = $this->value2container($value);
              if (in_array($name, ['MUST','MAY']) && !is_array($v)) {
                $v = [$v];
              }
              $objectclasses[$ocname][$name] = $v;
            }
            $name   = $chunk;
            $value  = '';
            break;

          default:  $value .= $chunk.' ';
        }
      }
    }
    if (class_available('Session')) {
      Session::set('LDAP_CACHE::getObjectclasses', $objectclasses);
    }

    return $objectclasses;
  }


  function value2container ($value)
  {
    /* Set emtpy values to "TRUE" only */
    if (preg_match('/^\s*$/', $value)) {
      return TRUE;
    }

    /* Remove ' and " if needed */
    $value = preg_replace('/^[\'"]/', '', $value);
    $value = preg_replace('/[\'"] *$/', '', $value);

    /* Convert to array if $ is inside... */
    if (preg_match('/\$/', $value)) {
      $container = preg_split('/\s*\$\s*/', $value);
    } else {
      $container = chop($value);
    }

    return $container;
  }

  /*!
   * \brief Add a string in log file
   *
   * \param string $string
   */
  function log ($string)
  {
    if (Session::is_set('Config')) {
      $cfg = Session::get('Config');
      if (isset($cfg->current['LDAPSTATS']) && preg_match('/true/i', $cfg->current['LDAPSTATS'])) {
        syslog(LOG_INFO, $string);
      }
    }
  }

  /* added by Guido Serra aka Zeph <zeph@purotesto.it> */

  /*!
   * \brief Function to get cn
   *
   * \param $dn The DN
   */
  function getCn ($dn)
  {
    $simple = explode(",", $dn);

    foreach ($simple as $piece) {
      $partial = explode("=", $piece);

      if ($partial[0] == "cn") {
        return $partial[1];
      }
    }
  }

  public static function getNamingContexts ($server, $admin = '', $password = '')
  {
    /* Build LDAP connection */
    $ds = ldap_connect($server);
    if (!$ds) {
      throw new LDAPFailureException('Can\'t bind to LDAP. No check possible!');
    }
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_bind($ds, $admin, $password);

    /* Get base to look for naming contexts */
    $res    = @ldap_read($ds, '', 'objectClass=*', ['namingContexts']);
    $attrs  = @ldap_get_entries($ds, $res);

    Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $attrs[0]['namingcontexts'], 'getNamingContexts');
    return $attrs[0]['namingcontexts'];
  }
}

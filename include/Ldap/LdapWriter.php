<?php
declare(strict_types=1);

/**
 * Handles LDAP write operations (add, modify, delete, rename).
 */
class LdapWriter
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    function add ($attrs)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $r = @ldap_add($this->ldap->cid, $this->ldap->basedn, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            if (!$this->ldap->success()) {
                $this->ldap->error .= $this->makeReadableErrors($this->ldap->error, $attrs);
            }
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'add('.$this->ldap->basedn.')');
            return ($r ? $r : 0);
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'add('.$this->ldap->basedn.')');
            return "";
        }
    }

    function modify (array $attrs)
    {
        if (count($attrs) == 0) {
            return 0;
        }
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $r = @ldap_modify($this->ldap->cid, $this->ldap->basedn, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            if (!$this->ldap->success()) {
                $this->ldap->error .= $this->makeReadableErrors($this->ldap->error, $attrs);
            }
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modify('.$this->ldap->basedn.')');
            return ($r ? $r : 0);
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modify('.$this->ldap->basedn.')');
            return "";
        }
    }

    function modifyBatch (array $changes)
    {
        if (count($changes) == 0) {
            return TRUE;
        }
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $r            = @ldap_modify_batch($this->ldap->cid, $this->ldap->basedn, $changes); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error  = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modifyBatch('.$this->ldap->basedn.')');
            return $r;
        } else {
            $this->ldap->error = 'Could not connect to LDAP server';
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modifyBatch('.$this->ldap->basedn.')');
            return FALSE;
        }
    }

    function rm ($attrs = "", $dn = "")
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            if ($dn == '') {
                $dn = $this->ldap->basedn;
            }

            $r = @ldap_mod_del($this->ldap->cid, $dn, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rm('.$dn.')');
            return $r;
        } else {
            $this->ldap->error = 'Could not connect to LDAP server';
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rm('.$dn.')');
            return '';
        }
    }

    function modAdd ($attrs = "", $dn = "")
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            if ($dn == "") {
                $dn = $this->ldap->basedn;
            }

            $r = @ldap_mod_add($this->ldap->cid, $dn, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modAdd('.$dn.')');
            return $r;
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'modAdd('.$dn.')');
            return "";
        }
    }

    function rmdir ($deletedn)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $r = @ldap_delete($this->ldap->cid, $deletedn); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rmdir('.$deletedn.')');
            return ($r ? $r : 0);
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rmdir('.$deletedn.')');
            return "";
        }
    }

    function renameDn ($source, $dest)
    {
        /* Check if source and destination are the same entry */
        if (strtolower($source) == strtolower($dest)) {
            trigger_error("Source and destination can't be the same entry.");
            $this->ldap->error = "Source and destination can't be the same entry.";
            return FALSE;
        }

        /* Check if destination entry exists */
        if ($this->ldap->serializer->dnExists($dest)) {
            trigger_error("Destination '$dest' already exists.");
            $this->ldap->error = "Destination '$dest' already exists.";
            return FALSE;
        }

        /* Extract the name and the parent part out ouf source dn.
            e.g.  cn=herbert,ou=department,dc=...
             parent   =>  ou=department,dc=...
             dest_rdn =>  cn=herbert
         */
        $parent   = preg_replace("/^[^,]+,/", "", $dest);
        $dest_rdn = preg_replace("/,.*$/", "", $dest);

        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            /* We have to pass TRUE as deleteoldrdn in case the attribute is single-valued */
            $r = @ldap_rename($this->ldap->cid, $source, $dest_rdn, $parent, TRUE); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */

            /* Check if destination dn exists, if not the server may not support this operation */
            $r &= $this->ldap->serializer->dnExists($dest);
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rename("'.$source.'","'.$dest.'")');
            return $r;
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rename("'.$source.'","'.$dest.'")');
            return FALSE;
        }
    }

    function rmdirRecursive ($srp, $deletedn)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $delarray = [];

            /* Get sorted list of dn's to delete */
            $this->ldap->search->cd($deletedn);
            $this->ldap->search->search($srp, '(objectClass=*)', ['dn']);
            while ($attrs = $this->ldap->search->fetch($srp)) {
                $delarray[$attrs['dn']] = strlen($attrs['dn']);
            }
            arsort($delarray);
            reset($delarray);

            /* Really Delete ALL dn's in subtree */
            $r = TRUE;
            foreach (array_keys($delarray) as $key) {
                $r = @ldap_delete($this->ldap->cid, $key); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                if ($r === FALSE) {
                    break;
                }
            }
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rmdirRecursive("'.$deletedn.'")');
            return ($r ? $r : 0);
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rmdirRecursive("'.$deletedn.'")');
            return "";
        }
    }

    function makeReadableErrors ($error, $attrs)
    {
        if ($this->ldap->success()) {
            return "";
        }

        $str = "";
        if (isset($attrs['objectClass'])
            && preg_match("/^objectClass: value #([0-9]*) invalid per syntax$/", $this->ldap->getAdditionalError(), $m)) {
            $ocs = $attrs['objectClass'];
            if (!is_array($ocs)) {
                $ocs = [$ocs];
            }
            if (isset($ocs[$m[1]])) {
                $str .= " - <b>objectClass: ".$ocs[$m[1]]."</b>";
            }
        }
        if ($error == "Undefined attribute type") {
            $str = " - <b>attribute: ".preg_replace("/:.*$/", "", $this->ldap->getAdditionalError())."</b>";
        }

        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $attrs, "Erroneous data");

        return $str;
    }

    function createMissingTrees ($srp, $target, $ignoreReferralBases = TRUE)
    {
        $real_path = substr($target, 0, strlen($target) - strlen($this->ldap->basedn) - 1);

        if ($target == $this->ldap->basedn) {
            $l = ["dummy"];
        } else {
            $l = array_reverse(ldap_explode_dn($real_path, 0));
        }
        unset($l['count']);
        $cdn = $this->ldap->basedn;

        /* Load schema if available... */
        $classes = $this->ldap->getObjectclasses();

        foreach ($l as $part) {
            if ($part != "dummy") {
                $cdn = "$part,$cdn";
            }

            /* Ignore referrals */
            if ($ignoreReferralBases) {
                $found = FALSE;
                foreach ($this->ldap->referrals as $ref) {
                    if ($ref['BASE'] == $cdn) {
                        $found = TRUE;
                        break;
                    }
                }
                if ($found) {
                    continue;
                }
            }

            /* Create missing entry? */
            if (!$this->ldap->serializer->dnExists($cdn)) {
                $type   = preg_replace('/^([^=]+)=.*$/', '\\1', $cdn);
                $param  = preg_replace('/^[^=]+=([^,]+).*$/', '\\1', $cdn);
                $param  = preg_replace(['/\\\\,/','/\\\\"/'], [',','"'], $param);

                $na = [];

                /* Automatic or traditional? */
                if (count($classes)) {
                    if ($type == 'l') {
                        /* Locality has l as MAY so autodetection fails */
                        $ocname = 'locality';
                    } else {
                        /* Get name of first matching objectClass */
                        $ocname = '';
                        foreach ($classes as $class) {
                            if (isset($class['MUST']) && in_array($type, $class['MUST'])) {
                                /* Look for first classes that is structural... */
                                if (isset($class['STRUCTURAL'])) {
                                    $ocname = $class['NAME'];
                                    break;
                                }

                                /* Look for classes that are auxiliary... */
                                if (isset($class['AUXILIARY'])) {
                                    $ocname = $class['NAME'];
                                }
                            }
                        }
                    }

                    /* Bail out, if we've nothing to do... */
                    if ($ocname == '') {
                        throw new FusionDirectoryError(htmlescape(sprintf(_('Cannot automatically create subtrees with RDN "%s": no object class found!'), $type)));
                    }

                    /* Assemble_entry */
                    $na['objectClass'] = [$ocname];
                    if (isset($classes[$ocname]['AUXILIARY'])) {
                        $na['objectClass'][] = $classes[$ocname]['SUP'];
                    }
                    if ($type == 'dc') {
                        /* This is bad actually, but - tell me a better way? */
                        $na['objectClass'][]  = 'organization';
                        $na['o']              = $param;
                    }
                    $na[$type] = $param;

                    // Fill in MUST values - but do not overwrite existing ones.
                    $oc = $ocname;
                    do {
                        if (isset($classes[$oc]['MUST']) && is_array($classes[$oc]['MUST'])) {
                            foreach ($classes[$oc]['MUST'] as $attr) {
                                if (isset($na[$attr]) && !empty($na[$attr])) {
                                    continue;
                                }
                                $na[$attr] = 'filled';
                            }
                        }
                        $oc = ($classes[$oc]['SUP'] ?? NULL);
                    } while ($oc);
                } else {
                    /* Use alternative add... */
                    switch ($type) {
                        case 'ou':
                            $na['objectClass']  = 'organizationalUnit';
                            $na['ou']           = $param;
                            break;
                        case 'dc':
                            $na['objectClass']  = ['dcObject', 'top', 'organization'];
                            $na['dc']           = $param;
                            $na['o']            = $param;
                            break;
                        default:
                            throw new FusionDirectoryError(htmlescape(sprintf(_('Cannot automatically create subtrees with RDN "%s": not supported'), $type)));
                    }
                }
                $this->ldap->search->cd($cdn);
                $this->add($na);

                if (!$this->ldap->success()) {
                    Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $cdn, 'dn');
                    Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $na, 'Content');
                    Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->getError(), 'LDAP error');

                    throw new FusionDirectoryLdapError($cdn, LDAP_ADD, $this->ldap->getError(), $this->ldap->getErrno());
                }
            }
        }
    }
}

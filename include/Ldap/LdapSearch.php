<?php
declare(strict_types=1);

/**
 * Handles LDAP read/search operations.
 */
class LdapSearch
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    function getSearchResource ()
    {
        $this->ldap->sr[$this->ldap->srp]     = NULL;
        $this->ldap->start[$this->ldap->srp]  = 0;
        $this->ldap->hasres[$this->ldap->srp] = FALSE;
        return $this->ldap->srp++;
    }

    function search ($srp, $filter, $attrs = [], $scope = 'subtree', ?array $controls = NULL)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }

            $startTime = microtime(TRUE);
            $this->clearResult($srp);
            switch (strtolower($scope)) {
                case 'base':
                    if (isset($controls)) {
                        $this->ldap->sr[$srp] = @ldap_read($this->ldap->cid, $this->ldap->basedn, $filter, $attrs, 0, 0, 0, LDAP_DEREF_NEVER, $controls); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    } else {
                        $this->ldap->sr[$srp] = @ldap_read($this->ldap->cid, $this->ldap->basedn, $filter, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    }
                    break;
                case 'one':
                    if (isset($controls)) {
                        $this->ldap->sr[$srp] = @ldap_list($this->ldap->cid, $this->ldap->basedn, $filter, $attrs, 0, 0, 0, LDAP_DEREF_NEVER, $controls); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    } else {
                        $this->ldap->sr[$srp] = @ldap_list($this->ldap->cid, $this->ldap->basedn, $filter, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    }
                    break;
                case 'subtree':
                default:
                    if (isset($controls)) {
                        $this->ldap->sr[$srp] = @ldap_search($this->ldap->cid, $this->ldap->basedn, $filter, $attrs, 0, 0, 0, LDAP_DEREF_NEVER, $controls); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    } else {
                        $this->ldap->sr[$srp] = @ldap_search($this->ldap->cid, $this->ldap->basedn, $filter, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    }
                    break;
            }
            $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->resetResult($srp);

            /* Set hasres to TRUE if we got a result or FALSE if $this->sr[$srp] is FALSE */
            if ($this->ldap->sr[$srp] === FALSE) {
                $this->ldap->hasres[$srp] = FALSE;
            } else {
                $this->ldap->hasres[$srp] = TRUE;
            }

            /* Check if query took longer as specified in max_ldap_query_time */
            $diff = microtime(TRUE) - $startTime;
            if ($this->ldap->max_ldap_query_time && ($diff > $this->ldap->max_ldap_query_time)) {
                $warning = new FusionDirectoryWarning(htmlescape(sprintf(_('LDAP performance is poor: last query took about %.2fs!'), $diff)));
                $warning->display();
            }

            $this->ldap->log("LDAP operation: time=".$diff." operation=search('".$this->ldap->basedn."', '$filter')");
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'search(base="'.$this->ldap->basedn.'",scope="'.$scope.'",filter="'.$filter.'")');
            return $this->ldap->sr[$srp];
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'search(base="'.$this->ldap->basedn.'",scope="'.$scope.'",filter="'.$filter.'")');
            return "";
        }
    }

    function parseResult ($srp): array
    {
        if ($this->ldap->hascon && $this->ldap->hasres[$srp]) {
            if (ldap_parse_result($this->ldap->cid, $this->ldap->sr[$srp], $errcode, $matcheddn, $errmsg, $referrals, $controls)) {
                return [$errcode, $matcheddn, $errmsg, $referrals, $controls];
            }
            throw new FusionDirectoryException(_('Parsing LDAP result failed'));
        } else {
            throw new FusionDirectoryException(_('No LDAP result to parse'));
        }
    }

    function cat ($srp, $dn, $attrs = ["*"], $filter = "(objectclass=*)")
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }

            $this->clearResult($srp);
            $this->ldap->sr[$srp] = @ldap_read($this->ldap->cid, $dn, $filter, $attrs); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error    = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->resetResult($srp);

            /* Set hasres to TRUE if we got a result or FALSE if $this->sr[$srp] is FALSE */
            if ($this->ldap->sr[$srp] === FALSE) {
                $this->ldap->hasres[$srp] = FALSE;
            } else {
                $this->ldap->hasres[$srp] = TRUE;
            }

            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'cat(dn="'.$dn.'",filter="'.$filter.'")');
            return $this->ldap->sr[$srp];
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'cat(dn="'.$dn.'",filter="'.$filter.'")');
            return "";
        }
    }

    function objectMatchFilter ($dn, $filter)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->reconnect) {
                $this->ldap->connection->connect();
            }
            $res  = @ldap_read($this->ldap->cid, $dn, $filter, ["objectClass"]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            if ($res !== FALSE) {
                Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'objectMatchFilter(dn="'.$dn.'",filter="'.$filter.'")');
                return @ldap_count_entries($this->ldap->cid, $res); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            } else {
                $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                return FALSE;
            }
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'objectMatchFilter(dn="'.$dn.'",filter="'.$filter.'")');
            return FALSE;
        }
    }

    function setSizeLimit ($size)
    {
        /* Ignore zero settings */
        if ($size == 0) {
            @ldap_set_option($this->ldap->cid, LDAP_OPT_SIZELIMIT, 10000000); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
        }
        if ($this->ldap->hascon) {
            @ldap_set_option($this->ldap->cid, LDAP_OPT_SIZELIMIT, $size); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
        }
        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $size, 'setSizeLimit');
    }

    function fetch ($srp, bool $cleanUpNumericIndices = FALSE)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->hasres[$srp]) {
                if ($this->ldap->start[$srp] == 0) {
                    if ($this->ldap->sr[$srp]) {
                        $this->ldap->start[$srp]  = 1;
                        $this->ldap->re[$srp]     = @ldap_first_entry($this->ldap->cid, $this->ldap->sr[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    } else {
                        return [];
                    }
                } else {
                    $this->ldap->re[$srp] = @ldap_next_entry($this->ldap->cid, $this->ldap->re[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                }
                $att = [];
                if ($this->ldap->re[$srp]) {
                    $att        = @ldap_get_attributes($this->ldap->cid, $this->ldap->re[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    $att['dn']  = trim(@ldap_get_dn($this->ldap->cid, $this->ldap->re[$srp])); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    if ($cleanUpNumericIndices && isset($att['count'])) {
                        for ($i = 0; $i < $att['count']; ++$i) {
                            /* Remove numeric keys */
                            unset($att[$i]);
                        }
                        unset($att['count']);
                    }
                }
                $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'fetch()');
                return $att;
            } else {
                $this->ldap->error = "Perform a fetch with no search";
                Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'fetch()');
                return "";
            }
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'fetch()');
            return "";
        }
    }

    function resetResult ($srp)
    {
        $this->ldap->start[$srp] = 0;
    }

    function clearResult ($srp)
    {
        if ($this->ldap->hasres[$srp]) {
            $this->ldap->hasres[$srp] = FALSE;
            @ldap_free_result($this->ldap->sr[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
        }
    }

    function getDN ($srp)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->hasres[$srp]) {
                if (!$this->ldap->re[$srp]) {
                    $this->ldap->error = "Perform a Fetch with no valid Result";
                } else {
                    $rv = @ldap_get_dn($this->ldap->cid, $this->ldap->re[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */

                    $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    return trim($rv);
                }
            } else {
                $this->ldap->error = "Perform a Fetch with no Search";
                return "";
            }
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            return "";
        }
    }

    function count ($srp)
    {
        if ($this->ldap->hascon) {
            if ($this->ldap->hasres[$srp]) {
                $rv = @ldap_count_entries($this->ldap->cid, $this->ldap->sr[$srp]); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'count()');
                return $rv;
            } else {
                $this->ldap->error = "Perform a Fetch with no Search";
                Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'count()');
                return "";
            }
        } else {
            $this->ldap->error = "Could not connect to LDAP server";
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'count()');
            return "";
        }
    }

    function cd ($dir)
    {
        if ($dir == '..') {
            $this->ldap->basedn = $this->getParentDir();
        } else {
            $this->ldap->basedn = $dir;
        }
    }

    function getParentDir ($basedn = '')
    {
        if ($basedn == '') {
            $basedn = $this->ldap->basedn;
        }
        return preg_replace("/[^,]*[,]*[ ]*(.*)/", "$1", $basedn);
    }
}

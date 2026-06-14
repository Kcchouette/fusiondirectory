<?php
declare(strict_types=1);

/**
 * Handles LDAP connection lifecycle.
 */
class LdapConnection
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    function connect ()
    {
        $this->ldap->hascon     = FALSE;
        $this->ldap->reconnect  = FALSE;
        if ($this->ldap->cid = @ldap_connect($this->ldap->hostname)) { /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            @ldap_set_option($this->ldap->cid, LDAP_OPT_PROTOCOL_VERSION, 3); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            if ($this->ldap->follow_referral) {
                @ldap_set_option($this->ldap->cid, LDAP_OPT_REFERRALS, 1); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                @ldap_set_rebind_proc($this->ldap->cid, [$this, 'rebind']); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            }
            if ($this->ldap->tls) {
                if (!@ldap_start_tls($this->ldap->cid)) { /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    $this->ldap->error = @ldap_error($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                    Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'connect: TLS failed');
                    return;
                }
            }

            $this->ldap->error = 'No Error';
            $serverctrls = [];
            if (class_available('ppolicyAccount')) {
                $serverctrls = [['oid' => LDAP_CONTROL_PASSWORDPOLICYREQUEST]];
            }
            $result = @ldap_bind_ext($this->ldap->cid, $this->ldap->binddn, $this->ldap->bindpw, $serverctrls); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            if (@ldap_parse_result($this->ldap->cid, $result, $errcode, $matcheddn, $errmsg, $referrals, $ctrls)) { /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
                if (isset($ctrls[LDAP_CONTROL_PASSWORDPOLICYRESPONSE]['value']['error'])) {
                    $this->ldap->hascon = FALSE;
                    switch ($ctrls[LDAP_CONTROL_PASSWORDPOLICYRESPONSE]['value']['error']) {
                        case 0:
                            /* passwordExpired - password has expired and must be reset */
                            $this->ldap->error = _('It seems your user password has expired. Please use <a href="recovery.php">password recovery</a> to change it.');
                            break;
                        case 1:
                            /* accountLocked */
                            $this->ldap->error = _('Account locked. Please contact your system administrator!');
                            break;
                        case 2:
                            /* changeAfterReset - password must be changed before the user will be allowed to perform any other operation */
                            $this->ldap->error = 'changeAfterReset';
                            break;
                        case 3:
                            /* passwordModNotAllowed */
                        case 4:
                            /* mustSupplyOldPassword */
                        case 5:
                            /* insufficientPasswordQuality */
                        case 6:
                            /* passwordTooShort */
                        case 7:
                            /* passwordTooYoung */
                        case 8:
                            /* passwordInHistory */
                        default:
                            $this->ldap->error = sprintf(_('Unexpected ppolicy error "%s", please contact the administrator'), $ctrls[LDAP_CONTROL_PASSWORDPOLICYRESPONSE]['value']['error']);
                            break;
                    }
                    // Note: Also available: expire, grace
                } else {
                    $this->ldap->hascon = ($errcode == 0);
                    if ($errcode == 49) {
                        $this->ldap->error = LDAP::invalidCredentialsError();
                    } elseif (empty($errmsg)) {
                        $this->ldap->error = ldap_err2str($errcode);
                    } else {
                        $this->ldap->error = $errmsg;
                    }
                }
            } else {
                $this->ldap->error  = 'Parsing of LDAP result from bind failed';
                $this->ldap->hascon = FALSE;
            }
        } else {
            $this->ldap->error = 'Could not connect to LDAP server';
        }

        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'connect');
    }

    function rebind ($ldap, $referral)
    {
        $credentials = $this->getCredentials($referral);
        if (@ldap_bind($ldap, $credentials['ADMINDN'], $credentials['ADMINPASSWORD'])) { /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->error      = "Success";
            $this->ldap->hascon     = TRUE;
            $this->ldap->reconnect  = TRUE;
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rebind');
            return 0;
        } else {
            $this->ldap->error = "Could not bind to " . $credentials['ADMINDN'];
            Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, $this->ldap->error, 'rebind');
            return NULL;
        }
    }

    function reconnect ()
    {
        if ($this->ldap->reconnect) {
            $this->unbind();
        }
    }

    function unbind ()
    {
        @ldap_unbind($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
        $this->ldap->cid = FALSE;
        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, '', 'unbind');
    }

    function disconnect ()
    {
        if ($this->ldap->hascon) {
            @ldap_close($this->ldap->cid); /* @phpstan-ignore-line — PHP LDAP functions emit warnings on failure */
            $this->ldap->hascon = FALSE;
            $this->ldap->cid    = FALSE;
        }
        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, '', 'disconnect');
    }

    public function isConnected(): bool
    {
        return $this->ldap->hascon;
    }

    function getCredentials ($url, $referrals = NULL)
    {
        $ret    = [];
        $url    = preg_replace('!\?\?.*$!', '', $url);
        $server = preg_replace('!^([^:]+://[^/]+)/.*$!', '\\1', $url);

        if ($referrals === NULL) {
            $referrals = $this->ldap->referrals;
        }

        if (isset($referrals[$server])) {
            return $referrals[$server];
        } else {
            $ret['ADMINDN']       = $this->ldap->binddn;
            $ret['ADMINPASSWORD'] = $this->ldap->bindpw;
        }

        return $ret;
    }
}

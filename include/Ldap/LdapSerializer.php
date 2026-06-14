<?php
declare(strict_types=1);

/**
 * Handles LDIF serialization and deserialization.
 */
class LdapSerializer
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    function generateLdif (string $dn, string $filter = '(objectClass=*)', string $scope = 'sub', int $limit = 0, ?int $wrap = NULL): string
    {
        $limit  = (($limit == 0) ? '' : ' -z '.$limit);
        if ($wrap === NULL) {
            $wrap = '';
        } else {
            $wrap = ' -o ldif-wrap='.($wrap ? $wrap : 'no');
        }

        // Check scope values
        $scope = trim($scope);
        if (!empty($scope) && !in_array($scope, ['base', 'one', 'sub', 'children'])) {
            throw new LDIFExportException(sprintf('Invalid parameter for scope "%s", please use "base", "one", "sub" or "children".', $scope));
        }
        $scope = (empty($scope) ? '' : ' -s '.$scope);

        // Prepare parameters to be valid for shell execution
        $dn     = escapeshellarg($dn);
        $pwd    = escapeshellarg($this->ldap->bindpw);
        $host   = escapeshellarg($this->ldap->hostname);
        $admin  = escapeshellarg($this->ldap->binddn);
        $filter = escapeshellarg($filter);

        $cmd = 'ldapsearch'.($this->ldap->tls ? ' -ZZ' : '')." -x -LLLL -D {$admin} {$filter} {$limit} {$wrap} {$scope} -H {$host} -b {$dn} -w {$pwd} ";

        // Create list of process pipes
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        // Try to open the process
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if ($process !== FALSE) {
            // Write the password to stdin
            fclose($pipes[0]);

            // Get results from stdout and stderr
            $res = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);

            // Close the process and check its return value
            if (proc_close($process) != 0) {
                throw new LDIFExportException($err);
            }
        } else {
            throw new LDIFExportException(_('proc_open failed to execute ldapsearch'));
        }
        return $res;
    }

    function dnExists ($dn): bool
    {
        Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, '', 'dnExists('.$dn.')');
        return (@ldap_read($this->ldap->cid, $dn, '(objectClass=*)', ['objectClass']) !== FALSE);
    }

    function parseLdif (string $str_attr): array
    {
        /* First we split the string into lines */
        $fileLines = preg_split("/\n/", $str_attr);
        if (end($fileLines) != '') {
            $fileLines[] = '';
        }

        /* Joining lines */
        $line       = NULL;
        $entry      = [];
        $entries    = [];
        $entryStart = -1;
        foreach ($fileLines as $lineNumber => $fileLine) {
            if (preg_match('/^ /', $fileLine)) {
                if ($line === NULL) {
                    throw new LDIFImportException(sprintf(_('Error line %s, first line of an entry cannot start with a space'), $lineNumber));
                }
                /* Append to current line */
                $line .= substr($fileLine, 1);
            } else {
                if ($line !== NULL) {
                    if (preg_match('/^#/', $line)
                        || (preg_match('/^version:/', $line) && empty($entry))) {
                        /* Ignore comment */
                        /* Ignore version number */
                    } else {
                        /* Line has ended */
                        list ($key, $value) = explode(':', $line, 2);
                        $value = trim($value);
                        if (preg_match('/^:/', $value)) {
                            $value = base64_decode(trim(substr($value, 1)));
                        }
                        if (preg_match('/^</', $value)) {
                            throw new LDIFImportException(sprintf(_('Error line %s, references to an external file are not supported'), $lineNumber));
                        }
                        if ($value === '') {
                            throw new LDIFImportException(sprintf(_('Error line %s, attribute "%s" has no value'), $lineNumber, $key));
                        }
                        if ($key == 'dn') {
                            if (!empty($entry)) {
                                throw new LDIFImportException(sprintf(_('Error line %s, an entry bloc can only have one dn'), $lineNumber));
                            }
                            $entry['dn']  = $value;
                            $entryStart   = $lineNumber;
                        } elseif (empty($entry)) {
                            throw new LDIFImportException(sprintf(_('Error line %s, an entry bloc should start with the dn'), $lineNumber));
                        } else {
                            if (!isset($entry[$key])) {
                                $entry[$key] = [];
                            }
                            $entry[$key][] = $value;
                        }
                    }
                }
                /* Start new line */
                $line = trim($fileLine);
                if ($line == '') {
                    if (!empty($entry)) {
                        /* Entry is finished */
                        $entries[$entryStart] = $entry;
                    }
                    /* Start a new entry */
                    $entry      = [];
                    $entryStart = -1;
                    $line       = NULL;
                }
            }
        }

        return $entries;
    }

    function importCompleteLdif ($srp, $str_attr, $JustModify, $DeleteOldEntries)
    {
        $entries = $this->parseLdif($str_attr);

        if ($this->ldap->reconnect) {
            $this->ldap->connection->connect();
        }

        foreach ($entries as $startLine => $entry) {
            /* Delete before insert */
            $usermdir = ($this->dnExists($entry['dn']) && $DeleteOldEntries);
            /* Should we use Modify instead of Add */
            $usemodify = ($this->dnExists($entry['dn']) && $JustModify);

            /* If we can't Import, return with a file error */
            if (!$this->importSingleEntry($srp, $entry, $usemodify, $usermdir)) {
                throw new LDIFImportException(sprintf(_('Error while importing dn: "%s", please check your LDIF from line %s on!'), $entry['dn'][0], $startLine));
            }
        }

        return count($entries);
    }

    function importSingleEntry ($srp, $data, $modify, $delete)
    {

        if (!config()) {
            trigger_error("Can't import ldif, can't read config object.");
        }

        if ($this->ldap->reconnect) {
            $this->ldap->connection->connect();
        }

        $ret        = FALSE;
        $dn         = NULL;
        $operation  = NULL;

        /* If dn is an index of data, we should try to insert the data */
        if (isset($data['dn'])) {
            /* Fix dn */
            $tmp = ldap_explode_dn($data['dn'], 0);
            unset($tmp['count']);
            $dn = '';
            foreach ($tmp as $tm) {
                $dn .= trim($tm).',';
            }
            $dn = preg_replace('/,$/', '', $dn);
            unset($data['dn']);

            /* Creating Entry */
            $this->ldap->search->cd($dn);

            /* Delete existing entry */
            if ($delete) {
                $this->ldap->writer->rmdirRecursive($srp, $dn);
            }

            /* Create missing trees */
            $this->ldap->search->cd(config()->current['BASE']);
            try {
                $this->ldap->writer->createMissingTrees($srp, preg_replace('/^[^,]+,/', '', $dn));
            } catch (FusionDirectoryError $error) {
                $error->display();
            }
            $this->ldap->search->cd($dn);

            $operation = LDAP_MOD;
            if (!$modify) {
                $this->ldap->search->cat($srp, $dn);
                if ($this->ldap->search->count($srp)) {
                    /* The destination entry exists, overwrite it with the new entry */
                    $attrs = $this->ldap->search->fetch($srp);
                    foreach (array_keys($attrs) as $name) {
                        if (!is_numeric($name)) {
                            if (in_array($name, ['dn','count'])) {
                                continue;
                            }
                            if (!isset($data[$name])) {
                                $data[$name] = [];
                            }
                        }
                    }
                    $ret = $this->ldap->writer->modify($data);
                } else {
                    /* The destination entry doesn't exists, create it */
                    $operation = LDAP_ADD;
                    $ret = $this->ldap->writer->add($data);
                }
            } else {
                /* Keep all vars that aren't touched by this ldif */
                $ret = $this->ldap->writer->modify($data);
            }
        }

        if (!$this->ldap->success()) {
            $error = new FusionDirectoryLdapError($dn, $operation, $this->ldap->getError(), $this->ldap->getErrno());
            $error->display();
        }


        return $ret;
    }
}

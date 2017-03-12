<?php

/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2013, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

/**
 * The Lock manager allows you to handle all file-locks centrally.
 * It stores all its data in a sql database. Derived from SabreDAV's
 * PDO Lock manager.
 */
class file_locks {

    const SHARED    = 1;
    const EXCLUSIVE = 2;
    const INFINITE  = -1;

    /**
     * The database connection object
     *
     * @var rcube_db
     */
    private $db;

    /**
     * The tablename this backend uses.
     *
     * @var string
     */
    protected $table;

    /**
     * Internal cache
     *
     * @var array
     */
    protected $icache = array();

    /**
     * Constructor
     *
     * @param string $table Table name
     */
    public function __construct($table = 'chwala_locks')
    {
        $rcube = rcube::get_instance();

        $this->db    = $rcube->get_dbh();
        $this->table = $this->db->table_name($table);

        if ($rcube->session) {
            $rcube->session->register_gc_handler(array($this, 'gc'));
        }
        else {
            // run garbage collector with probability based on
            // session settings if session does not exist.
            $probability = (int) ini_get('session.gc_probability');
            $divisor     = (int) ini_get('session.gc_divisor');

            if ($divisor > 0 && $probability > 0) {
                $random = mt_rand(1, $divisor);
                if ($random <= $probability) {
                    $this->gc();
                }
            }
        }
    }

    /**
     * Returns a list of locks
     *
     * This method should return all the locks for a particular URI, including
     * locks that might be set on a parent URI.
     *
     * If child_locks is set to true, this method should also look for
     * any locks in the subtree of the URI for locks.
     *
     * @param string $uri         URI
     * @param bool   $child_locks Enables subtree checks
     *
     * @return array List of locks
     */
    public function lock_list($uri, $child_locks = false)
    {
        if ($this->icache['uri'] == $uri && $this->icache['child'] == $child_locks) {
            return $this->icache['list'];
        }

        $query  = "SELECT * FROM " . $this->db->quote_identifier($this->table) . " WHERE (uri = ?";
        $params = array($uri);

        if ($child_locks) {
            $query   .= " OR uri LIKE ?";
            $params[] = $uri . '/%';
        }

        $path = '';
        $key  = $uri;
        $list = array();

        // in case uri contains protocol/host specification e.g. imap://user@host/
        // handle prefix separately
        if (preg_match('~^([a-z]+://[^/]+/)~i', $uri, $matches)) {
            $path = $matches[1];
            $uri  = substr($uri, strlen($matches[1]));
        }

        // We need to check locks for every part in the path
        $path_parts = explode('/', $uri);

        // We already covered the last part of the uri
        array_pop($path_parts);

        if (!empty($path_parts)) {
            $root_path = $path . implode('/', $path_parts);

            // this path is already cached, extract locks from cached result
            // we do this because it is a common scenario to request
            // for lock on every file/folder in specified location
            if ($this->icache['root_path'] == $root_path) {
                $length = strlen($root_path);
                foreach ($this->icache['list'] as $lock) {
                    if ($lock['depth'] != 0 && strlen($lock['token']) <= $length) {
                        $list[] = $lock;
                    }
                }
            }
            else {
                foreach ($path_parts as $part) {
                    $path     .= $part;
                    $params[]  = $path;
                    $path     .= '/';
                }

                $query .= " OR (uri IN (" . implode(',', array_pad(array(), count($path_parts), '?')) . ") AND depth <> 0)";
            }
        }

        // finally, skip expired locks
        $query .= ") AND expires > " . $this->db->now();

        // run the query and parse result
        $result = $this->db->query($query, $params);

        while ($row = $this->db->fetch_assoc($result)) {
            $created = strtotime($row['expires']) - $row['timeout'];
            $list[] = array(
                'uri'     => $row['uri'],
                'owner'   => $row['owner'],
                'token'   => $row['token'],
                'timeout' => (int) $row['timeout'],
                'created' => (int) $created,
                'scope'   => $row['scope'] == self::EXCLUSIVE ? file_storage::LOCK_EXCLUSIVE : file_storage::LOCK_SHARED,
                'depth'   => $row['depth'] == self::INFINITE ? file_storage::LOCK_INFINITE : (int) $row['depth'],
            );
        }

        // remember last result in memory, sometimes we need it (or part of it) again
        $this->icache['list']        = $list;
        $this->icache['uri']         = $key;
        $this->icache['root_path']   = $root_path;
        $this->icache['child_locks'] = $child_locks;

        return $list;
    }

    /**
     * Locks a uri
     *
     * @param string $uri  URI
     * @param array  $lock Lock data
     *
     * @return bool
     */
    public function lock($uri, $lock)
    {
        // We're making the lock timeout max. 30 minutes
        $timeout = min($lock['timeout'], 30*60);

        $data = array(
            $this->db->quote_identifier('uri')     => $uri,
            $this->db->quote_identifier('owner')   => $lock['owner'],
            $this->db->quote_identifier('scope')   => $lock['scope'] == file_storage::LOCK_EXCLUSIVE ? self::EXCLUSIVE : self::SHARED,
            $this->db->quote_identifier('depth')   => $lock['depth'] == file_storage::LOCK_INFINITE ? self::INFINITE : 0,
            $this->db->quote_identifier('timeout') => $timeout,
        );

        // check if lock exists
        $locks  = $this->lock_list($uri, false);
        $exists = false;

        foreach ($locks as $l) {
            if ($l['token'] == $lock['token']) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            foreach (array_keys($data) as $key) {
                $update_cols[] = "$key = ?";
            }

            $result = $this->db->query("UPDATE " . $this->db->quote_identifier($this->table)
                . " SET " . implode(', ', $update_cols)
                    . ", " . $this->db->quote_identifier('expires') . " = " . $this->db->now($timeout)
                . " WHERE token = ?",
                array_merge(array_values($data), array($lock['token']))
            );
        }
        else {
            $data[$this->db->quote_identifier('token')] = $lock['token'];

            $result = $this->db->query("INSERT INTO " . $this->db->quote_identifier($this->table)
                . " (".join(', ', array_keys($data)) . ", " . $this->db->quote_identifier('expires') . ")"
                . " VALUES (" . str_repeat('?, ', count($data)) . $this->db->now($timeout) . ")",
                array_values($data)
            );
        }

        return $this->db->affected_rows();
    }

    /**
     * Removes a lock from a URI
     *
     * @param string $path URI
     * @param array  $lock Lock data
     *
     * @return bool
     */
    public function unlock($uri, $lock)
    {
        $stmt = $this->db->query("DELETE FROM " . $this->db->quote_identifier($this->table)
            . " WHERE uri = ? AND token = ?",
            $uri, $lock['token']);

        return $this->db->affected_rows();
    }

    /**
     * Remove expired locks
     */
    public function gc()
    {
        $this->db->query("DELETE FROM " . $this->db->quote_identifier($this->table)
            . " WHERE expires < " . $this->db->now());
    }
}

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

interface file_storage
{
    // capabilities
    const CAPS_ACL           = 'ACL';
    const CAPS_MAX_UPLOAD    = 'MAX_UPLOAD';
    const CAPS_PROGRESS_NAME = 'PROGRESS_NAME';
    const CAPS_PROGRESS_TIME = 'PROGRESS_TIME';
    const CAPS_QUOTA         = 'QUOTA';
    const CAPS_LOCKS         = 'LOCKS';

    // config
    const SEPARATOR = '/';

    // error codes
    const ERROR             = 500;
    const ERROR_LOCKED      = 423;
    const ERROR_FILE_EXISTS = 550;
    const ERROR_UNSUPPORTED = 570;

    // locks
    const LOCK_SHARED    = 'shared';
    const LOCK_EXCLUSIVE = 'exclusive';
    const LOCK_INFINITE  = 'infinite';

    /**
     * Authenticates a user
     *
     * @param string $username User name
     * @param string $password User password
     *
     * @return bool True on success, False on failure
     */
    public function authenticate($username, $password);

    /**
     * Configures environment
     *
     * @param array $config COnfiguration
     */
    public function configure($config);

    /**
     * Storage driver capabilities
     *
     * @return array List of capabilities
     */
    public function capabilities();

    /**
     * Create a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param array  $file      File data (path/content, type)
     *
     * @throws Exception
     */
    public function file_create($file_name, $file);

    /**
     * Update a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param array  $file      File data (path/content, type)
     *
     * @throws Exception
     */
    public function file_update($file_name, $file);

    /**
     * Delete a file.
     *
     * @param string $file_name Name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_delete($file_name);

    /**
     * Returns file body.
     *
     * @param string   $file_name Name of a file (with folder path)
     * @param array    $params    Parameters (force-download)
     * @param resource $fp        Print to file pointer instead (send no headers)
     *
     * @throws Exception
     */
    public function file_get($file_name, $params = array(), $fp = null);

    /**
     * Move (or rename) a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param string $new_name  New name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_move($file_name, $new_name);

    /**
     * Copy a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param string $new_name  New name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_copy($file_name, $new_name);

    /**
     * Returns file metadata.
     *
     * @param string $file_name Name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_info($file_name);

    /**
     * List files in a folder.
     *
     * @param string $folder_name Name of a folder with full path
     * @param array  $params      List parameters ('sort', 'reverse', 'search')
     *
     * @return array List of files (file properties array indexed by filename)
     * @throws Exception
     */
    public function file_list($folder_name, $params = array());

    /**
     * Create a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception
     */
    public function folder_create($folder_name);

    /**
     * Delete a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception
     */
    public function folder_delete($folder_name);

    /**
     * Move/Rename a folder.
     *
     * @param string $folder_name Name of a folder with full path
     * @param string $new_name    New name of a folder with full path
     *
     * @throws Exception
     */
    public function folder_move($folder_name, $new_name);

    /**
     * Returns list of folders.
     *
     * @return array List of folders
     * @throws Exception
     */
    public function folder_list();

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
     * @throws Exception
     */
    public function lock_list($uri, $child_locks = false);

    /**
     * Locks a URI
     *
     * @param string $uri  URI
     * @param array  $lock Lock data
     *                     - depth: 0/'infinite'
     *                     - scope: 'shared'/'exclusive'
     *                     - owner: string
     *                     - token: string
     *                     - timeout: int
     *
     * @throws Exception
     */
    public function lock($uri, $lock);

    /**
     * Removes a lock from a URI
     *
     * @param string $path URI
     * @param array  $lock Lock data
     *
     * @throws Exception
     */
    public function unlock($uri, $lock);

    /**
     * Return disk quota information for specified folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @return array Quota
     * @throws Exception
     */
    public function quota($folder);
}

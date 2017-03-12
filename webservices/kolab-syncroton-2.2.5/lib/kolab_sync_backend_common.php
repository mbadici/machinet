<?php

/**
 +--------------------------------------------------------------------------+
 | Kolab Sync (ActiveSync for Kolab)                                        |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG <contact@kolabsys.com>         |
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
 * Parent backend class for kolab backends
 */
class kolab_sync_backend_common implements Syncroton_Backend_IBackend
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table_name;

    /**
     * Model interface name
     *
     * @var string
     */
    protected $interface_name;

    /**
     * Backend interface name
     *
     * @var string
     */
    protected $class_name;

    /**
     * SQL Database engine
     *
     * @var rcube_db
     */
    protected $db;

    /**
     * Internal cache (in-memory)
     *
     * @var array
     */
    protected $cache = array();


    /**
     * Constructor
     */
    function __construct()
    {
        $this->db = rcube::get_instance()->get_dbh();

        if (empty($this->class_name)) {
            $this->class_name = str_replace('Model_I', 'Model_', $this->interface_name);
        }
    }

    /**
     * Creates new Syncroton object in database
     *
     * @param Syncroton_Model_* $object Object
     *
     * @throws InvalidArgumentException
     * @return Syncroton_Model_* Object
     */
    public function create($object)
    {
        if (! $object instanceof $this->interface_name) {
            throw new InvalidArgumentException('$object must be instanace of ' . $this->interface_name);
        }

        $data   = $this->object_to_array($object);
        $insert = array();

        $data['id'] = $object->id = sha1(mt_rand(). microtime());

        foreach ($data as $key => $value) {
            $insert[$this->db->quote_identifier($key)] = $this->db->quote($value);
        }

        $this->db->query('INSERT INTO ' . $this->table_name
            . ' (' . implode(', ', array_keys($insert)) . ')' . ' VALUES(' . implode(', ', $insert) . ')');

        if (!$this->db->insert_id($this->table_name)) {
            // @TODO: throw exception
        }

        return $object;
    }

    /**
     * Returns Syncroton data object
     *
     * @param string  $id
     * @throws Syncroton_Exception_NotFound
     * @return Syncroton_Model_*
     */
    public function get($id)
    {
        $id = $id instanceof $this->interface_name ? $id->id : $id;

        if ($id) {
            $select = $this->db->query('SELECT * FROM ' . $this->table_name . ' WHERE id = ?', array($id));
            $data   = $this->db->fetch_assoc($select);
        }

        if (empty($data)) {
            throw new Syncroton_Exception_NotFound('Object not found');
        }

        return $this->get_object($data);
    }

    /**
     * Deletes Syncroton data object
     *
     * @param string|Syncroton_Model_* $id Object or identifier
     *
     * @return bool True on success, False on failure
     */
    public function delete($id)
    {
        $id = $id instanceof $this->interface_name ? $id->id : $id;

        if (!$id) {
            return false;
        }

        $result = $this->db->query('DELETE FROM ' . $this->table_name .' WHERE id = ?', array($id));

        return (bool) $this->db->affected_rows($result);
    }

    /**
     * Updates Syncroton data object
     *
     * @param Syncroton_Model_* $object
     *
     * @throws InvalidArgumentException
     * @return Syncroton_Model_* Object
     */
    public function update($object)
    {
        if (! $object instanceof $this->interface_name) {
            throw new InvalidArgumentException('$object must be instanace of ' . $this->interface_name);
        }

        $data = $this->object_to_array($object);
        $set  = array();

        foreach ($data as $key => $value) {
            $set[] = $this->db->quote_identifier($key) . ' = ' . $this->db->quote($value);
        }

        $this->db->query('UPDATE ' . $this->table_name . ' SET ' . implode(', ', $set)
            . ' WHERE ' . $this->db->quote_identifier('id') . ' = ' . $this->db->quote($object->id));

        return $object;
    }

    /**
     * Convert array into model object
     */
    protected function get_object($data)
    {
        foreach ($data as $key => $value) {
            unset($data[$key]);

            if (!empty($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $value)) { // 2012-08-12 07:43:26
                $value = new DateTime($value, new DateTimeZone('utc'));
            }

            $data[$this->to_camelcase($key, false)] = $value;
        }

        return new $this->class_name($data);
    }

    /**
     * Converts model object into array
     */
    protected function object_to_array($object)
    {
        $data = array();

        foreach ($object as $key => $value) {
            if ($value instanceof DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value) && isset($value->id)) {
                $value = $value->id;
            }

            $data[$this->from_camelcase($key)] = $value;
        }

        return $data;
    }

    /**
     * Convert property name from camel-case to lower-case-with-underscore
     */
    protected function from_camelcase($string)
    {
        $string = lcfirst($string);

        return preg_replace_callback('/([A-Z])/', function ($string) { return '_' . strtolower($string[0]); }, $string);
    }

    /**
     * Convert property name from lower-case-with-underscore to camel-case
     */
    protected function to_camelcase($string, $ucFirst = true)
    {
        if ($ucFirst) {
            $string = ucfirst($string);
        }

        return preg_replace_callback('/_([a-z])/', function ($string) { return strtoupper($string[1]); }, $string);
    }
}

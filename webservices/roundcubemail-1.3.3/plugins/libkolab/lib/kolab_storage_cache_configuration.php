<?php

/**
 * Kolab storage cache class for configuration objects
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2013, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class kolab_storage_cache_configuration extends kolab_storage_cache
{
    protected $extra_cols = array('type');

    /**
     * Helper method to convert the given Kolab object into a dataset to be written to cache
     *
     * @override
     */
    protected function _serialize($object)
    {
        $sql_data = parent::_serialize($object);
        $sql_data['type'] = $object['type'];

        return $sql_data;
    }

    /**
     * Select Kolab objects filtered by the given query
     *
     * @param array Pseudo-SQL query as list of filter parameter triplets
     * @param boolean Set true to only return UIDs instead of complete objects
     * @return array List of Kolab data objects (each represented as hash array) or UIDs
     */
    public function select($query = array(), $uids = false)
    {
        // modify query for IMAP search: query param 'type' is actually a subtype
        if (!$this->ready) {
            foreach ($query as $i => $tuple) {
                if ($tuple[0] == 'type') {
                    $tuple[2] = 'configuration.' . $tuple[2];
                    $query[$i] = $tuple;
                }
            }
        }

        return parent::select($query, $uids);
    }

    /**
     * Helper method to compose a valid SQL query from pseudo filter triplets
     */
    protected function _sql_where($query)
    {
        if (is_array($query)) {
            foreach ($query as $idx => $param) {
                // convert category filter
                if ($param[0] == 'category') {
                    $param[2] = array_map(function($n) { return 'category:' . $n; }, (array) $param[2]);

                    $query[$idx][0] = 'tags';
                    $query[$idx][2] = count($param[2]) > 1 ? $param[2] : $param[2][0];
                }
                // convert member filter (we support only = operator with single value)
                else if ($param[0] == 'member') {
                    $query[$idx][0] = 'words';
                    $query[$idx][1] = '~';
                    $query[$idx][2] = '^' . $param[2] . '$';
                }
            }
        }

        return parent::_sql_where($query);
    }
}

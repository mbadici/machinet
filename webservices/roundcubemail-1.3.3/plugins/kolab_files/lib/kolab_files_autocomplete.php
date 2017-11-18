<?php

/**
 * Kolab files collaborators autocompletion
 *
 * @version @package_version@
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2013-2015, Kolab Systems AG <contact@kolabsys.com>
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

class kolab_files_autocomplete
{
    private $plugin;
    private $rc;


    /**
     * Class constructor
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->rc     = rcube::get_instance();

        $search = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC, true);
        $reqid  = rcube_utils::get_input_value('_reqid', rcube_utils::INPUT_GPC);
        $users  = array();
        $keys   = array();

        if ($this->init_ldap()) {
            $max  = (int) $this->rc->config->get('autocomplete_max', 15);
            $mode = (int) $this->rc->config->get('addressbook_search_mode');
            $me   = $this->rc->get_user_name();

            $this->ldap->set_pagesize($max);
            $result = $this->ldap->search('*', $search, $mode);

            foreach ($result->records as $record) {
                $user = $record['uid'];

                if (is_array($user)) {
                    $user = array_filter($user);
                    $user = $user[0];
                }

                if (in_array($me, rcube_addressbook::get_col_values('email', $record, true))) {
                    continue;
                }

                if ($user) {
                    $display = rcube_addressbook::compose_search_name($record);
                    $user    = array('name' => $user, 'display' => $display);
                    $users[] = $user;
                    $keys[]  = $display ?: $user['name'];
                }
            }
/*
            if ($this->rc->config->get('kolab_files_groups')) {
                $prefix      = $this->rc->config->get('kolab_files_group_prefix');
                $group_field = $this->rc->config->get('kolab_files_group_field', 'name');
                $result      = $this->ldap->list_groups($search, $mode);

                foreach ($result as $record) {
                    $group    = $record['name'];
                    $group_id = is_array($record[$group_field]) ? $record[$group_field][0] : $record[$group_field];

                    if ($group) {
                        $users[] = array('name' => ($prefix ? $prefix : '') . $group_id, 'display' => $group, 'type' => 'group');
                        $keys[]  = $group;
                    }
                }
            }
*/
        }

        if (count($users)) {
            // sort users index
            asort($keys, SORT_LOCALE_STRING);
            // re-sort users according to index
            foreach ($keys as $idx => $val) {
                $keys[$idx] = $users[$idx];
            }
            $users = array_values($keys);
        }

        $this->rc->output->command('ksearch_query_results', $users, $search, $reqid);
        $this->rc->output->send();
    }

    /**
     * Initializes autocomplete LDAP backend
     */
    private function init_ldap()
    {
        if ($this->ldap) {
            return $this->ldap->ready;
        }

        // get LDAP config
        $config = $this->rc->config->get('kolab_files_users_source');

        if (empty($config)) {
            return false;
        }

        // not an array, use configured ldap_public source
        if (!is_array($config)) {
            $ldap_config = (array) $this->rc->config->get('ldap_public');
            $config      = $ldap_config[$config];
        }

        $uid_field = $this->rc->config->get('kolab_files_users_field', 'mail');
        $filter    = $this->rc->config->get('kolab_files_users_filter');
        $debug     = $this->rc->config->get('ldap_debug');
        $domain    = $this->rc->config->mail_domain($_SESSION['imap_host']);

        if (empty($uid_field) || empty($config)) {
            return false;
        }

        // get name attribute
        if (!empty($config['fieldmap'])) {
            $name_field = $config['fieldmap']['name'];
        }
        // ... no fieldmap, use the old method
        if (empty($name_field)) {
            $name_field = $config['name_field'];
        }

        // add UID field to fieldmap, so it will be returned in a record with name
        $config['fieldmap']['name'] = $name_field;
        $config['fieldmap']['uid']  = $uid_field;

        // search in UID and name fields
        // $name_field can be in a form of <field>:<modifier> (#1490591)
        $name_field = preg_replace('/:.*$/', '', $name_field);
        $search     = array_unique(array($name_field, $uid_field));

        $config['search_fields']   = $search;
        $config['required_fields'] = array($uid_field);

        // set search filter
        if ($filter) {
            $config['filter'] = $filter;
        }

        // disable vlv
        $config['vlv'] = false;

        // Initialize LDAP connection
        $this->ldap = new rcube_ldap($config, $debug, $domain);

        return $this->ldap->ready;
    }
}

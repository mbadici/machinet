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
 * GAL (Global Address List) data backend for Syncroton
 */
class kolab_sync_data_gal extends kolab_sync_data implements Syncroton_Data_IDataSearch
{
    const MAX_SEARCH_RESULT = 100;

    /**
     * LDAP search result
     *
     * @var array
     */
    protected $result = array();

    /**
     * LDAP address books list
     *
     * @var array
     */
    protected $address_books = array();

    /**
     * Mapping from ActiveSync Contacts namespace fields
     */
    protected $mapping = array(
        'alias'         => 'nickname',
        'company'       => 'organization',
        'displayName'   => 'name',
        'emailAddress'  => 'email',
        'firstName'     => 'firstname',
        'lastName'      => 'surname',
        'mobilePhone'   => 'phone.mobile',
        'office'        => 'office',
        'picture'       => 'photo',
        'phone'         => 'phone',
        'title'         => 'jobtitle',
    );

    /**
     * Kolab object type
     *
     * @var string
     */
    protected $modelName = 'contact';

    /**
     * Type of the default folder
     *
     * @var int
     */
    protected $defaultFolderType = Syncroton_Command_FolderSync::FOLDERTYPE_CONTACT;

    /**
     * Default container for new entries
     *
     * @var string
     */
    protected $defaultFolder = 'Contacts';

    /**
     * Type of user created folders
     *
     * @var int
     */
    protected $folderType = Syncroton_Command_FolderSync::FOLDERTYPE_CONTACT_USER_CREATED;


    /**
     * the constructor
     *
     * @param Syncroton_Model_IDevice $device
     * @param DateTime                $syncTimeStamp
     */
    public function __construct(Syncroton_Model_IDevice $device, DateTime $syncTimeStamp)
    {
        parent::__construct($device, $syncTimeStamp);

        // Use configured fields mapping
        $rcube    = rcube::get_instance();
        $fieldmap = (array) $rcube->config->get('activesync_gal_fieldmap');
        if (!empty($fieldmap)) {
            $fieldmap = array_intersec_key($fieldmap, array_keys($this->mapping));
            $this->mapping = array_merge($this->mapping, $fieldmap);
        }
    }

    /**
     * Not used but required by parent class
     */
    public function toKolab(Syncroton_Model_IEntry $data, $folderId, $entry = null)
    {
    }

    /**
     * Not used but required by parent class
     */
    public function getEntry(Syncroton_Model_SyncCollection $collection, $serverId)
    {
    }

    /**
     * Returns properties of a contact for Search response
     *
     * @param array $data    Contact data
     * @param array $options Search options
     *
     * @return Syncroton_Model_GAL Contact (GAL) object
     */
    public function getSearchEntry($data, $options)
    {
        $result = array();

        // Contacts namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $this->getLDAPDataItem($data, $name);

            if (empty($value) || is_array($value)) {
                continue;
            }

            switch ($name) {
            case 'photo':
                // @TODO: MaxPictures option
                // ActiveSync limits photo size of GAL contact to 100KB
                $maxsize = 102400;
                if (!empty($options['picture']['maxSize'])) {
                    $maxsize = min($maxsize, $options['picture']['maxSize']);
                }

                if (strlen($value) > $maxsize) {
                    continue;
                }

                $value = new Syncroton_Model_GALPicture(array(
                    'data'   => $value, // binary
                    'status' => Syncroton_Model_GALPicture::STATUS_SUCCESS,
                ));

                break;
            }

            $result[$key] = $value;
        }

        return new Syncroton_Model_GAL($result);
    }


    /**
     * ActiveSync Search handler
     *
     * @param Syncroton_Model_StoreRequest $store Search query parameters
     *
     * @return Syncroton_Model_StoreResponse Complete Search response
     * @throws Exception
     */
    public function search(Syncroton_Model_StoreRequest $store)
    {
        $options  = $store->options;
        $query    = $store->query;

        if (empty($query) || !is_string($query)) {
            throw new Exception('Empty/invalid search request');
        }

        $records = array();
        $rcube   = rcube::get_instance();

        // @TODO: caching with Options->RebuildResults support

        $books  = $this->get_address_sources();
        $mode   = 2; // use prefix mode
        $fields = $rcube->config->get('contactlist_fields');

        if (empty($fields)) {
            $fields = '*';
        }

        foreach ($books as $idx => $book) {
            $book = $this->get_address_book($idx);

            if (!$book) {
                continue;
            }

            $book->set_page(1);
            $book->set_pagesize(self::MAX_SEARCH_RESULT);

            $result = $book->search($fields, $query, $mode, true, true, 'email');

            if (!$result->count) {
                continue;
            }

            // get records
            $result = $book->list_records();

            while ($row = $result->next()) {
                $row['sourceid'] = $idx;

                // make sure 'email' item is there, convert all email:* into one
                $row['email'] = $book->get_col_values('email', $row, true);

                $key = $this->contact_key($row);
                unset($row['_raw_attrib']); // save some memory, @TODO: do this in rcube_ldap
                $records[$key] = $row;
            }

            // We don't want to search all sources if we've got already a lot of contacts
            if (count($records) >= self::MAX_SEARCH_RESULT) {
                break;
            }
        }

        // sort the records
        ksort($records, SORT_LOCALE_STRING);

        $records  = array_values($records);
        $response = new Syncroton_Model_StoreResponse();

        // Calculate requested range
        $start = (int) $options['range'][0];
        $limit = (int) $options['range'][1] + 1;
        $total = count($records);
        $response->total = $total;

        // Get requested chunk of data set
        if ($total) {
            if ($start > $total) {
                $start = $total;
            }
            if ($limit > $total) {
                $limit = max($start+1, $total);
            }

            if ($start > 0 || $limit < $total) {
                $records = array_slice($records, $start, $limit-$start);
            }

            $response->range = array($start, $start + count($records) - 1);
        }

        // Build result array, convert to ActiveSync format
        foreach ($records as $idx => $rec) {
            $response->result[] = new Syncroton_Model_StoreResponseResult(array(
                'longId'     => $rec['ID'],
                'properties' => $this->getSearchEntry($rec, $options),
            ));
            unset($records[$idx]);
        }

        return $response;
    }

    /**
     * Return instance of the internal address book class
     *
     * @param string $id Address book identifier
     *
     * @return rcube_contacts Address book object
     */
    protected function get_address_book($id)
    {
        $contacts    = null;
        $config      = rcube::get_instance()->config;
        $ldap_config = (array) $config->get('ldap_public');

        // use existing instance
        if (isset($this->address_books[$id]) && ($this->address_books[$id] instanceof rcube_addressbook)) {
            $book = $this->address_books[$id];
        }
        else if ($id && $ldap_config[$id]) {
            $book = new rcube_ldap($ldap_config[$id], $config->get('ldap_debug'),
                $config->mail_domain($_SESSION['storage_host']));
        }

        if (!$book) {
            rcube::raise_error(array(
                'code' => 700, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Addressbook source ($id) not found!"),
                true, false);

            return null;
        }
/*
        // set configured sort order
        if ($sort_col = $this->config->get('addressbook_sort_col'))
            $contacts->set_sort_order($sort_col);
*/
        // add to the 'books' array for shutdown function
        $this->address_books[$id] = $book;

        return $book;
    }


    /**
     * Return LDAP address books list
     *
     * @return array  Address books array
     */
    protected function get_address_sources()
    {
        $config      = rcube::get_instance()->config;
        $ldap_config = (array) $config->get('ldap_public');
        $async_books = $config->get('activesync_addressbooks');

        if ($async_books === null) {
            $async_books = (array) $config->get('autocomplete_addressbooks');
        }

        $list = array();

        foreach ((array)$async_books as $id) {
            $prop = $ldap_config[$id];
            // handle misconfiguration
            if (empty($prop) || !is_array($prop)) {
                continue;
            }

            $list[$id] = array(
                'id'       => $id,
                'name'     => $prop['name'],
            );
/*
            // register source for shutdown function
            if (!is_object($this->address_books[$id]))
                $this->address_books[$id] = $list[$id];
            }
*/
        }

        return $list;
    }

    /**
     * Creates contact key for sorting by
     */
    protected function contact_key($row)
    {
        $key = $row['name'] . ':' . $row['sourceid'];

        // add email to a key to not skip contacts with the same name
        if (!empty($row['email'])) {
            if (is_array($row['email'])) {
                $key .= ':' . implode(':', $row['email']);
            }
            else {
                $key .= ':' . $row['email'];
            }
        }

        return $key;
    }

    /**
     * Extracts data from Roundcube LDAP data array
     */
    protected function getLDAPDataItem($data, $name)
    {
        list($name, $index) = explode(':', $name);
        $name = str_replace('.', ':', $name);

        if (isset($data[$name])) {
            if ($index) {
                return is_array($data[$name]) ? $data[$name][$index] : null;
            }

            return is_array($data[$name]) ? array_shift($data[$name]) : $data[$name];
        }

        return null;
    }
}

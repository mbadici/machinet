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
 * COntacts data class for Syncroton
 */
class kolab_sync_data_contacts extends kolab_sync_data
{
    /**
     * Mapping from ActiveSync Contacts namespace fields
     */
    protected $mapping = array(
        'anniversary'           => 'anniversary',
        'assistantName'         => 'assistant:0',
        //'assistantPhoneNumber' => 'assistantphonenumber',
        'birthday'              => 'birthday',
        'body'                  => 'notes',
        'businessAddressCity'          => 'address.work.locality',
        'businessAddressCountry'       => 'address.work.country',
        'businessAddressPostalCode'    => 'address.work.code',
        'businessAddressState'         => 'address.work.region',
        'businessAddressStreet'        => 'address.work.street',
        'businessFaxNumber'     => 'phone.workfax.number',
        'businessPhoneNumber'   => 'phone.work.number',
        'carPhoneNumber'        => 'phone.car.number',
        //'categories'            => 'categories',
        'children'              => 'children',
        'companyName'           => 'organization',
        'department'            => 'department',
        //'email1Address'         => 'email:0',
        //'email2Address'         => 'email:1',
        //'email3Address'         => 'email:2',
        //'fileAs'                => 'fileas', //@TODO: ?
        'firstName'             => 'firstname',
        //'home2PhoneNumber'      => 'home2phonenumber',
        'homeAddressCity'       => 'address.home.locality',
        'homeAddressCountry'    => 'address.home.country',
        'homeAddressPostalCode' => 'address.home.code',
        'homeAddressState'      => 'address.home.region',
        'homeAddressStreet'     => 'address.home.street',
        'homeFaxNumber'         => 'phone.homefax.number',
        'homePhoneNumber'       => 'phone.home.number',
        'jobTitle'              => 'jobtitle',
        'lastName'              => 'surname',
        'middleName'            => 'middlename',
        'mobilePhoneNumber'     => 'phone.mobile.number',
        //'officeLocation'        => 'officelocation',
        'otherAddressCity'      => 'address.office.locality',
        'otherAddressCountry'   => 'address.office.country',
        'otherAddressPostalCode' => 'address.office.code',
        'otherAddressState'     => 'address.office.region',
        'otherAddressStreet'    => 'address.office.street',
        'pagerNumber'           => 'phone.pager.number',
        'picture'               => 'photo',
        //'radioPhoneNumber'      => 'radiophonenumber',
        //'rtf'                   => 'rtf',
        'spouse'                => 'spouse',
        'suffix'                => 'suffix',
        'title'                 => 'prefix',
        'webPage'               => 'website.homepage.url',
        //'yomiCompanyName'       => 'yomicompanyname',
        //'yomiFirstName'         => 'yomifirstname',
        //'yomiLastName'          => 'yomilastname',
        // Mapping from ActiveSync Contacts2 namespace fields
        //'accountName'           => 'accountname',
        //'companyMainPhone'      => 'companymainphone',
        //'customerId'            => 'customerid',
        //'governmentId'          => 'governmentid',
        'iMAddress'             => 'im:0',
        'iMAddress2'            => 'im:1',
        'iMAddress3'            => 'im:2',
        'managerName'           => 'manager:0',
        //'mMS'                   => 'mms',
        'nickName'              => 'nickname',
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
     * Creates model object
     *
     * @param Syncroton_Model_SyncCollection $collection Collection data
     * @param string                         $serverId   Local entry identifier
     */
    public function getEntry(Syncroton_Model_SyncCollection $collection, $serverId)
    {
        $data   = is_array($serverId) ? $serverId : $this->getObject($collection->collectionId, $serverId);
        $result = array();

        // Contacts namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $this->getKolabDataItem($data, $name);

            switch ($name) {
            case 'photo':
                if ($value) {
                    // ActiveSync limits photo size to 48KB (of base64 encoded string)
                    if (strlen($value) * 1.33 > 48 * 1024) {
                        continue;
                    }
                }
                break;

            case 'birthday':
            case 'anniversary':
                $value = self::date_from_kolab($value);
                break;

            case 'notes':
                $value = $this->setBody($value);
                break;
            }

            if (empty($value) || is_array($value)) {
                continue;
            }

            $result[$key] = $value;
        }

        // email address(es): email1Address, email2Address, email3Address
        for ($x=0; $x<3; $x++) {
            if (!empty($data['email'][$x]) && !empty($data['email'][$x]['address'])) {
                $result['email' . ($x+1) . 'Address'] = $data['email'][$x]['address'];
            }
        }

        return new Syncroton_Model_Contact($result);
    }

    /**
     * convert contact from xml to libkolab array
     *
     * @param Syncroton_Model_IEntry $data     Contact to convert
     * @param string                 $folderId Folder identifier
     * @param array                  $entry    Existing entry
     *
     * @return array Kolab object array
     */
    public function toKolab(Syncroton_Model_IEntry $data, $folderId, $entry = null)
    {
        $contact = !empty($entry) ? $entry : array();

        // Contacts namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $data->$key;

            switch ($name) {
            case 'address.work.street':
                if (strtolower($this->device->devicetype) == 'palm') {
                    // palm pre sends the whole address in the <Contacts:BusinessStreet> tag
                    $value = null;
                }
                break;

            case 'website.homepage.url':
                // remove facebook urls
                if (preg_match('/^fb:\/\//', $value)) {
                    $value = null;
                }
                break;

            case 'notes':
                $value = $this->getBody($value, Syncroton_Model_EmailBody::TYPE_PLAINTEXT);
                // If note isn't specified keep old note
                if ($value === null) {
                    continue 2;
                }
                break;

            case 'photo':
                // If photo isn't specified keep old photo
                if ($value === null) {
                    continue 2;
                }
                break;

            case 'birthday':
            case 'anniversary':
                if ($value) {
                    // convert date to string format, so libkolab will store
                    // it with no time and timezone what could be incorrectly re-calculated (#2555)
                    $value = $value->format('Y-m-d');
                }
                break;
            }

            $this->setKolabDataItem($contact, $name, $value);
        }

        // email address(es): email1Address, email2Address, email3Address
        $emails = array();
        for ($x=0; $x<3; $x++) {
            $key = 'email' . ($x+1) . 'Address';
            if ($value = $data->$key) {
                // Android sends email address as: Lars Kneschke <l.kneschke@metaways.de>
                if (preg_match('/(.*)<(.+@[^@]+)>/', $value, $matches)) {
                    $value = trim($matches[2]);
                }

                // try to find address type, at least we can do this if
                // address wasn't changed
                $type = '';
                foreach ((array)$contact['email'] as $email) {
                    if ($email['address'] == $value) {
                        $type = $email['type'];
                    }
                }
                $emails[] = array('address' => $value, 'type' => $type);
            }
        }
        $contact['email'] = $emails;

        return $contact;
    }

    /**
     * Returns filter query array according to specified ActiveSync FilterType
     *
     * @param int $filter_type Filter type
     *
     * @param array Filter query
     */
    protected function filter($filter_type = 0)
    {
        // specify object type, contact folders in Kolab might
        // contain also ditribution-list objects, we'll skip them
        return array(array('type', '=', $this->modelName));
    }

}

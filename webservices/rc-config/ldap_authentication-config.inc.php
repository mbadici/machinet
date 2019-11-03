<?php

// The id of the LDAP address book (which refers to the $rcmail_config['ldap_public'])
// or complete addressbook definition array.
$rcmail_config['ldap_authentication_addressbook'] = 'GAL';

// This will overwrite defined filter
$rcmail_config['ldap_authentication_filter'] = '(&(objectClass=InetOrgPerson)(|(uid=%fu)(mail=%fu)(alias=%fu)))';

// Use this fields (from fieldmap configuration) to get authentication ID
$rcmail_config['ldap_authentication_login'] = 'email';

// Use this fields (from fieldmap configuration) for default identity
$rcmail_config['ldap_authentication_name']  = 'name';
$rcmail_config['ldap_authentication_email'] = 'email';

?>

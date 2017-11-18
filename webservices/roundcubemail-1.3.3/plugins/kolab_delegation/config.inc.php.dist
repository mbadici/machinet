<?php

// This will overwrite defined LDAP filter
// Note: LDAP addressbook defined for kolab_auth plugin is used
$config['kolab_delegation_filter'] = '(|(objectClass=kolabInetOrgPerson)(&(objectclass=kolabsharedfolder)(kolabFolderType=mail)))';

// Delegates field (from fieldmap configuration) to get delegates list
// Note: This is a field name, not LDAP attribute name
// Note: LDAP addressbook defined for kolab_auth plugin is used
$config['kolab_delegation_delegate_field'] = 'kolabDelegate';

// Remove all user identities which do not match the user's primary or alias
// addresses and delegator's addresses
$config['kolab_delegation_purge_identities'] = false;

?>

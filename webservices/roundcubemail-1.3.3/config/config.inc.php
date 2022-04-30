<?php

/*
 +-----------------------------------------------------------------------+
 | Local configuration for the Roundcube Webmail installation.           |
 |                                                                       |
 | This is a sample configuration file only containing the minumum       |
 | setup required for a functional installation. Copy more options       |
 | from defaults.inc.php to this file to override the defaults.          |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+
*/

$config = array();

// Database connection string (DSN) for read+write operations
// Format (compatible with PEAR MDB2): db_provider://user:password@host/database
// Currently supported db_providers: mysql, pgsql, sqlite, mssql or sqlsrv
// For examples see http://pear.php.net/manual/en/package.database.mdb2.intro-dsn.php
// NOTE: for SQLite use absolute path: 'sqlite:////full/path/to/sqlite.db?mode=0646'
$config['db_dsnw'] = 'mysql://roundcube:m@chiNET@localhost/roundcubemail';

// The mail host chosen to perform the log-in.
// Leave blank to show a textbox at login, give a list of hosts
// to display a pulldown menu or set one host as string.
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// Supported replacement variables:
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %s - domain name after the '@' from e-mail address provided at login screen
// For example %n = mail.domain.tld, %t = domain.tld
// system error reporting, sum of: 1 = log; 4 = show, 8 = trace
$config['debug_level'] = 8;

// log driver:  'syslog' or 'file'.
$config['log_driver'] = 'file';
$config['mail_host']='%d';

//use this for default domain:
//$config['mail_domain'] = 'machinet.ro';
// date format for log entries
// (read http://php.net/manual/en/function.date.php for all format characters)  
$config['log_date_format'] = 'd-M-Y H:i:s O';

// Syslog ident string to use, if using the 'syslog' log driver.
$config['syslog_id'] = 'roundcube';
$config['log_dir'] = 'logs/';
// Syslog facility to use, if using the 'syslog' log driver.
// For possible values see installer or http://php.net/manual/en/function.openlog.php
$config['syslog_facility'] = LOG_USER;

// Log sent messages to <log_dir>/sendmail or to syslog
$config['smtp_log'] = true;

// Log successful/failed logins to <log_dir>/userlogins or to syslog
$config['log_logins'] = true;
$config['log_errors'] = true;

// Log session authentication errors to <log_dir>/session or to syslog
$config['log_session'] = true;

// Log SQL queries to <log_dir>/sql or to syslog
$config['sql_debug'] = true;

// Log IMAP conversation to <log_dir>/imap or to syslog
$config['imap_debug'] = true;

// Log LDAP conversation to <log_dir>/ldap or to syslog
$config['password_log'] = false;
$config['ldap_log'] = true;
$config['ldap_debug'] = false;

// Log SMTP conversation to <log_dir>/smtp or to syslog
$config['smtp_debug'] = false;

$config['default_host'] = 'localhost';

// SMTP server host (for sending mails).
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// If left blank, the PHP mail() function is used
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['smtp_server'] = 'localhost';

// SMTP port (default is 25; use 587 for STARTTLS or 465 for the
// deprecated SSL over SMTP (aka SMTPS))
$config['smtp_port'] = 25;

// SMTP username (if required) if you use %u as the username Roundcube
// will use the current username for login
$config['smtp_user'] = '';

// SMTP password (if required) if you use %p as the password Roundcube
// will use the current user's password for login
$config['smtp_pass'] = '';

// provide an URL where a user can get support for this Roundcube installation
// PLEASE DO NOT LINK TO THE ROUNDCUBE.NET WEBSITE HERE!
$config['support_url'] = '';
//$config['force_https'] = 'true';

// Name your service. This is displayed on the login screen and in the window title
$config['product_name'] = 'MachiNet Webmail';

// this key is used to encrypt the users imap password which is stored
// in the session record (and the client cookie if remember password is enabled).
// please provide a string of exactly 24 chars.
// YOUR KEY MUST BE DIFFERENT THAN THE SAMPLE VALUE FOR SECURITY REASONS
$config['des_key'] = 'rcmail-!24ByteDESkey*Str';

// List of active plugins (in plugins/ directory)
$config['plugins'] = array(
    'archive',
    'zipdownload',
    'acl' ,
  'calendar' , 'kolab_auth',   'kolab_activesync' ,'kolab_addressbook' , 'kolab_config'  , 'kolab_notes' , 'ldap_authentication' , 'kolab_folders' , 'kolab_files' ,  'libcalendaring' , 
      'libkolab'  , 'tasklist' ,  'jqueryui'  , 'password' , 'managesieve' , 'markasjunk' , 'pdfviewer' , 'odfviewer'  
);

// skin name: folder from skins/
$config['skin'] = 'larry';

// path to imagemagick convert binary
$rcmail_config['im_convert_path'] = null;

// Size of thumbnails from image attachments displayed below the message content.
// Note: whether images are displayed at all depends on the 'inline_images' option.
// Set to 0 to display images in full size.
$rcmail_config['image_thumbnail_size'] = 240;

// maximum size of uploaded contact photos in pixel
$rcmail_config['contact_photo_size'] = 160;

// Enable DNS checking for e-mail address validation
$rcmail_config['email_dns_check'] = false;

// Disables saving sent messages in Sent folder (like gmail) (Default: false)
// Note: useful when SMTP server stores sent mail in user mailbox
$rcmail_config['no_save_sent_messages'] = false;

// ----------------------------------
// PLUGINS
// ----------------------------------

// List of active plugins (in plugins/ directory)

// ----------------------------------
// USER INTERFACE
// ----------------------------------

// default messages sort column. Use empty value for default server's sorting, 
// or 'arrival', 'date', 'subject', 'from', 'to', 'fromto', 'size', 'cc'
$rcmail_config['message_sort_col'] = '';

// default messages sort order
$rcmail_config['message_sort_order'] = 'DESC';

// These cols are shown in the message list. Available cols are:
// subject, from, to, fromto, cc, replyto, date, size, status, flag, attachment, 'priority'
$rcmail_config['list_cols'] = array('subject', 'status', 'fromto', 'date', 'size', 'flag', 'attachment');

// the default locale setting (leave empty for auto-detection)
// RFC1766 formatted language name like en_US, de_DE, de_CH, fr_FR, pt_BR
$rcmail_config['language'] = null;

// use this format for date display (date or strftime format)
$rcmail_config['date_format'] = 'Y-m-d';

// give this choice of date formats to the user to select from
$rcmail_config['date_formats'] = array('Y-m-d', 'd-m-Y', 'Y/m/d', 'm/d/Y', 'd/m/Y', 'd.m.Y', 'j.n.Y');

// use this format for time display (date or strftime format)
$rcmail_config['time_format'] = 'H:i';

// give this choice of time formats to the user to select from
$rcmail_config['time_formats'] = array('G:i', 'H:i', 'g:i a', 'h:i A');

// use this format for short date display (derived from date_format and time_format)
$rcmail_config['date_short'] = 'D H:i';

// use this format for detailed date/time formatting (derived from date_format and time_format)
$rcmail_config['date_long'] = 'Y-m-d H:i';

// store draft message is this mailbox
// leave blank if draft messages should not be stored
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$rcmail_config['drafts_mbox'] = 'Drafts';

// store spam messages in this mailbox
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$rcmail_config['junk_mbox'] = 'Junk';

// store sent message is this mailbox
// leave blank if sent messages should not be stored
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$rcmail_config['sent_mbox'] = 'Sent';

// move messages to this folder when deleting them
// leave blank if they should be deleted directly
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$rcmail_config['trash_mbox'] = 'Trash';

// display these folders separately in the mailbox list.
// these folders will also be displayed with localized names
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$rcmail_config['default_folders'] = array('INBOX', 'Drafts', 'Sent Items', 'Junk', 'Trash');

// automatically create the above listed default folders on first login
$rcmail_config['create_default_folders'] = true;

// protect the default folders from renames, deletes, and subscription changes
$rcmail_config['protect_default_folders'] = true;

// if in your system 0 quota means no limit set this option to true 
$rcmail_config['quota_zero_as_unlimited'] = false;

// Make use of the built-in spell checker. It is based on GoogieSpell.
// Since Google only accepts connections over https your PHP installatation
// requires to be compiled with Open SSL support
$rcmail_config['enable_spellcheck'] = true;

// Enables spellchecker exceptions dictionary.
// Setting it to 'shared' will make the dictionary shared by all users.
$rcmail_config['spellcheck_dictionary'] = false;

// Set the spell checking engine. 'googie' is the default. 'pspell' is also available,
// but requires the Pspell extensions. When using Nox Spell Server, also set 'googie' here.
$rcmail_config['spellcheck_engine'] = 'googie';

// For a locally installed Nox Spell Server, please specify the URI to call it.
// Get Nox Spell Server from http://orangoo.com/labs/?page_id=72
// Leave empty to use the Google spell checking service, what means
// that the message content will be sent to Google in order to check spelling
$rcmail_config['spellcheck_uri'] = '';

// These languages can be selected for spell checking.
// Configure as a PHP style hash array: array('en'=>'English', 'de'=>'Deutsch');
// Leave empty for default set of available language.
$rcmail_config['spellcheck_languages'] = NULL;

// Makes that words with all letters capitalized will be ignored (e.g. GOOGLE)
$rcmail_config['spellcheck_ignore_caps'] = false;

// Makes that words with numbers will be ignored (e.g. g00gle)
$rcmail_config['spellcheck_ignore_nums'] = false;

// Makes that words with symbols will be ignored (e.g. g@@gle)
$rcmail_config['spellcheck_ignore_syms'] = false;

// Use this char/string to separate recipients when composing a new message
$rcmail_config['recipients_separator'] = ',';

// don't let users set pagesize to more than this value if set
$rcmail_config['max_pagesize'] = 200;

// Minimal value of user's 'refresh_interval' setting (in seconds)
$rcmail_config['min_refresh_interval'] = 60;

// Enables files upload indicator. Requires APC installed and enabled apc.rfc1867 option.
// By default refresh time is set to 1 second. You can set this value to true
// or any integer value indicating number of seconds.
$rcmail_config['upload_progress'] = false;

// Specifies for how many seconds the Undo button will be available
// after object delete action. Currently used with supporting address book sources.
// Setting it to 0, disables the feature.
$rcmail_config['undo_timeout'] = 0;

// ----------------------------------
// ADDRESSBOOK SETTINGS
// ----------------------------------

// This indicates which type of address book to use. Possible choises:
// 'sql' (default), 'ldap' and ''.
// If set to 'ldap' then it will look at using the first writable LDAP
// address book as the primary address book and it will not display the
// SQL address book in the 'Address Book' view.
// If set to '' then no address book will be displayed or only the
// addressbook which is created by a plugin (like CardDAV).
$rcmail_config['address_book_type'] = 'ldap';

// In order to enable public ldap search, configure an array like the Verisign
// example further below. if you would like to test, simply uncomment the example.
// Array key must contain only safe characters, ie. a-zA-Z0-9_
$rcmail_config['ldap_public'] = array('GAL');

// If you are going to use LDAP for individual address books, you will need to 
// set 'user_specific' to true and use the variables to generate the appropriate DNs to access it.
//
// The recommended directory structure for LDAP is to store all the address book entries
// under the users main entry, e.g.:
//
//  o=root
//   ou=people
//    uid=user@domain
//  mail=contact@contactdomain
//
// So the base_dn would be uid=%fu,ou=people,o=root
// The bind_dn would be the same as based_dn or some super user login.
/* 
 * example config for Verisign directory
*/
$rcmail_config['ldap_public']['GAL'] = array(
  'name'          => 'GAL',
  // Replacement variables supported in host names:
  // %h - user's IMAP hostname
  // %n - hostname ($_SERVER['SERVER_NAME'])
  // %t - hostname without the first part
  // %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
  // %z - IMAP domain (IMAP hostname without the first part)
  // For example %n = mail.domain.tld, %t = domain.tld
  'hosts'         => array('localhost'),
  'port'          => 389,
  'use_tls'	      => false,
  'ldap_version'  => 3,       // using LDAPv3
  'user_specific' => false,   // If true the base_dn, bind_dn and bind_pass default to the user's IMAP login.
  // %fu - The full username provided, assumes the username is an email
  //       address, uses the username_domain value if not an email address.
  // %u  - The username prior to the '@'.
  // %d  - The domain name after the '@'.
  // %dc - The domain name hierarchal string e.g. "dc=test,dc=domain,dc=com"
  // %dn - DN found by ldap search when search_filter/search_base_dn are used
//  'base_dn'       => 'ou=%d,ou=users,dc=machinet',
  'base_dn'       => 'dc=machinet',
  'bind_dn'       => '',
  'bind_pass'     => '',
  // It's possible to bind for an individual address book
  // The login name is used to search for the DN to bind with
//  'search_base_dn' => 'ou=%d,ou=users,dc=machinet',
  'search_base_dn' => 'dc=machinet',
//
//  'search_filter' = '(&(objectClass=inetOrgPerson)(mail=%fu))',
  'search_filter'  => '(&(mail=%u))',   // e.g. '(&(objectClass=posixAccount)(uid=%u))'
  // DN and password to bind as before searching for bind DN, if anonymous search is not allowed
  'search_bind_dn' => '',
  'search_bind_pw' => '',
  // Default for %dn variable if search doesn't return DN value
  'search_dn_default' => '',
  // Optional authentication identifier to be used as SASL authorization proxy
  // bind_dn need to be empty
  'auth_cid'       => '',
  // SASL authentication method (for proxy auth), e.g. DIGEST-MD5
  'auth_method'    => '',
  // Indicates if the addressbook shall be hidden from the list.
  // With this option enabled you can still search/view contacts.
  'hidden'        => false,
  // Indicates if the addressbook shall not list contacts but only allows searching.
  'searchonly'    => false,
  // Indicates if we can write to the LDAP directory or not.
  // If writable is true then these fields need to be populated:
  // LDAP_Object_Classes, required_fields, LDAP_rdn
  'writable'       => false,
  // To create a new contact these are the object classes to specify
  // (or any other classes you wish to use).
  'LDAP_Object_Classes' => array('top', 'inetOrgPerson'),
  // The RDN field that is used for new entries, this field needs
  // to be one of the search_fields, the base of base_dn is appended
  // to the RDN to insert into the LDAP directory.
  'LDAP_rdn'       => 'cn',
  // The required fields needed to build a new contact as required by
  // the object classes (can include additional fields not required by the object classes).
  'required_fields' => array('cn', 'sn', 'mail'),
  'search_fields'   => array('mail', 'cn'),  // fields to search in
  // mapping of contact fields to directory attributes
  //   for every attribute one can specify the number of values (limit) allowed.
  //   default is 1, a wildcard * means unlimited
  'fieldmap' => array(
    // Roundcube  => LDAP:limit
    'name'        => 'cn',
    'surname'     => 'sn',
    'firstname'   => 'givenName',
    'jobtitle'    => 'title',
    'email'       => 'mail:*',
    'phone:home'  => 'homePhone',
    'phone:work'  => 'telephoneNumber',
    'phone:mobile' => 'mobile',
    'phone:pager' => 'pager',
    'street'      => 'street',
    'zipcode'     => 'postalCode',
    'region'      => 'st',
    'locality'    => 'l',
    // if you country is a complex object, you need to configure 'sub_fields' below
    'country'      => 'c',
    'organization' => 'o',
    'department'   => 'ou',
    'jobtitle'     => 'title',
    'notes'        => 'description',
    // these currently don't work:
    // 'phone:workfax' => 'facsimileTelephoneNumber',
    // 'photo'         => 'jpegPhoto',
    // 'manager'       => 'manager',
    // 'assistant'     => 'secretary',
  ),
  // Map of contact sub-objects (attribute name => objectClass(es)), e.g. 'c' => 'country'
  'sub_fields' => array(),
  // Generate values for the following LDAP attributes automatically when creating a new record
  'autovalues' => array(
  // 'uid'  => 'md5(microtime())',               // You may specify PHP code snippets which are then eval'ed 
  // 'mail' => '{givenname}.{sn}@mydomain.com',  // or composite strings with placeholders for existing attributes
  ),
  'sort'          => 'cn',    // The field to sort the listing by.
  'scope'         => 'sub',   // search mode: sub|base|list
  'filter'        => '(&(objectClass=inetOrgPerson))',      // used for basic listing (if not empty) and will be &'d with search queries. example: status=act
  'fuzzy_search'  => true,    // server allows wildcard search
  'vlv'           => true,   // Enable Virtual List View to more efficiently fetch paginated data (if server supports it)
  'numsub_filter' => '(objectClass=organizationalUnit)',   // with VLV, we also use numSubOrdinates to query the total number of records. Set this filter to get all numSubOrdinates attributes for counting
  'sizelimit'     => '0',     // Enables you to limit the count of entries fetched. Setting this to 0 means no limit.
  'timelimit'     => '0',     // Sets the number of seconds how long is spend on the search. Setting this to 0 means no limit.
  'referrals'     => true|false,  // Sets the LDAP_OPT_REFERRALS option. Mostly used in multi-domain Active Directory setups

  // definition for contact groups (uncomment if no groups are supported)
  // for the groups base_dn, the user replacements %fu, %u, $d and %dc work as for base_dn (see above)
  // if the groups base_dn is empty, the contact base_dn is used for the groups as well
  // -> in this case, assure that groups and contacts are separated due to the concernig filters! 
  'groups'        => array(
    'base_dn'     => '',
    'scope'       => 'sub',   // search mode: sub|base|list
    'filter'      => '(&(objectClass=groupOfNames)(mail = *%d))',
    'object_classes' => array("top", "groupOfNames"),
    'member_attr'  => 'member',   // name of the member attribute, e.g. uniqueMember
    'name_attr'    => 'cn',       // attribute to be used as group name
  ),
);


// An ordered array of the ids of the addressbooks that should be searched
// when populating address autocomplete fields server-side. ex: array('sql','Verisign');
$rcmail_config['autocomplete_addressbooks'] = array('GAL');

// The minimum number of characters required to be typed in an autocomplete field
// before address books will be searched. Most useful for LDAP directories that
// may need to do lengthy results building given overly-broad searches
$rcmail_config['autocomplete_min_length'] = 3;

// Number of parallel autocomplete requests.
// If there's more than one address book, n parallel (async) requests will be created,
// where each request will search in one address book. By default (0), all address
// books are searched in one request.
$rcmail_config['autocomplete_threads'] = 0;

// Max. numer of entries in autocomplete popup. Default: 15.
$rcmail_config['autocomplete_max'] = 15;

// show address fields in this order


<?php

/**
 * Localizations for the Kolab Address Book plugin
 *
 * Copyright (C) 2014, Kolab Systems AG
 *
 * For translation see https://www.transifex.com/projects/p/kolab/resource/kolab_addressbook/
 */

$labels = array();
$labels['initials'] = 'Initials';
$labels['profession'] = 'Profession';
$labels['officelocation'] = 'Office location';
$labels['children'] = 'Children';
$labels['pgppublickey'] = 'PGP public key';
$labels['pkcs7publickey'] = 'S/MIME public key';
$labels['freebusyurl'] = 'Free-busy URL';
$labels['typebusiness'] = 'Business';
$labels['typebusinessfax'] = 'Business Fax';
$labels['typecompany'] = 'Company';
$labels['typeprimary'] = 'Primary';
$labels['typetelex'] = 'Telex';
$labels['typeradio'] = 'Radio';
$labels['typeisdn'] = 'ISDN';
$labels['typettytdd'] = 'TTY/TDD';
$labels['typecallback'] = 'Callback';
$labels['settings'] = 'Settings';

$labels['bookcreate'] = 'Create address book';
$labels['bookedit'] = 'Edit address book';
$labels['bookdelete'] = 'Delete address book';
$labels['bookremove'] = 'Remove from list';
$labels['bookproperties'] = 'Address book properties';
$labels['bookname'] = 'Book name';
$labels['parentbook'] = 'Superior book';
$labels['bookshowurl'] = 'Show CardDAV URL';
$labels['carddavurldescription'] = 'Copy this address to a <a href="http://en.wikipedia.org/wiki/CardDAV" target="_blank">CardDAV</a> client application to fully synchronize this specific address book with your computer or mobile device.';

$labels['addressbookprio'] = 'Address book(s) selection/behaviour';
$labels['personalfirst'] = 'Personal address book(s) first';
$labels['globalfirst'] = 'Global address book(s) first';
$labels['personalonly'] = 'Personal address book(s) only';
$labels['globalonly'] = 'Global address book(s) only';

$labels['findaddressbooks'] = 'Find address books';
$labels['searchterms'] = 'Search terms';
$labels['listsearchresults'] = 'Additional address books';
$labels['foldersearchform'] = 'Address book search form';
$labels['foldersubscribe'] = 'List permanently';
$labels['nraddressbooksfound'] = '$nr address books found';
$labels['noaddressbooksfound'] = 'No address books found';

// history dialog
$labels['showhistory'] = 'Show History';
$labels['objectchangelog'] = 'Change History';
$labels['objectdiff'] = 'Changes from $rev1 to $rev2';
$labels['revisionrestoreconfirm'] = 'Do you really want to restore revision $rev of this contact? This will replace the current contact with the old version.';
$labels['objectnotfound'] = 'Failed to load contact data';
$labels['objectchangelognotavailable'] = 'Change history is not available for this contact';
$labels['objectdiffnotavailable'] = 'No comparison possible for the selected revisions';
$labels['objectrestoresuccess'] = 'Revision $rev successfully restored';
$labels['objectrestoreerror'] = 'Failed to restore the old revision';

$messages['bookdeleteconfirm']  = 'Do you really want to delete the selected address book and all contacts in it?';
$messages['bookdeleting'] = 'Deleting address book...';
$messages['booksaving'] = 'Saving address book...';
$messages['bookdeleted'] = 'Address book deleted successfully.';
$messages['bookupdated'] = 'Address book updated successfully.';
$messages['bookcreated'] = 'Address book created successfully.';
$messages['bookdeleteerror'] = 'An error occured while deleting address book.';
$messages['bookupdateerror'] = 'An error occured while updating address book.';
$messages['bookcreateerror'] = 'An error occured while creating address book.';
$messages['nobooknamewarning'] = 'Please, enter address book name.';
$messages['noemailnamewarning'] = 'Please, enter email address or contact name.';

?>

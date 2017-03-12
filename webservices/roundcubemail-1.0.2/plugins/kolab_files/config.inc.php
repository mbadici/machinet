<?php

// URL of kolab-chwala installation
$rcmail_config['kolab_files_url'] = 'http://mail.4data.ro/chwala';
//$rcmail_config['kolab_files_url'] = 'chwala';

// List of files list columns. Available are: name, size, mtime, type
$rcmail_config['kolab_files_list_cols'] = array('name', 'mtime', 'size');

// Name of the column to sort files list by
$rcmail_config['kolab_files_sort_col'] = 'name';

// Order of the files list sort
$rcmail_config['kolab_files_sort_order'] = 'asc';

// Number of concurent requests for searching and collections listing. Default: 1
$rcmail_config['kolab_files_search_threads'] = 1;

?>

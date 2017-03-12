CREATE TABLE IF NOT EXISTS `syncroton_policy` (
    `id` varchar(40) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `policy_key` varchar(64) NOT NULL,
    `json_policy` blob NOT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `syncroton_device` (
    `id` varchar(40) NOT NULL,
    `deviceid` varchar(64) NOT NULL,
    `devicetype` varchar(64) NOT NULL,
    `owner_id` varchar(40) NOT NULL,
    `acsversion` varchar(40) NOT NULL,
    `policykey` varchar(64) DEFAULT NULL,
    `policy_id` varchar(40) DEFAULT NULL,
    `useragent` varchar(255) DEFAULT NULL,
    `imei` varchar(255) DEFAULT NULL,
    `model` varchar(255) DEFAULT NULL,
    `friendlyname` varchar(255) DEFAULT NULL,
    `os` varchar(255) DEFAULT NULL,
    `oslanguage` varchar(255) DEFAULT NULL,
    `phonenumber` varchar(255) DEFAULT NULL,
    `pinglifetime` int(11) DEFAULT NULL,
    `remotewipe` int(11) DEFAULT '0',
    `pingfolder` longblob,
    `lastsynccollection` longblob DEFAULT NULL,
    `contactsfilter_id` varchar(40) DEFAULT NULL,
    `calendarfilter_id` varchar(40) DEFAULT NULL,
    `tasksfilter_id` varchar(40) DEFAULT NULL,
    `emailfilter_id` varchar(40) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `owner_id--deviceid` (`owner_id`, `deviceid`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_folder` (
    `id` varchar(40) NOT NULL,
    `device_id` varchar(40) NOT NULL,
    `class` varchar(64) NOT NULL,
    `folderid` varchar(254) NOT NULL,
    `parentid` varchar(254) DEFAULT NULL,
    `displayname` varchar(254) NOT NULL,
    `type` int(11) NOT NULL,
    `creation_time` datetime NOT NULL,
    `lastfiltertype` int(11) DEFAULT NULL,
    `supportedfields` longblob DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `device_id--class--folderid` (`device_id`(40),`class`(40),`folderid`(40)),
    KEY `folderstates::device_id--devices::id` (`device_id`),
    CONSTRAINT `folderstates::device_id--devices::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE 
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_synckey` (
    `id` varchar(40) NOT NULL,
    `device_id` varchar(40) NOT NULL DEFAULT '',
    `type` varchar(64) NOT NULL DEFAULT '',
    `counter` int(11) NOT NULL DEFAULT '0',
    `lastsync` datetime DEFAULT NULL,
    `pendingdata` longblob,
    PRIMARY KEY (`id`),
    UNIQUE KEY `device_id--type--counter` (`device_id`,`type`,`counter`),
    CONSTRAINT `syncroton_synckey::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_content` (
    `id` varchar(40) NOT NULL,
    `device_id` varchar(40) NOT NULL,
    `folder_id` varchar(40) NOT NULL,
    `contentid` varchar(128) NOT NULL,
    `creation_time` datetime DEFAULT NULL,
    `creation_synckey` int(11) NOT NULL,
    `is_deleted` tinyint(1) DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `device_id--folder_id--contentid` (`device_id`(40),`folder_id`(40),`contentid`(128)),
    KEY `syncroton_contents::device_id` (`device_id`),
    CONSTRAINT `syncroton_contents::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_data` (
    `id` varchar(40) NOT NULL,
    `class` varchar(40) NOT NULL,
    `folder_id` varchar(40) NOT NULL,
    `data` longblob,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_data_folder` (
    `id` varchar(40) NOT NULL,
    `type` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `owner_id` varchar(40) NOT NULL,
    `parent_id` varchar(40) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `syncroton_modseq` (
    `device_id` varchar(40) NOT NULL,
    `folder_id` varchar(40) NOT NULL,
    `synctime` varchar(14) NOT NULL,
    `data` longblob,
    PRIMARY KEY (`device_id`,`folder_id`,`synctime`),
    KEY `syncroton_modseq::device_id` (`device_id`),
    CONSTRAINT `syncroton_modseq::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Roundcube core table should exist if we're using the same database

CREATE TABLE IF NOT EXISTS `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

INSERT INTO `system` (`name`, `value`) VALUES ('syncroton-version', '2013040900');

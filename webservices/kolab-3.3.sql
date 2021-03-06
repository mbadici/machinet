-- MySQL dump 10.13  Distrib 5.5.38, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: roundcube
-- ------------------------------------------------------
-- Server version	5.5.38-0+wheezy1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cache`
--
Create DATABASE roundcubemail;
Grant  all on roundcubemail.* to 'roundcube'@'localhost' identified by 'm@chiNET';
use roundcubemail;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `user_id` int(10) unsigned NOT NULL,
  `cache_key` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `data` longtext NOT NULL,
  `expires` text NOT NULL,
  KEY `created_index` (`created`),
  KEY `user_cache_index` (`user_id`,`cache_key`),
  CONSTRAINT `user_id_fk_cache` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_index`
--

DROP TABLE IF EXISTS `cache_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_index` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `valid` tinyint(1) NOT NULL DEFAULT '0',
  `data` longtext NOT NULL,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`mailbox`),
  KEY `changed_index` (`changed`),
  CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_messages`
--

DROP TABLE IF EXISTS `cache_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_messages` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `data` longtext NOT NULL,
  `flags` int(11) NOT NULL DEFAULT '0',
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`mailbox`,`uid`),
  KEY `changed_index` (`changed`),
  CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_shared`
--

DROP TABLE IF EXISTS `cache_shared`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_shared` (
  `cache_key` varchar(255) CHARACTER SET ascii NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `expires` datetime DEFAULT NULL,
  `data` longtext NOT NULL,
  KEY `expires_index` (`expires`),
  KEY `cache_key_index` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_thread`
--

DROP TABLE IF EXISTS `cache_thread`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_thread` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `data` longtext NOT NULL,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`mailbox`),
  KEY `changed_index` (`changed`),
  CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chwala_locks`
--

DROP TABLE IF EXISTS `chwala_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chwala_locks` (
  `uri` varchar(512) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `owner` varchar(256) DEFAULT NULL,
  `timeout` int(10) unsigned DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `token` varchar(256) DEFAULT NULL,
  `scope` tinyint(4) DEFAULT NULL,
  `depth` tinyint(4) DEFAULT NULL,
  KEY `uri_index` (`uri`(255),`depth`),
  KEY `expires_index` (`expires`),
  KEY `token_index` (`token`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contactgroupmembers`
--

DROP TABLE IF EXISTS `contactgroupmembers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contactgroupmembers` (
  `contactgroup_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`,`contact_id`),
  KEY `contactgroupmembers_contact_index` (`contact_id`),
  CONSTRAINT `contactgroup_id_fk_contactgroups` FOREIGN KEY (`contactgroup_id`) REFERENCES `contactgroups` (`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contact_id_fk_contacts` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contactgroups`
--

DROP TABLE IF EXISTS `contactgroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contactgroups` (
  `contactgroup_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`contactgroup_id`),
  KEY `contactgroups_user_index` (`user_id`,`del`),
  CONSTRAINT `user_id_fk_contactgroups` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `contact_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  `email` text NOT NULL,
  `firstname` varchar(128) NOT NULL DEFAULT '',
  `surname` varchar(128) NOT NULL DEFAULT '',
  `vcard` longtext,
  `words` text,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`contact_id`),
  KEY `user_contacts_index` (`user_id`,`del`),
  CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dictionary`
--

DROP TABLE IF EXISTS `dictionary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dictionary` (
  `user_id` int(10) unsigned DEFAULT NULL,
  `language` varchar(5) NOT NULL,
  `data` longtext NOT NULL,
  UNIQUE KEY `uniqueness` (`user_id`,`language`),
  CONSTRAINT `user_id_fk_dictionary` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_types`
--

DROP TABLE IF EXISTS `group_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `identities`
--

DROP TABLE IF EXISTS `identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `identities` (
  `identity_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `standard` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `organization` varchar(128) NOT NULL DEFAULT '',
  `email` varchar(128) NOT NULL,
  `reply-to` varchar(128) NOT NULL DEFAULT '',
  `bcc` varchar(128) NOT NULL DEFAULT '',
  `signature` text,
  `html_signature` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`identity_id`),
  KEY `user_identities_index` (`user_id`,`del`),
  KEY `email_identities_index` (`email`,`del`),
  CONSTRAINT `user_id_fk_identities` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `itipinvitations`
--

DROP TABLE IF EXISTS `itipinvitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `itipinvitations` (
  `token` varchar(64) NOT NULL,
  `event_uid` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `event` text NOT NULL,
  `expires` datetime DEFAULT NULL,
  `cancelled` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`token`),
  KEY `uid_idx` (`event_uid`,`user_id`),
  KEY `fk_itipinvitations_user_id` (`user_id`),
  CONSTRAINT `fk_itipinvitations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_alarms`
--

DROP TABLE IF EXISTS `kolab_alarms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_alarms` (
  `event_id` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `notifyat` datetime DEFAULT NULL,
  `dismissed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`event_id`),
  KEY `fk_kolab_alarms_user_id` (`user_id`),
  CONSTRAINT `fk_kolab_alarms_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_configuration`
--

DROP TABLE IF EXISTS `kolab_cache_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_configuration` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `type` varchar(32) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  KEY `configuration_type` (`folder_id`,`type`),
  CONSTRAINT `fk_kolab_cache_configuration_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_contact`
--

DROP TABLE IF EXISTS `kolab_cache_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_contact` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` longtext NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `type` varchar(32) CHARACTER SET ascii NOT NULL,
  `surname` varchar(128) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `name` varchar(128) DEFAULT NULL,
  `firstname` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  KEY `contact_type` (`folder_id`,`type`),
  CONSTRAINT `fk_kolab_cache_contact_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_event`
--

DROP TABLE IF EXISTS `kolab_cache_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_event` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `dtstart` datetime DEFAULT NULL,
  `dtend` datetime DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  CONSTRAINT `fk_kolab_cache_event_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_file`
--

DROP TABLE IF EXISTS `kolab_cache_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_file` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  KEY `folder_filename` (`folder_id`,`filename`),
  CONSTRAINT `fk_kolab_cache_file_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_freebusy`
--

DROP TABLE IF EXISTS `kolab_cache_freebusy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_freebusy` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `dtstart` datetime DEFAULT NULL,
  `dtend` datetime DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  CONSTRAINT `fk_kolab_cache_freebusy_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_journal`
--

DROP TABLE IF EXISTS `kolab_cache_journal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_journal` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `dtstart` datetime DEFAULT NULL,
  `dtend` datetime DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  CONSTRAINT `fk_kolab_cache_journal_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_note`
--

DROP TABLE IF EXISTS `kolab_cache_note`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_note` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  CONSTRAINT `fk_kolab_cache_note_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_cache_task`
--

DROP TABLE IF EXISTS `kolab_cache_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_cache_task` (
  `folder_id` bigint(20) unsigned NOT NULL,
  `msguid` bigint(20) unsigned NOT NULL,
  `uid` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime DEFAULT NULL,
  `changed` datetime DEFAULT NULL,
  `data` text NOT NULL,
  `xml` text NOT NULL,
  `tags` varchar(255) NOT NULL,
  `words` text NOT NULL,
  `dtstart` datetime DEFAULT NULL,
  `dtend` datetime DEFAULT NULL,
  PRIMARY KEY (`folder_id`,`msguid`),
  CONSTRAINT `fk_kolab_cache_task_folder` FOREIGN KEY (`folder_id`) REFERENCES `kolab_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kolab_folders`
--

DROP TABLE IF EXISTS `kolab_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kolab_folders` (
  `folder_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resource` varchar(255) NOT NULL,
  `type` varchar(32) NOT NULL,
  `synclock` int(10) NOT NULL DEFAULT '0',
  `ctag` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`folder_id`),
  KEY `resource_type` (`resource`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `options` (
  `attribute` varchar(128) NOT NULL,
  `option_values` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ou_types`
--

DROP TABLE IF EXISTS `ou_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ou_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_types`
--

DROP TABLE IF EXISTS `resource_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_types`
--

DROP TABLE IF EXISTS `role_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searches`
--

DROP TABLE IF EXISTS `searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searches` (
  `search_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` int(3) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  PRIMARY KEY (`search_id`),
  UNIQUE KEY `uniqueness` (`user_id`,`type`,`name`),
  CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session`
--

DROP TABLE IF EXISTS `session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `session` (
  `sess_id` varchar(128) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `ip` varchar(40) NOT NULL,
  `vars` mediumtext NOT NULL,
  PRIMARY KEY (`sess_id`),
  KEY `changed_index` (`changed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sharedfolder_types`
--

DROP TABLE IF EXISTS `sharedfolder_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sharedfolder_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_content`
--

DROP TABLE IF EXISTS `syncroton_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_content` (
  `id` varchar(40) NOT NULL,
  `device_id` varchar(40) NOT NULL,
  `folder_id` varchar(40) NOT NULL,
  `contentid` varchar(128) NOT NULL,
  `creation_time` datetime DEFAULT NULL,
  `creation_synckey` int(11) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id--folder_id--contentid` (`device_id`,`folder_id`,`contentid`),
  KEY `syncroton_contents::device_id` (`device_id`),
  CONSTRAINT `syncroton_contents::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_data`
--

DROP TABLE IF EXISTS `syncroton_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_data` (
  `id` varchar(40) NOT NULL,
  `class` varchar(40) NOT NULL,
  `folder_id` varchar(40) NOT NULL,
  `data` longblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_data_folder`
--

DROP TABLE IF EXISTS `syncroton_data_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_data_folder` (
  `id` varchar(40) NOT NULL,
  `type` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `owner_id` varchar(40) NOT NULL,
  `parent_id` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_device`
--

DROP TABLE IF EXISTS `syncroton_device`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_device` (
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
  `lastsynccollection` longblob,
  `lastping` datetime DEFAULT NULL,
  `contactsfilter_id` varchar(40) DEFAULT NULL,
  `calendarfilter_id` varchar(40) DEFAULT NULL,
  `tasksfilter_id` varchar(40) DEFAULT NULL,
  `emailfilter_id` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_id--deviceid` (`owner_id`,`deviceid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_folder`
--

DROP TABLE IF EXISTS `syncroton_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_folder` (
  `id` varchar(40) NOT NULL,
  `device_id` varchar(40) NOT NULL,
  `class` varchar(64) NOT NULL,
  `folderid` varchar(254) NOT NULL,
  `parentid` varchar(254) DEFAULT NULL,
  `displayname` varchar(254) NOT NULL,
  `type` int(11) NOT NULL,
  `creation_time` datetime NOT NULL,
  `lastfiltertype` int(11) DEFAULT NULL,
  `supportedfields` longblob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id--class--folderid` (`device_id`,`class`(40),`folderid`(40)),
  KEY `folderstates::device_id--devices::id` (`device_id`),
  CONSTRAINT `folderstates::device_id--devices::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_modseq`
--

DROP TABLE IF EXISTS `syncroton_modseq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_modseq` (
  `device_id` varchar(40) NOT NULL,
  `folder_id` varchar(40) NOT NULL,
  `synctime` varchar(14) NOT NULL,
  `data` longblob,
  PRIMARY KEY (`device_id`,`folder_id`,`synctime`),
  KEY `syncroton_modseq::device_id` (`device_id`),
  CONSTRAINT `syncroton_modseq::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_policy`
--

DROP TABLE IF EXISTS `syncroton_policy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_policy` (
  `id` varchar(40) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `policy_key` varchar(64) NOT NULL,
  `json_policy` blob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syncroton_synckey`
--

DROP TABLE IF EXISTS `syncroton_synckey`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syncroton_synckey` (
  `id` varchar(40) NOT NULL,
  `device_id` varchar(40) NOT NULL DEFAULT '',
  `type` varchar(64) NOT NULL DEFAULT '',
  `counter` int(11) NOT NULL DEFAULT '0',
  `lastsync` datetime DEFAULT NULL,
  `pendingdata` longblob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id--type--counter` (`device_id`,`type`,`counter`),
  CONSTRAINT `syncroton_synckey::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system`
--

DROP TABLE IF EXISTS `system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system` (
  `name` varchar(64) NOT NULL,
  `value` mediumtext,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasklists`
--

DROP TABLE IF EXISTS `tasklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasklists` (
  `tasklist_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(8) NOT NULL,
  `showalarms` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tasklist_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tasklist_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tasklist_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `uid` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `changed` datetime NOT NULL,
  `del` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `description` text,
  `tags` text,
  `date` varchar(10) DEFAULT NULL,
  `time` varchar(5) DEFAULT NULL,
  `startdate` varchar(10) DEFAULT NULL,
  `starttime` varchar(5) DEFAULT NULL,
  `flagged` tinyint(4) NOT NULL DEFAULT '0',
  `complete` float NOT NULL DEFAULT '0',
  `alarms` varchar(255) DEFAULT NULL,
  `recurrence` varchar(255) DEFAULT NULL,
  `organizer` varchar(255) DEFAULT NULL,
  `attendees` text,
  `notify` datetime DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `tasklisting` (`tasklist_id`,`del`,`date`),
  KEY `uid` (`uid`),
  CONSTRAINT `fk_tasks_tasklist_id` FOREIGN KEY (`tasklist_id`) REFERENCES `tasklists` (`tasklist_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_types`
--

DROP TABLE IF EXISTS `user_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  `used_for` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mail_host` varchar(128) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `last_login` datetime DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `preferences` text,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`,`mail_host`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-09-17 17:15:02

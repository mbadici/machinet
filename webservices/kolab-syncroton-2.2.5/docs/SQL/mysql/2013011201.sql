ALTER TABLE `syncroton_content` DROP KEY `device_id--folder_id--contentid`;
ALTER TABLE `syncroton_content` MODIFY `contentid` varchar(128) DEFAULT NULL;
ALTER TABLE `syncroton_content` ADD UNIQUE KEY `device_id--folder_id--contentid` (`device_id`(40),`folder_id`(40),`contentid`(128));

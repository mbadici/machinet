DELETE FROM `syncroton_content` WHERE `device_id` IS NULL;
DELETE FROM `syncroton_content` WHERE `folder_id` IS NULL;
DELETE FROM `syncroton_content` WHERE `contentid` IS NULL;
ALTER TABLE `syncroton_content`
    MODIFY `device_id` varchar(40) NOT NULL,
    MODIFY `folder_id` varchar(40) NOT NULL,
    MODIFY `contentid` varchar(128) NOT NULL;

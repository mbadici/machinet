ALTER TABLE `syncroton_device` ADD `lastsynccollection` longblob DEFAULT NULL;
ALTER TABLE `syncroton_folder` ADD `supportedfields` longblob DEFAULT NULL;
ALTER TABLE `syncroton_data` CHANGE `type` `class` varchar(40) NOT NULL;
CREATE TABLE `syncroton_data_folder` (
    `id` varchar(40) NOT NULL,
    `type` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `owner_id` varchar(40) NOT NULL,
    `parent_id` varchar(40) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

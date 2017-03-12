CREATE TABLE IF NOT EXISTS `syncroton_modseq` (
    `device_id` varchar(40) NOT NULL,
    `folder_id` varchar(40) NOT NULL,
    `synctime` varchar(14) NOT NULL,
    `data` longblob,
    PRIMARY KEY (`device_id`,`folder_id`,`synctime`),
    KEY `syncroton_modseq::device_id` (`device_id`),
    CONSTRAINT `syncroton_modseq::device_id--syncroton_device::id` FOREIGN KEY (`device_id`) REFERENCES `syncroton_device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

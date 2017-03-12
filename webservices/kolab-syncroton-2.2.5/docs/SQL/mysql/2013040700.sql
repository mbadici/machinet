DROP TABLE IF EXISTS `syncroton_policy`;
CREATE TABLE IF NOT EXISTS `syncroton_policy` (
    `id` varchar(40) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `policy_key` varchar(64) NOT NULL,
    `json_policy` blob NOT NULL,
    PRIMARY KEY (`id`)
);

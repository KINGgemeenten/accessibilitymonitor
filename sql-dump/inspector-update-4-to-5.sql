# Alter the "actions" table.
ALTER TABLE `actions` ADD `website_id` int(10) unsigned NOT NULL;
ALTER TABLE `actions` CHANGE `item_uid` `URL` varchar(512) DEFAULT NULL

# Alter the "website" table.
ALTER TABLE `website` CHANGE `website_id` `website_id` int(10) unsigned NOT NULL;
DROP INDEX `url` ON `website`;

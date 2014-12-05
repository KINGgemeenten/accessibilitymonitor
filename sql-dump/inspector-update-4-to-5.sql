# Alter the "actions" table.
ALTER TABLE `actions` ADD `website_test_results_id` int(10) unsigned NOT NULL;
ALTER TABLE `actions` CHANGE `item_uid` `url` varchar(512) DEFAULT NULL

# Alter the "url" table.
ALTER TABLE `actions` CHANGE `full_url` `url` varchar(1024) NOT NULL

# Alter the "website" table.
ALTER TABLE `website` CHANGE `website_id` `website_test_results_id` int(10) unsigned NOT NULL;
DROP INDEX `url` ON `website`;

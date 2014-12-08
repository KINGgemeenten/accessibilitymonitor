# Drop the "actions" table.
DROP TABLE `actions`;

# Alter the "url" table.
ALTER TABLE `url` CHANGE `full_url` `url` varchar(1024) NOT NULL;
ALTER TABLE `url` CHANGE `website_id` `website_test_results_id` int(10) unsigned NOT NULL;
ALTER TABLE `url` ADD `analysis` int(11) DEFAULT NULL;

# Drop the "website" table.
DROP TABLE `website`;

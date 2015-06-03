# Rename the url table to url_result, so we don't have to migrate the test
# results, which are big and for which migration will take a lot of time.
RENAME TABLE `url` TO `url_result`;

# Create the new url table.
CREATE TABLE `url` (
  `url_id` int(11) NOT NULL AUTO_INCREMENT,
  `website_test_results_id` int(10) unsigned NOT NULL,
  `url` varchar(1024) NOT NULL,
  `status` int(11) NOT NULL,
  `last_processed` int(11) DEFAULT NULL,
  `is_root` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `queue_name` varchar(255) NOT NULL DEFAULT '',
  `failed_test_count` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`url_id`),
  KEY `status` (`status`),
  KEY `failed_test_count` (`failed_test_count`),
  KEY `last_processed` (`last_processed`),
  KEY `website_test_results_id` (`website_test_results_id`),
  KEY `queue_name` (`queue_name`),
  KEY `status_queue_name` (`status`,`queue_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Migrate URL metadata from url_result to url.
INSERT INTO `url` (url_id, website_test_results_id, url, status, last_processed, is_root, queue_name, failed_test_count) SELECT url_id, website_test_results_id, url, status, analysis, is_root, queue_name, failed_test_count FROM `url_result`;

# Drop the migrated columns from the url tabel.
ALTER TABLE `url_result` DROP COLUMN website_test_results_id, DROP COLUMN url, DROP COLUMN status, DROP COLUMN analysis, DROP COLUMN is_root, DROP COLUMN queue_name, DROP COLUMN failed_test_count;

# Finalize the update.
OPTIMIZE TABLE `url`;
OPTIMIZE TABLE `url_result`;

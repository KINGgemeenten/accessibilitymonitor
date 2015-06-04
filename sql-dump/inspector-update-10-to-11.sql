#Create the `test_run` table.
CREATE TABLE `test_run` (
  `priority` int(11) unsigned NOT NULL DEFAULT '0',
  `created` int(11) unsigned NOT NULL DEFAULT '0',
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `website_test_results_id` int(10) unsigned NOT NULL,
  `last_processed` int(11) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `website_test_results_id` (`website_test_results_id`),
  KEY `created` (`created`),
  KEY `priority` (`priority`),
  KEY `group_name` (`group_name`),
  KEY `last_processed` (`last_processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Migrate test run metadata from `url` and `queue` to `test_run`.
INSERT INTO `test_run` (priority, created, `group_name`, website_test_results_id, last_processed) SELECT q.priority, q.created, u.queue_name, u.website_test_results_id, MAX(u.analysis) FROM url u LEFT JOIN queue q ON q.name = u.queue_name GROUP BY u.website_test_results_id;

# Rename the `url` table to `url_result`, so we don't have to migrate the test
# results, which are big and for which migration will take a lot of time.
RENAME TABLE `url` TO `url_result`;

# Create the new `url` table.
CREATE TABLE `url` (
  `url_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `test_run_id` int(11) unsigned NOT NULL,
  `url` varchar(1024) NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  `last_processed` int(11) unsigned DEFAULT '0',
  `is_root` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `failed_test_count` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`url_id`),
  KEY `status` (`status`),
  KEY `failed_test_count` (`failed_test_count`),
  KEY `last_processed` (`last_processed`),
  KEY `test_run_id` (`test_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Migrate URL metadata from `url_result` to `url`.
INSERT INTO `url` (url_id, url, status, last_processed, is_root, failed_test_count, test_run_id) SELECT ur.url_id, ur.url, ur.status, ur.analysis, ur.is_root, ur.failed_test_count, tr.id FROM `url_result` ur LEFT JOIN test_run tr ON ur.website_test_results_id = tr.website_test_results_id;

# Drop the migrated columns from the `url_result` table.
ALTER TABLE `url_result` DROP COLUMN website_test_results_id, DROP COLUMN url, DROP COLUMN status, DROP COLUMN analysis, DROP COLUMN is_root, DROP COLUMN queue_name, DROP COLUMN failed_test_count;

# Alter the fields in the `url_result` table.
ALTER TABLE `url_result` CHANGE `url_id` `url_id` int(11) unsigned NOT NULL;

# Change the character encoding to UTF-8 for the `url_result` table.
ALTER TABLE `url_result` CONVERT TO CHARACTER SET utf8;

# Drop the `queue` table.
DROP TABLE `queue`;

# Column `website_test_results_id` in table `test_run` is redundant, but must
# be kept to allow clients to update. It is dropped in
# inspector-update-11-to-12.sql.

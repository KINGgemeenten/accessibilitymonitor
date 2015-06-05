#Create the `test_run` table.
CREATE TABLE `test_run` (
  `priority` int(3) unsigned NOT NULL DEFAULT '0',
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
INSERT INTO `test_run` (priority, created, `group_name`, website_test_results_id, last_processed) SELECT q.priority, q.created, u.queue_name, u.website_test_results_id, MAX(u.last_processed) FROM url u LEFT JOIN queue q ON q.name = u.queue_name GROUP BY u.website_test_results_id;
UPDATE test_run SET last_processed = 0 WHERE last_processed IS NULL;

# Add columns to the `url` table.
ALTER TABLE url ADD COLUMN `test_run_id` int(11) unsigned NOT NULL;
ALTER TABLE url ADD INDEX `is_root` (`is_root`);
ALTER TABLE url ADD INDEX `test_run_id` (`test_run_id`);
ALTER TABLE url ADD INDEX `status_test_run_id` (`status`, `test_run_id`);

# Alter columns in the `url` table.
ALTER TABLE url CHANGE `url_id` `url_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE url CHANGE `status` `status` tinyint(3) unsigned NOT NULL;
ALTER TABLE url CHANGE `last_processed` `last_processed` int(11) unsigned DEFAULT '0';
ALTER TABLE url CHANGE `failed_test_count` `failed_test_count` int(11) unsigned NOT NULL DEFAULT '0';

# Insert test run IDs into `url`;
UPDATE `url` INNER JOIN test_run ON test_run.website_test_results_id = url.website_test_results_id SET test_run_id = test_run.id;

# Drop the `queue` table.
DROP TABLE queue;

# Drop columns from the `url` table.
ALTER TABLE url DROP COLUMN queue_name;
ALTER TABLE url DROP COLUMN website_test_results_id;

# Drop auto increment and negative value support from `url_result.url_id`.
ALTER TABLE `url_result` CHANGE `url_id` `url_id` int(11) unsigned NOT NULL;

# Change the character encoding to UTF-8 for the `url_result` table.
ALTER TABLE `url_result` CONVERT TO CHARACTER SET utf8;

# Finalize the update.
OPTIMIZE TABLE `url`;
OPTIMIZE TABLE `url_result`;

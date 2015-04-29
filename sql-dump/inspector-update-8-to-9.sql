# Create the "queue" table.
CREATE TABLE `queue` (
  `priority` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `created` int(11) DEFAULT NULL,
  `last_request` int(11) DEFAULT '0',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Add the "queue_name" field to the "url" table.
ALTER TABLE url ADD queue_name varchar(255) NOT NULL DEFAULT '';

# Drop the "priority" field from the "url" table.
ALTER TABLE url DROP COLUMN priority;

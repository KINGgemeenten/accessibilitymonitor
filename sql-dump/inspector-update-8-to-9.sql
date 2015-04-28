# Create the "queue" table.
CREATE TABLE `queue` (
  `priority` int(11) NOT NULL,
  `id` varchar(255) NOT NULL DEFAULT '',
  `created` int(11) DEFAULT NULL,
  `last_request` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

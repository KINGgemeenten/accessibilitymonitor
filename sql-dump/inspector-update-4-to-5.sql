# Drop the "actions" table.
DROP TABLE `actions`;

# Alter the "url" table.
ALTER TABLE `actions` CHANGE `full_url` `url` varchar(1024) NOT NULL

# Drop the "website" table.
DROP TABLE `website`;

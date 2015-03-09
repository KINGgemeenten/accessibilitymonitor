# Add the "is_root" column to the "url" table.
ALTER TABLE url ADD is_root tinyint(3) unsigned NOT NULL DEFAULT '0';

# Extend the capacity of the "pagespeed_result" in the "url" table.
ALTER TABLE `url` CHANGE `pagespeed_result` `pagespeed_result` longtext;

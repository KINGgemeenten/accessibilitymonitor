# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 192.168.50.5 (MySQL 5.5.43-0ubuntu0.12.04.1)
# Database: inspector
# Generation Time: 2015-05-29 11:49:24 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table test_run
# ------------------------------------------------------------

DROP TABLE IF EXISTS `test_run`;

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



# Dump of table url
# ------------------------------------------------------------

DROP TABLE IF EXISTS `url`;

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
  KEY `is_root` (`is_root`),
  KEY `last_processed` (`last_processed`),
  KEY `test_run_id` (`test_run_id`)
  KEY `status_test_run_id` (`status`, `test_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table url_result
# ------------------------------------------------------------

DROP TABLE IF EXISTS `url_result`;

CREATE TABLE `url_result` (
  `url_id` int(11) unsigned NOT NULL,
  `cms` varchar(1024) DEFAULT NULL,
  `quail_result` text,
  `pagespeed_result` longtext,
  PRIMARY KEY (`url_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

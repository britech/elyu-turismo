-- MySQL dump 10.13  Distrib 5.5.62, for Win64 (AMD64)
--
-- Host: localhost    Database: tourism
-- ------------------------------------------------------
-- Server version	5.6.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `classification`
--

DROP TABLE IF EXISTS `classification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classification_UN` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guest`
--

DROP TABLE IF EXISTS `guest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guest` (
  `id` varchar(50) NOT NULL,
  `ipaddress` varchar(50) DEFAULT NULL,
  `latitude` varchar(100) DEFAULT NULL,
  `longitude` varchar(100) DEFAULT NULL,
  `daterecorded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `placeofinterest`
--

DROP TABLE IF EXISTS `placeofinterest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `placeofinterest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(1000) NOT NULL,
  `description` text,
  `commuterGuide` text,
  `address` varchar(1000) NOT NULL,
  `town` varchar(100) NOT NULL,
  `latitude` varchar(100) NOT NULL,
  `longitude` varchar(100) NOT NULL,
  `arenabled` tinyint(4) DEFAULT '0',
  `displayable` tinyint(4) DEFAULT '0',
  `descriptionWysiwyg` json DEFAULT NULL,
  `commuterGuideWysiwyg` json DEFAULT NULL,
  `overview` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poiclassification`
--

DROP TABLE IF EXISTS `poiclassification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poiclassification` (
  `placeofinterest` int(11) DEFAULT NULL,
  `classification` int(11) DEFAULT NULL,
  KEY `poiclassification_fk` (`placeofinterest`),
  KEY `poiclassification_fk_1` (`classification`),
  CONSTRAINT `poiclassification_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `poiclassification_fk_1` FOREIGN KEY (`classification`) REFERENCES `classification` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poidiscovery`
--

DROP TABLE IF EXISTS `poidiscovery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poidiscovery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(1000) NOT NULL,
  `latitude` varchar(100) NOT NULL,
  `longitude` varchar(100) NOT NULL,
  `guest` varchar(50) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `comments` text,
  `status` varchar(50) DEFAULT 'for_review',
  `dateadded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `poidiscovery_fk` (`guest`),
  CONSTRAINT `poidiscovery_fk` FOREIGN KEY (`guest`) REFERENCES `guest` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poifee`
--

DROP TABLE IF EXISTS `poifee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poifee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `placeofinterest` int(11) NOT NULL,
  `description` varchar(300) NOT NULL,
  `amount` decimal(10,2) DEFAULT '0.00',
  `enabled` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `poifee_fk` (`placeofinterest`),
  CONSTRAINT `poifee_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poirating`
--

DROP TABLE IF EXISTS `poirating`;
CREATE TABLE `poirating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `placeofinterest` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `guest` varchar(50) DEFAULT NULL,
  `comments` text,
  `status` varchar(50) DEFAULT 'for_review',
  `dateadded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `poirating_fk` (`placeofinterest`),
  KEY `poirating_fk_1` (`guest`),
  KEY `poirating_alternate_search_IDX` (`placeofinterest`,`dateadded`) USING BTREE,
  KEY `poirating_primary_search_IDX` (`dateadded`) USING BTREE,
  CONSTRAINT `poirating_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `poirating_fk_1` FOREIGN KEY (`guest`) REFERENCES `guest` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `poischedule`
--

DROP TABLE IF EXISTS `poischedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poischedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `placeofinterest` int(11) NOT NULL,
  `startingtime` time NOT NULL,
  `closingtime` time NOT NULL,
  `date` date DEFAULT NULL,
  `day` varchar(100) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  `openEveryday` tinyint(4) DEFAULT '0',
  `openAllDay` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `poischedule_fk` (`placeofinterest`),
  CONSTRAINT `poischedule_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poitag`
--

DROP TABLE IF EXISTS `poitag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poitag` (
  `placeofinterest` int(11) NOT NULL,
  `tag` int(11) DEFAULT NULL,
  KEY `poitag_fk` (`placeofinterest`),
  KEY `poitag_fk_1` (`tag`),
  CONSTRAINT `poitag_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `poitag_fk_1` FOREIGN KEY (`tag`) REFERENCES `topictag` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poivisit`
--

DROP TABLE IF EXISTS `poivisit`;
CREATE TABLE `poivisit` (
  `placeofinterest` int(11) NOT NULL,
  `guest` varchar(50) DEFAULT NULL,
  `dateadded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `poivisit_fk` (`placeofinterest`),
  KEY `poivisit_fk_1` (`guest`),
  KEY `poivisit_alternate_search_IDX` (`placeofinterest`,`dateadded`) USING BTREE,
  KEY `poivisit_primary_search_IDX` (`dateadded`) USING BTREE,
  CONSTRAINT `poivisit_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `poivisit_fk_1` FOREIGN KEY (`guest`) REFERENCES `guest` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `topictag`
--

DROP TABLE IF EXISTS `topictag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topictag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topictag_UN` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

CREATE TABLE `poiimage` (
  `id` varchar(50) CHARACTER SET latin1 NOT NULL,
  `placeofinterest` int(11) NOT NULL,
  `designation` varchar(50) CHARACTER SET latin1 NOT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `poiimage_FK` (`placeofinterest`),
  CONSTRAINT `poiimage_FK` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE USER 'tourism_user'@'localhost' IDENTIFIED BY 'u&e6uaAz-b#^Lj7m';
GRANT SELECT,INSERT,UPDATE,DELETE ON tourism.* TO 'tourism_user'@'localhost';

CREATE USER 'tourism_user'@'%' IDENTIFIED BY 'u&e6uaAz-b#^Lj7m';
GRANT SELECT,INSERT,UPDATE,DELETE ON tourism.* TO 'tourism_user'@'%';

FLUSH PRIVILEGES;
--
-- Dumping routines for database 'tourism'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-07-15  6:20:44

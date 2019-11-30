-- MySQL dump 10.13  Distrib 5.5.62, for Win64 (AMD64)
--
-- Host: 192.168.99.100    Database: tourism
-- ------------------------------------------------------
-- Server version	5.7.26

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
-- Table structure for table `opendatalog`
--

DROP TABLE IF EXISTS `opendatalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opendatalog` (
  `email` text CHARACTER SET latin1 NOT NULL,
  `reportType` tinytext CHARACTER SET latin1 NOT NULL,
  `downloadTimestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
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
  `displayable` tinyint(4) DEFAULT '1',
  `descriptionWysiwyg` json DEFAULT NULL,
  `commuterGuideWysiwyg` json DEFAULT NULL,
  `imageName` text,
  `photoCredit` text,
  `images` text,
  `developmentLevel` varchar(100) DEFAULT NULL,
  `arLink` varchar(1000) DEFAULT NULL,
  `videoLink` varchar(1000) DEFAULT NULL,
  `videoCredit` text,
  PRIMARY KEY (`id`),
  KEY `placeofinterest_town_IDX` (`town`) USING BTREE,
  FULLTEXT KEY `placeofinterest_name_IDX` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
-- Table structure for table `poicomment`
--

DROP TABLE IF EXISTS `poicomment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poicomment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text CHARACTER SET latin1 NOT NULL,
  `contentWysiwyg` json DEFAULT NULL,
  `name` varchar(1000) CHARACTER SET latin1 DEFAULT NULL,
  `email` tinytext CHARACTER SET latin1,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `placeofinterest` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poicomment_FK` (`placeofinterest`),
  CONSTRAINT `poicomment_FK` FOREIGN KEY (`placeofinterest`) REFERENCES `poicomment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poicontact`
--

DROP TABLE IF EXISTS `poicontact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poicontact` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `placeofinterest` int(11) NOT NULL,
  `type` tinytext CHARACTER SET latin1 NOT NULL,
  `value` varchar(500) CHARACTER SET latin1 NOT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `poicontact_FK` (`placeofinterest`),
  CONSTRAINT `poicontact_FK` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
  `description` varchar(300) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `freePrice` tinyint(4) DEFAULT '0',
  `enabled` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `poifee_fk` (`placeofinterest`),
  CONSTRAINT `poifee_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poischedule`
--

DROP TABLE IF EXISTS `poischedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poischedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `placeofinterest` int(11) NOT NULL,
  `openingtime` time DEFAULT NULL,
  `closingtime` time DEFAULT NULL,
  `date` date DEFAULT NULL,
  `day` varchar(100) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `open24h` tinyint(4) DEFAULT '0',
  `open7d` tinyint(4) DEFAULT '0',
  `enabled` tinyint(4) DEFAULT '1',
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poivisit` (
  `placeofinterest` int(11) NOT NULL,
  `dateadded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `poivisit_fk` (`placeofinterest`),
  KEY `poivisit_alternate_search_IDX` (`placeofinterest`,`dateadded`) USING BTREE,
  KEY `poivisit_primary_search_IDX` (`dateadded`) USING BTREE,
  CONSTRAINT `poivisit_fk` FOREIGN KEY (`placeofinterest`) REFERENCES `placeofinterest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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

--
-- Table structure for table `townproduct`
--

DROP TABLE IF EXISTS `townproduct`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `townproduct` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(500) CHARACTER SET latin1 NOT NULL,
  `arLink` varchar(1000) CHARACTER SET latin1 DEFAULT NULL,
  `town` varchar(100) NOT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  `description` text,
  `imageFile` text,
  `images` text,
  `photoCredit` text,
  PRIMARY KEY (`id`),
  KEY `townproduct_town_IDX` (`town`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET latin1 NOT NULL,
  `password` tinytext CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `visitorgeocode`
--

DROP TABLE IF EXISTS `visitorgeocode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitorgeocode` (
  `placeofinterest` int(11) NOT NULL,
  `userAgent` text CHARACTER SET latin1 NOT NULL,
  `dateAdded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` tinytext CHARACTER SET latin1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `town`
--
DROP TABLE IF EXISTS `town`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `town` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `tourismCircuit` varchar(100) NOT NULL,
  `description` text,
  `descriptionWysiwyg` json DEFAULT NULL,
  `commuterGuide` text,
  `commuterGuideWysiwyg` json DEFAULT NULL,
  `otherDetails` text,
  `otherDetailsWysiwyg` text,
  `bannerImage` text,
  `linkImage` text,
  `photoCredits` text,
  PRIMARY KEY (`id`),
  KEY `town_name_IDX` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8

INSERT INTO town (name,tourismCircuit,description,descriptionWysiwyg,commuterGuide,commuterGuideWysiwyg,otherDetails,otherDetailsWysiwyg,bannerImage,linkImage,photoCredits) VALUES 
('Balaoan','North',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Bangar','North',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Luna','North',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Santol','North',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Sudipen','North',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Bacnotan','Central',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('San Fernando','Central',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('San Juan','Central',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('San Gabriel','Central',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Bauang','Central East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Bagulin','Central East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Burgos','Central East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Naguilian','Central East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Agoo','South',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Aringay','South',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Caba','South',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Santo Tomas','South',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Pugo','South East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Rosario','South East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
,('Tubao','South East',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);

CREATE TABLE `complaint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poi` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobileNumber` varchar(20) DEFAULT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `complain_FK` (`poi`),
  CONSTRAINT `complain_FK` FOREIGN KEY (`poi`) REFERENCES `complaint` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8

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

-- Dump completed on 2019-09-15 17:22:30
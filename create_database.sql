-- MySQL dump 10.9
--
-- Host: localhost    Database: doko
-- ------------------------------------------------------
-- Server version	4.1.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

--
-- Table structure for table `Card`
--

DROP TABLE IF EXISTS `Card`;
CREATE TABLE `Card` (
  `id` int(11) NOT NULL auto_increment,
  `suite` enum('diamonds','hearts','spades','clubs') NOT NULL default 'diamonds',
  `strength` enum('nine','ten','jack','queen','king','ace') NOT NULL default 'nine',
  `points` tinyint(4) NOT NULL default '0',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Card`
--


/*!40000 ALTER TABLE `Card` DISABLE KEYS */;
LOCK TABLES `Card` WRITE;
INSERT INTO `Card` VALUES (1,'hearts','ten',10),(2,'hearts','ten',10),(3,'clubs','queen',3),(4,'clubs','queen',3),(5,'spades','queen',3),(6,'spades','queen',3),(7,'hearts','queen',3),(8,'hearts','queen',3),(9,'diamonds','queen',3),(10,'diamonds','queen',3),(11,'clubs','jack',2),(12,'clubs','jack',2),(13,'spades','jack',2),(14,'spades','jack',2),(15,'hearts','jack',2),(16,'hearts','jack',2),(17,'diamonds','jack',2),(18,'diamonds','jack',2),(19,'diamonds','ace',11),(20,'diamonds','ace',11),(21,'diamonds','ten',10),(22,'diamonds','ten',10),(23,'diamonds','king',4),(24,'diamonds','king',4),(25,'diamonds','nine',0),(26,'diamonds','nine',0),(27,'clubs','ace',11),(28,'clubs','ace',11),(29,'clubs','ten',10),(30,'clubs','ten',10),(31,'clubs','king',4),(32,'clubs','king',4),(33,'clubs','nine',0),(34,'clubs','nine',0),(35,'spades','ace',11),(36,'spades','ace',11),(37,'spades','ten',10),(38,'spades','ten',10),(39,'spades','king',4),(40,'spades','king',4),(41,'spades','nine',0),(42,'spades','nine',0),(43,'hearts','ace',11),(44,'hearts','ace',11),(45,'hearts','king',4),(46,'hearts','king',4),(47,'hearts','nine',0),(48,'hearts','nine',0);
UNLOCK TABLES;
/*!40000 ALTER TABLE `Card` ENABLE KEYS */;

--
-- Table structure for table `Comment`
--

DROP TABLE IF EXISTS `Comment`;
CREATE TABLE `Comment` (
  `mod_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_date` timestamp NOT NULL default '0000-00-00 00:00:00',
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) default NULL,
  `play_id` int(11) default NULL,
  `comment` text,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Comment`
--


/*!40000 ALTER TABLE `Comment` DISABLE KEYS */;
LOCK TABLES `Comment` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Comment` ENABLE KEYS */;

--
-- Table structure for table `Game`
--

DROP TABLE IF EXISTS `Game`;
CREATE TABLE `Game` (
  `mod_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_date` timestamp NOT NULL default '0000-00-00 00:00:00',
  `randomnumbers` varchar(136) default NULL,
  `type` enum('solo','wedding','poverty','dpoverty') default NULL,
  `solo` enum('trumpless','jack','queen','trump','club','spade','heart','silent') default NULL,
  `status` enum('pre','play','gameover') default NULL,
  `followup_of` int(11) default NULL,
  `id` int(11) NOT NULL auto_increment,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Game`
--


/*!40000 ALTER TABLE `Game` DISABLE KEYS */;
LOCK TABLES `Game` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Game` ENABLE KEYS */;

--
-- Table structure for table `Hand`
--

DROP TABLE IF EXISTS `Hand`;
CREATE TABLE `Hand` (
  `id` int(11) NOT NULL auto_increment,
  `game_id` int(11) NOT NULL default '0',
  `user_id` int(11) NOT NULL default '0',
  `hash` varchar(33) default NULL,
  `status` enum('start','init','check','poverty','play','gameover') default 'start',
  `position` tinyint(4) NOT NULL default '0',
  `party` enum('re','contra') default NULL,
  `sickness` enum('wedding','nines','poverty','solo') default NULL,
  `solo` enum('trumpless','jack','queen','trump','club','spade','heart','silent') default NULL,
  `sick_call` enum('true','false') default 'false',
  `win_call` enum('true','false') default 'false',
  `point_call` enum('90','60','30','0') default NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Hand`
--


/*!40000 ALTER TABLE `Hand` DISABLE KEYS */;
LOCK TABLES `Hand` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Hand` ENABLE KEYS */;

--
-- Table structure for table `Hand_Card`
--

DROP TABLE IF EXISTS `Hand_Card`;
CREATE TABLE `Hand_Card` (
  `id` int(11) NOT NULL auto_increment,
  `hand_id` int(11) NOT NULL default '0',
  `card_id` int(11) NOT NULL default '0',
  `played` enum('true','false') default 'false',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Hand_Card`
--


/*!40000 ALTER TABLE `Hand_Card` DISABLE KEYS */;
LOCK TABLES `Hand_Card` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Hand_Card` ENABLE KEYS */;

--
-- Table structure for table `Play`
--

DROP TABLE IF EXISTS `Play`;
CREATE TABLE `Play` (
  `mod_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_date` timestamp NOT NULL default '0000-00-00 00:00:00',
  `id` int(11) NOT NULL auto_increment,
  `trick_id` int(11) NOT NULL default '0',
  `hand_card_id` int(11) NOT NULL default '0',
  `sequence` tinyint(4) NOT NULL default '0',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Play`
--


/*!40000 ALTER TABLE `Play` DISABLE KEYS */;
LOCK TABLES `Play` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Play` ENABLE KEYS */;

--
-- Table structure for table `Score`
--

DROP TABLE IF EXISTS `Score`;
CREATE TABLE `Score` (
  `id` int(11) NOT NULL auto_increment,
  `game_id` int(11) NOT NULL default '0',
  `hand_id` int(11) NOT NULL default '0',
  `score` tinyint(4) default NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Score`
--


/*!40000 ALTER TABLE `Score` DISABLE KEYS */;
LOCK TABLES `Score` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Score` ENABLE KEYS */;

--
-- Table structure for table `Trick`
--

DROP TABLE IF EXISTS `Trick`;
CREATE TABLE `Trick` (
  `mod_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `create_date` timestamp NOT NULL default '0000-00-00 00:00:00',
  `id` int(11) NOT NULL auto_increment,
  `game_id` int(11) NOT NULL default '0',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Trick`
--


/*!40000 ALTER TABLE `Trick` DISABLE KEYS */;
LOCK TABLES `Trick` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `Trick` ENABLE KEYS */;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
CREATE TABLE `User` (
  `id` int(11) NOT NULL auto_increment,
  `fullname` varchar(64) default NULL,
  `email` varchar(64) default NULL,
  `password` varchar(32) default NULL,
  `timezone` tinyint(2) default NULL,
  `last_login` timestamp NOT NULL default '0000-00-00 00:00:00',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `User`
--


/*!40000 ALTER TABLE `User` DISABLE KEYS */;
LOCK TABLES `User` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `User` ENABLE KEYS */;

--
-- Table structure for table `User_Game_Prefs`
--

DROP TABLE IF EXISTS `User_Game_Prefs`;
CREATE TABLE `User_Game_Prefs` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL default '0',
  `game_id` int(11) NOT NULL default '0',
  `pref_key` varchar(64) default NULL,
  `value` varchar(64) default NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `User_Game_Prefs`
--


/*!40000 ALTER TABLE `User_Game_Prefs` DISABLE KEYS */;
LOCK TABLES `User_Game_Prefs` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `User_Game_Prefs` ENABLE KEYS */;

--
-- Table structure for table `User_Prefs`
--

DROP TABLE IF EXISTS `User_Prefs`;
CREATE TABLE `User_Prefs` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL default '0',
  `pref_key` varchar(64) default NULL,
  `value` varchar(64) default NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `User_Prefs`
--


/*!40000 ALTER TABLE `User_Prefs` DISABLE KEYS */;
LOCK TABLES `User_Prefs` WRITE;
UNLOCK TABLES;
/*!40000 ALTER TABLE `User_Prefs` ENABLE KEYS */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


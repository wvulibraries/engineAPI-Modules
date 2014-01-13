SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------

CREATE DATABASE engineCMS;
USE engineCMS;

-- --------------------------------------------------------

CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON engineCMS.* TO 'username'@'localhost';

-- --------------------------------------------------------

-- --
-- Table structure for table `log`
--

DROP TABLE IF EXISTS `log`;
CREATE TABLE IF NOT EXISTS `log` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` int(10) unsigned DEFAULT NULL,
  `ip` varchar(15) DEFAULT NULL,
  `referrer` text,
  `resource` text,
  `useragent` varchar(5000) CHARACTER SET utf8 DEFAULT NULL,
  `querystring` text,
  `site` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------


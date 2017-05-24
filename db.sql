CREATE DATABASE IF NOT EXISTS `swdapi` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `swdapi`;

CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `secret` char(64) NOT NULL,
  `name` varchar(140) NOT NULL,
  `last_active` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `nonce` (
  `value` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires` int(11) NOT NULL,
  UNIQUE KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `clientID` bigint(20) NOT NULL,
  `uid` varchar(64) NOT NULL,
  `secret` char(64) NOT NULL,
  `expires` bigint(20) NOT NULL,
  `timeout` int(11) NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

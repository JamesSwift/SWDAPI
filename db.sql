CREATE DATABASE IF NOT EXISTS `swdapi` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `swdapi`;

CREATE TABLE IF NOT EXISTS `nonce` (
  `value` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires` int(11) NOT NULL,
  UNIQUE KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
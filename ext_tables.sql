CREATE TABLE `tx_hauptsachevideo_domain_model_storedtask`
(
  `uid`           int(11)     NOT NULL AUTO_INCREMENT,
  `pid`           int(11)     NOT NULL DEFAULT '0',
  `tstamp`        int(11)     NOT NULL DEFAULT '0',
  `crdate`        int(11)     NOT NULL DEFAULT '0',
  `file`          int(11)     NOT NULL DEFAULT '0',
  `configuration` mediumtext           DEFAULT NULL,
  `status`        varchar(15) NOT NULL DEFAULT 'new',
  `log`           mediumtext           DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `file` (`file`),
);

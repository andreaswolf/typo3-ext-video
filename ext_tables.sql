CREATE TABLE `tx_hauptsachevideo_task`
(
  `uid`           int(11)        NOT NULL AUTO_INCREMENT,
  `pid`           int(11)        NOT NULL DEFAULT '0',
  `tstamp`        int(11)        NOT NULL DEFAULT '0',
  `crdate`        int(11)        NOT NULL DEFAULT '0',
  `file`          int(11)        NOT NULL,
  `configuration` varbinary(767) NOT NULL,
  `status`        varchar(15)    NOT NULL DEFAULT 'new',
  `log`           mediumtext,
  PRIMARY KEY (`uid`),
  KEY `task` (`file`, `configuration`, `status`),
);

CREATE TABLE `tx_hauptsachevideo_cloudconvert_process`
(
  `uid`     int(11)        NOT NULL AUTO_INCREMENT,
  `pid`     int(11)        NOT NULL DEFAULT '0',
  `tstamp`  int(11)        NOT NULL DEFAULT '0',
  `crdate`  int(11)        NOT NULL DEFAULT '0',
  `file`    int(11)        NOT NULL,
  `mode`    varchar(7)     NOT NULL,
  `options` varbinary(767) NOT NULL,
  `status`  text                    DEFAULT NULL,
  `failed`  tinyint(1)     NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `process` (`file`, `mode`, `options`),
);

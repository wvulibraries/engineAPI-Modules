SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fieldBuilderTest
-- ----------------------------
DROP TABLE IF EXISTS `fieldBuilderTest`;
CREATE TABLE `fieldBuilderTest` (
  `ID`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
)
  ENGINE =InnoDB
  DEFAULT CHARSET =latin1;

INSERT INTO `fieldBuilderTest` (`name`) VALUES ('a'), ('b'), ('c'), ('z'), ('y'), ('x');